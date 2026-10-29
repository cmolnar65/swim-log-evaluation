<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Garmin Connect administration UI and admin actions.
 *
 * Extracted from Admin to keep the main admin coordinator focused while
 * preserving the existing Admin public hook surface.
 */
final class Admin_Garmin {
	private static function garmin_activity_import_state( $user_id, $activity_id ) {
		global $wpdb;
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) return array( 'status' => 'none', 'workout_id' => 0 );
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		$imports = Database::table( 'imports' );
		$row = Database::read( 'get_row',  $wpdb->prepare(
			"SELECT workout_id,import_status FROM %i WHERE user_id=%d AND source_provider=%s AND external_activity_id=%s ORDER BY id DESC LIMIT 1",$imports,
			$user_id, 'garmin', (string) $activity_id
		) );
		if ( ! $row ) return array( 'status' => 'none', 'workout_id' => 0 );
		if ( 'pending' === (string) $row->import_status ) return array( 'status' => 'pending', 'workout_id' => (int) $row->workout_id );
		if ( 'complete' === (string) $row->import_status ) return array( 'status' => 'imported', 'workout_id' => (int) $row->workout_id );
		return array( 'status' => 'recorded', 'workout_id' => (int) $row->workout_id );
	}

	private static function garmin_activity_match_status( $user_id, $activity ) {
		$type = isset( $activity['activityType']['typeKey'] ) ? (string) $activity['activityType']['typeKey'] : '';
		if ( ! in_array( $type, array( 'lap_swimming', 'open_water_swimming' ), true ) ) {
			return 'none';
		}

		$start = '';
		if ( ! empty( $activity['startTimeGMT'] ) ) {
			$start = (string) $activity['startTimeGMT'] . 'Z';
		} elseif ( ! empty( $activity['startTimeLocal'] ) ) {
			$start = (string) $activity['startTimeLocal'];
		}
		if ( '' === $start ) {
			return 'none';
		}

		$workout = array(
			'workout_type'    => 'open_water_swimming' === $type ? 'open_water' : 'pool',
			'workout_start'   => $start,
			'total_distance_m'=> isset( $activity['distance'] ) && is_numeric( $activity['distance'] ) ? (float) $activity['distance'] : 0.0,
			'elapsed_time_ms' => isset( $activity['duration'] ) && is_numeric( $activity['duration'] ) ? (int) round( (float) $activity['duration'] * 1000 ) : 0,
			'moving_time_ms'  => isset( $activity['movingDuration'] ) && is_numeric( $activity['movingDuration'] ) ? (int) round( (float) $activity['movingDuration'] * 1000 ) : 0,
		);

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-importer.php';
		$match = Importer::classify_match( $user_id, $workout );
		if ( 'exact' === $match['status'] ) {
			return 'exact';
		}
		if ( in_array( $match['status'], array( 'probable', 'disagreement' ), true ) ) {
			return 'similar';
		}
		return 'none';
	}

	public static function handle_garmin_fit_download() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) {
			wp_die( esc_html__( 'You do not have permission to download Garmin activity files.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			wp_die( esc_html__( 'Garmin activity file diagnostics are available only when WordPress debugging is enabled.', 'chriss-swim-training-progress-evaluation' ) );
		}
		check_admin_referer( 'swimlog_garmin_download_fit' );
		$activity_id = isset( $_POST['garmin_activity_id'] ) ? absint( wp_unslash( $_POST['garmin_activity_id'] ) ) : 0;
		if ( ! $activity_id ) {
			wp_die( esc_html__( 'Invalid Garmin activity file request.', 'chriss-swim-training-progress-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
		$result = Garmin_Personal_API::prepare_activity_fit( get_current_user_id(), $activity_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$fit_path = $result['tmp_name'];
		$filename = $result['filename'];
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . (string) $result['size'] );
		readfile( $fit_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Validated temporary Garmin FIT download.
		wp_delete_file( $fit_path );
		exit;
	}

	public static function garmin() {
		if ( ! current_user_can( 'swimlog_view_own_results' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage your Garmin Connect configuration.', 'chriss-swim-training-progress-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-garmin-personal-api.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		$user_id = get_current_user_id();
		$garmin_locations = Location::all_for_user( $user_id );
		$debugging = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$notice = '';
		$error = '';
		$activities = null;
		$activity_detail = null;
		$activity_full_detail = null;
		$import_preflight = null;
		$batch_results = array();
		$batch_summary = null;
		$garmin_today = wp_date( 'Y-m-d' );
		$garmin_start_date = wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( 10 * DAY_IN_SECONDS ) );
		$garmin_end_date = $garmin_today;
		$garmin_pool_swim = true;
		$garmin_open_water = false;
		$garmin_batch_location_id = 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Garmin activity detail selector.
		$detail_id = isset( $_GET['garmin_activity_id'] ) ? absint( wp_unslash( $_GET['garmin_activity_id'] ) ) : 0;
		if ( $detail_id ) {
			$activity_detail = Garmin_Personal_API::activity( $user_id, $detail_id );
			if ( is_wp_error( $activity_detail ) ) {
				$error = $activity_detail->get_error_message();
				$activity_detail = null;
			}
		}

		if ( isset( $_POST['swimlog_garmin_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['swimlog_garmin_action'] ) );

			if ( 'save_sync' === $action ) {
				check_admin_referer( 'swimlog_save_garmin_sync' );
				$enabled=isset($_POST['garmin_sync_enabled']);require_once SWIMLOG_EVALUATION_DIR.'includes/class-plugin.php';Plugin::set_garmin_sync_schedule($user_id,$enabled);
				$notice=$enabled?__('Scheduled Garmin pool-swim synchronization enabled.','chriss-swim-training-progress-evaluation'):__('Scheduled Garmin synchronization disabled.','chriss-swim-training-progress-evaluation');
			}
			if ( 'import_preflight' === $action ) {
				check_admin_referer( 'swimlog_garmin_import_preflight' );
				$requested_activity_id = isset( $_POST['garmin_activity_id'] ) ? absint( wp_unslash( $_POST['garmin_activity_id'] ) ) : 0;
				if ( ! $requested_activity_id || $requested_activity_id !== $detail_id ) {
					$error = __( 'Invalid Garmin activity import request.', 'chriss-swim-training-progress-evaluation' );
				} elseif ( ! is_array( $activity_detail ) || ! isset( $activity_detail['activityTypeDTO']['typeKey'] ) || ! in_array( (string) $activity_detail['activityTypeDTO']['typeKey'], array( 'lap_swimming', 'open_water_swimming' ), true ) ) {
					$error = __( 'Only Garmin pool-swimming and open-water-swimming activities are eligible for swim workout import.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$requested_location_id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
					if ( $requested_location_id && ! Location::get_for_user( $requested_location_id, $user_id ) ) {
						$error = __( 'Select one of your own locations.', 'chriss-swim-training-progress-evaluation' );
					} else {
						$import_preflight = Garmin_Personal_API::import_activity( $user_id, $requested_activity_id, $requested_location_id );
					}
					if ( is_wp_error( $import_preflight ) ) {
						$error = $import_preflight->get_error_message();
						$import_preflight = null;
					}
				}
			} elseif ( 'import_selected' === $action ) {
				check_admin_referer( 'swimlog_garmin_import_selected' );
				$garmin_start_date = isset( $_POST['garmin_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_start_date'] ) ) : $garmin_start_date;
				$garmin_end_date = isset( $_POST['garmin_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_end_date'] ) ) : $garmin_end_date;
				$garmin_pool_swim = isset( $_POST['garmin_pool_swim'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['garmin_pool_swim'] ) );
				$garmin_open_water = isset( $_POST['garmin_open_water'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['garmin_open_water'] ) );
				$garmin_batch_location_id = isset( $_POST['garmin_batch_location_id'] ) ? absint( wp_unslash( $_POST['garmin_batch_location_id'] ) ) : 0;
				$selected = isset( $_POST['garmin_activity_ids'] ) && is_array( $_POST['garmin_activity_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['garmin_activity_ids'] ) ) ) ) ) : array();
				if ( empty( $selected ) ) {
					$error = __( 'Select at least one Garmin activity to import.', 'chriss-swim-training-progress-evaluation' );
				} elseif ( count( $selected ) > 25 ) {
					$error = __( 'A maximum of 25 Garmin activities may be imported at one time.', 'chriss-swim-training-progress-evaluation' );
				} elseif ( $garmin_batch_location_id && ! Location::get_for_user( $garmin_batch_location_id, $user_id ) ) {
					$error = __( 'Select one of your own locations.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$counts = array( 'imported' => 0, 'already' => 0, 'pending' => 0, 'failed' => 0 );
					foreach ( $selected as $selected_id ) {
						$detail = Garmin_Personal_API::activity( $user_id, $selected_id );
						if ( is_wp_error( $detail ) || ! is_array( $detail ) || ! isset( $detail['activityTypeDTO']['typeKey'] ) || ! in_array( (string) $detail['activityTypeDTO']['typeKey'], array( 'lap_swimming', 'open_water_swimming' ), true ) ) {
							$batch_results[] = array( 'activity_id' => $selected_id, 'status' => 'failed', 'message' => is_wp_error( $detail ) ? $detail->get_error_message() : __( 'Only Garmin pool-swimming and open-water-swimming activities are eligible for swim workout import.', 'chriss-swim-training-progress-evaluation' ) );
							$counts['failed']++;
							continue;
						}
						$selected_type = (string) $detail['activityTypeDTO']['typeKey'];
						$location_id = 'open_water_swimming' === $selected_type ? $garmin_batch_location_id : 0;
						$result = Garmin_Personal_API::import_activity( $user_id, $selected_id, $location_id );
						if ( is_wp_error( $result ) ) {
							$batch_results[] = array( 'activity_id' => $selected_id, 'status' => 'failed', 'message' => $result->get_error_message() );
							$counts['failed']++;
							continue;
						}
						$status = (string) ( $result['status'] ?? '' );
						if ( 'imported' === $status ) $counts['imported']++;
						elseif ( in_array( $status, array( 'already_associated', 'associated' ), true ) ) $counts['already']++;
						elseif ( 'pending_review' === $status ) $counts['pending']++;
						else $counts['failed']++;
						$batch_results[] = array_merge( array( 'activity_id' => $selected_id, 'status' => $status ), $result );
					}
					$batch_summary = array_merge( array( 'selected' => count( $selected ) ), $counts );
					$start_ts = strtotime( $garmin_start_date . ' 00:00:00' );$end_ts = strtotime( $garmin_end_date . ' 00:00:00' );
					if ( false !== $start_ts && false !== $end_ts && $end_ts >= $start_ts && ( $end_ts - $start_ts ) <= ( 30 * DAY_IN_SECONDS ) ) {
						$list = Garmin_Personal_API::recent_activities( $user_id, 100, $garmin_start_date, $garmin_end_date, array_filter( array( $garmin_pool_swim ? 'lap_swimming' : '', $garmin_open_water ? 'open_water_swimming' : '' ) ) );
						if ( ! is_wp_error( $list ) ) $activities = $list;
					}
				}
			} elseif ( 'activity_detail' === $action && $debugging ) {
				check_admin_referer( 'swimlog_garmin_activity_detail' );
				$requested_activity_id = isset( $_POST['garmin_activity_id'] ) ? absint( wp_unslash( $_POST['garmin_activity_id'] ) ) : 0;
				if ( ! $requested_activity_id || $requested_activity_id !== $detail_id ) {
					$error = __( 'Invalid Garmin activity detail request.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$result = Garmin_Personal_API::activity( $user_id, $requested_activity_id );
					if ( is_wp_error( $result ) ) {
						$error = $result->get_error_message();
					} else {
						$activity_full_detail = $result;
					}
				}
			} elseif ( 'save' === $action ) {
				check_admin_referer( 'swimlog_save_garmin_key' );
				$api_key = isset( $_POST['garmin_api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['garmin_api_key'] ) ) ) : '';
				$result = Garmin_Personal_API::save_key( $user_id, $api_key );
				if ( is_wp_error( $result ) ) {
					$error = $result->get_error_message();
				} else {
					$notice = __( 'Garmin Connect Personal API key saved securely.', 'chriss-swim-training-progress-evaluation' );
				}
			} elseif ( 'test' === $action ) {
				check_admin_referer( 'swimlog_test_garmin_connection' );
				$result = Garmin_Personal_API::test_connection( $user_id );
				if ( is_wp_error( $result ) ) {
					$error = $result->get_error_message();
				} else {
					$notice = __( 'Garmin Connect connection successful. An access token was returned.', 'chriss-swim-training-progress-evaluation' );
				}
			} elseif ( 'activities' === $action ) {
				check_admin_referer( 'swimlog_garmin_recent_activities' );
				$garmin_start_date = isset( $_POST['garmin_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_start_date'] ) ) : '';
				$garmin_end_date = isset( $_POST['garmin_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['garmin_end_date'] ) ) : '';
				$garmin_pool_swim = isset( $_POST['garmin_pool_swim'] );
				$garmin_open_water = isset( $_POST['garmin_open_water'] );
				$start_ts = strtotime( $garmin_start_date . ' 00:00:00' );
				$end_ts = strtotime( $garmin_end_date . ' 00:00:00' );
				$valid_dates = (bool) preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $garmin_start_date ) && (bool) preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $garmin_end_date );
				if ( ! $valid_dates || false === $start_ts || false === $end_ts || $end_ts < $start_ts || ( $end_ts - $start_ts ) > ( 30 * DAY_IN_SECONDS ) ) {
					$error = __( 'Choose a valid Garmin activity date range of no more than 30 days.', 'chriss-swim-training-progress-evaluation' );
				} else {
					$result = Garmin_Personal_API::recent_activities( $user_id, 100, $garmin_start_date, $garmin_end_date, array_filter( array( $garmin_pool_swim ? 'lap_swimming' : '', $garmin_open_water ? 'open_water_swimming' : '' ) ) );
					if ( is_wp_error( $result ) ) {
						$error = $result->get_error_message();
					} else {
						$activities = $result;
					}
				}
			} elseif ( 'remove' === $action ) {
				check_admin_referer( 'swimlog_remove_garmin_key' );
				Garmin_Personal_API::remove_key( $user_id );
				$notice = __( 'Garmin Connect Personal API key removed.', 'chriss-swim-training-progress-evaluation' );
			}
		}

		$configured = Garmin_Personal_API::is_configured( $user_id );
		$sync_enabled='1'===(string)get_user_meta($user_id,'swimlog_garmin_sync_enabled',true);$sync_last=(string)get_user_meta($user_id,'swimlog_garmin_sync_last_run',true);$sync_attempt=(string)get_user_meta($user_id,'swimlog_garmin_sync_last_attempt',true);$sync_success=(string)get_user_meta($user_id,'swimlog_garmin_sync_last_success',true);$sync_error=(array)get_user_meta($user_id,'swimlog_garmin_sync_last_error',true);$sync_result=(array)get_user_meta($user_id,'swimlog_garmin_sync_last_result',true);$sync_next=wp_next_scheduled('swimlog_garmin_scheduled_sync',array($user_id));
		$activity_mode = $detail_id > 0 || is_array( $activities );
		?>
		<div class="wrap swimlog-admin"<?php echo ( $configured && ! $activity_mode ) ? ' data-swimlog-garmin-configured="1"' : ''; ?>>
			<h1><?php esc_html_e( 'Garmin Connect Personal API', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<?php if ( ! $configured ) : ?><p><?php esc_html_e( 'Configure your own Garmin Connect Personal API key. The key is encrypted before it is stored and is never displayed again after saving.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>
			<?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<?php if ( ! $activity_mode ) : ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Status', 'chriss-swim-training-progress-evaluation' ); ?></th><td><strong><?php echo $configured ? esc_html__( 'Configured', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Not configured', 'chriss-swim-training-progress-evaluation' ); ?></strong></td></tr>
			</table>

			<?php if ( $configured ) : ?><div id="swimlog-garmin-configuration-options" style="margin-top:32px;padding-top:20px;border-top:1px solid #c3c4c7"><h2 style="margin-top:0"><?php esc_html_e( 'Configuration Options — Click to Expand', 'chriss-swim-training-progress-evaluation' ); ?></h2></div><details id="swimlog-garmin-key-settings"><summary><strong><?php esc_html_e( 'Replace Personal API Key', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:12px"><?php else : ?><h2><?php esc_html_e( 'Add Personal API Key', 'chriss-swim-training-progress-evaluation' ); ?></h2><?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_garmin_key' ); ?>
				<input type="hidden" name="swimlog_garmin_action" value="save">
				<table class="form-table" role="presentation"><tr><th scope="row"><label for="swimlog-garmin-api-key"><?php esc_html_e( 'Personal API Key', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-garmin-api-key" class="regular-text" type="password" name="garmin_api_key" value="" autocomplete="new-password" required><p class="description"><?php esc_html_e( 'The key is accepted only over this POST form, encrypted for storage, and is not written back into the page.', 'chriss-swim-training-progress-evaluation' ); ?></p><details style="margin-top:12px;max-width:760px"><summary><strong><?php esc_html_e( 'How do I get a Personal API Key?', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:10px"><ol><li><?php esc_html_e( 'Sign in to your Garmin Connect account.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Open Settings.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Select Personal API Key from the menu on the left.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Create a new Personal API Key.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Copy the generated API key.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php esc_html_e( 'Return to this page and paste the value into the Personal API Key field above.', 'chriss-swim-training-progress-evaluation' ); ?></li><li><?php echo $configured ? esc_html__( 'Click Replace API Key.', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Click Save API Key.', 'chriss-swim-training-progress-evaluation' ); ?></li></ol><p><strong><?php esc_html_e( 'Keep your API key private.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <?php esc_html_e( 'Treat it like a password and do not share it. After saving, this plugin encrypts the key for storage and does not display the saved value again.', 'chriss-swim-training-progress-evaluation' ); ?></p></div></details></td></tr></table>
				<?php submit_button( $configured ? __( 'Replace API Key', 'chriss-swim-training-progress-evaluation' ) : __( 'Save API Key', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', true, array( 'id' => 'swimlog-garmin-api-key-submit', 'disabled' => 'disabled' ) ); ?>
			</form>
			<?php if ( $configured ) : ?></div></details><?php endif; ?>

			<?php endif; ?>

			<?php if ( $configured ) : ?>
	
			<?php if ( $configured && ! $activity_mode ) : ?><hr><h2><?php esc_html_e('Scheduled Synchronization','chriss-swim-training-progress-evaluation'); ?></h2><form method="post"><?php wp_nonce_field('swimlog_save_garmin_sync'); ?><input type="hidden" name="swimlog_garmin_action" value="save_sync"><p><label><input type="checkbox" name="garmin_sync_enabled" value="1" <?php checked($sync_enabled); ?>> <?php esc_html_e('Schedule Garmin checks hourly for new pool and open-water swimming activities.','chriss-swim-training-progress-evaluation'); ?></label></p><p class="description"><?php esc_html_e('The sync uses WordPress WP-Cron, so an hourly check runs when WordPress receives traffic after the scheduled time rather than at a guaranteed clock time. Each run looks back seven days. Pool swims can import automatically. Open-water swims also import automatically when the first valid GPS fix matches a saved location within 500 meters; unmatched locations remain for manual review. Duplicate activities are skipped, and ambiguous workout matches remain pending for review.','chriss-swim-training-progress-evaluation'); ?></p><?php if($sync_attempt):?><p><strong><?php esc_html_e('Last attempt:','chriss-swim-training-progress-evaluation'); ?></strong> <?php echo esc_html($sync_attempt); ?></p><?php endif;?><?php if($sync_success):?><p><strong><?php esc_html_e('Last successful sync:','chriss-swim-training-progress-evaluation'); ?></strong> <?php echo esc_html($sync_success); ?><?php if($sync_result):?> — <?php echo esc_html(sprintf(/* translators: 1: imported, 2: known, 3: pending, 4: open-water review, 5: failed. */ __('Imported %1$d; already known %2$d; pending %3$d; open water for review %4$d; failed %5$d.','chriss-swim-training-progress-evaluation'),(int)($sync_result['imported']??0),(int)($sync_result['already']??0),(int)($sync_result['pending']??0),(int)($sync_result['open_water_review']??0),(int)($sync_result['failed']??0))); ?><?php endif;?></p><?php endif;?><?php if($sync_error):?><p><strong><?php esc_html_e('Last error:','chriss-swim-training-progress-evaluation'); ?></strong> <?php echo esc_html(($sync_error['code']??'error').': '.($sync_error['message']??'')); ?></p><?php endif;?><?php if($sync_next):?><p><strong><?php esc_html_e('Next scheduled check:','chriss-swim-training-progress-evaluation'); ?></strong> <?php echo esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),$sync_next,wp_timezone())); ?></p><?php elseif($sync_enabled):?><p><strong><?php esc_html_e('Next scheduled check:','chriss-swim-training-progress-evaluation'); ?></strong> <?php esc_html_e('Not currently scheduled. Save the synchronization setting to repair the schedule.','chriss-swim-training-progress-evaluation'); ?></p><?php endif;?><?php submit_button(__('Save Synchronization Setting','chriss-swim-training-progress-evaluation'),'secondary'); ?></form><?php endif; ?>
			<?php if ( ! $activity_mode ) : ?>
				<details id="swimlog-garmin-connection"><summary><strong><?php esc_html_e( 'Test Connection', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:12px">
				<p><?php esc_html_e( 'Test the saved credential by requesting a short-lived access token from Garmin Connect. The access token is not stored by this test.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'swimlog_test_garmin_connection' ); ?>
					<input type="hidden" name="swimlog_garmin_action" value="test">
					<?php submit_button( __( 'Test Connection', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
				</form>
				</div></details>
				<?php endif; ?>

				<?php if ( is_array( $activity_detail ) ) :
					$summary = isset( $activity_detail['summaryDTO'] ) && is_array( $activity_detail['summaryDTO'] ) ? $activity_detail['summaryDTO'] : array();
					$atype = isset( $activity_detail['activityTypeDTO']['typeKey'] ) ? (string) $activity_detail['activityTypeDTO']['typeKey'] : '';
					$aname = isset( $activity_detail['activityName'] ) ? (string) $activity_detail['activityName'] : '';
					$astart = isset( $activity_detail['startTimeLocal'] ) ? (string) $activity_detail['startTimeLocal'] : '';
					$adistance = isset( $summary['distance'] ) && is_numeric( $summary['distance'] ) ? number_format_i18n( (float) $summary['distance'], 1 ) . ' m' : '—';
					$aduration = isset( $summary['duration'] ) && is_numeric( $summary['duration'] ) ? gmdate( 'H:i:s', max( 0, (int) round( (float) $summary['duration'] ) ) ) : '—';
					$acalories = isset( $summary['calories'] ) && is_numeric( $summary['calories'] ) ? number_format_i18n( (float) $summary['calories'], 0 ) : '—';
					$aavg_hr = isset( $summary['averageHR'] ) && is_numeric( $summary['averageHR'] ) ? number_format_i18n( (float) $summary['averageHR'], 0 ) . ' bpm' : '—';
					$amax_hr = isset( $summary['maxHR'] ) && is_numeric( $summary['maxHR'] ) ? number_format_i18n( (float) $summary['maxHR'], 0 ) . ' bpm' : '—';
					$detail_import_state = self::garmin_activity_import_state( $user_id, $detail_id );
				?>
					<hr><h2><?php esc_html_e( 'Garmin Activity Details', 'chriss-swim-training-progress-evaluation' ); ?></h2>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-garmin' ) ); ?>"><?php esc_html_e( 'Back to Garmin Connect', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
					<table class="widefat striped"><tbody>
						<tr><th><?php esc_html_e( 'Activity ID', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( (string) $detail_id ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $aname ?: '—' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Type', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $atype ?: '—' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Start', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $astart ?: '—' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $adistance ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Duration', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $aduration ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Calories', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $acalories ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Average HR', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $aavg_hr ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Maximum HR', 'chriss-swim-training-progress-evaluation' ); ?></th><td><?php echo esc_html( $amax_hr ); ?></td></tr>
					</tbody></table>
					<?php if ( 'imported' === $detail_import_state['status'] ) : ?>
						<p style="margin-top:16px"><strong><?php esc_html_e( 'Already imported.', 'chriss-swim-training-progress-evaluation' ); ?></strong><?php if ( $detail_import_state['workout_id'] ) : ?> <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'swimlog-workouts', 'workout_id'=>$detail_import_state['workout_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Workout', 'chriss-swim-training-progress-evaluation' ); ?></a><?php endif; ?></p>
					<?php elseif ( 'pending' === $detail_import_state['status'] ) : ?>
						<p style="margin-top:16px"><strong><?php esc_html_e( 'Review required.', 'chriss-swim-training-progress-evaluation' ); ?></strong> <a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ); ?>"><?php esc_html_e( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
					<?php else : ?>
					<form method="post" style="margin-top:16px">
						<?php wp_nonce_field( 'swimlog_garmin_import_preflight' ); ?>
						<input type="hidden" name="swimlog_garmin_action" value="import_preflight">
						<input type="hidden" name="garmin_activity_id" value="<?php echo esc_attr( (string) $detail_id ); ?>">
						<?php if ( 'open_water_swimming' === $atype ) : ?>
							<p><label for="swimlog-garmin-location"><strong><?php esc_html_e( 'Open Water Location', 'chriss-swim-training-progress-evaluation' ); ?></strong></label><br>
							<select id="swimlog-garmin-location" name="location_id"><option value="0"><?php esc_html_e( 'No location selected', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( $garmin_locations as $garmin_location ) : ?><option value="<?php echo esc_attr( $garmin_location->id ); ?>"><?php echo esc_html( $garmin_location->name ); ?></option><?php endforeach; ?></select></p>
							<p class="description"><?php esc_html_e( 'Assign the saved swim location while importing. GPS track and distance data remain unchanged.', 'chriss-swim-training-progress-evaluation' ); ?></p>
						<?php endif; ?>
						<?php submit_button( __( 'Import Activity', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', false ); ?>
					</form>
					<?php endif; ?>
					<?php if ( is_array( $import_preflight ) ) : ?>
						<?php if ( 'already_associated' === $import_preflight['status'] ) : ?>
							<div class="notice notice-error inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: existing import ID, 3: existing workout ID. */
									__( 'Activity Already Imported: Preflight stopped safely: Garmin activity %1$d is already associated with import #%2$d and workout #%3$d. No data was changed.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['import_id'],
									(int) $import_preflight['workout_id']
								) );
							?></p></div>
						<?php elseif ( 'associated' === $import_preflight['status'] ) : ?>
							<div class="notice notice-error inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: existing import ID, 3: existing workout ID. */
									__( 'Activity Already Imported: Garmin activity %1$d was safely associated with existing import #%2$d / workout #%3$d after an exact FIT SHA-256 match. No workout data was changed.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['import_id'],
									(int) $import_preflight['workout_id']
								) );
							?></p></div>
						<?php elseif ( 'file_exists' === $import_preflight['status'] ) : ?>
							<div class="notice notice-error inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin filename, 2: existing import ID, 3: existing workout ID. */
									__( 'Activity Already Imported: Preflight stopped safely: %1$s has the same SHA-256 fingerprint as existing import #%2$d / workout #%3$d. No association or data change was made.', 'chriss-swim-training-progress-evaluation' ),
									(string) $import_preflight['filename'],
									(int) $import_preflight['existing_import_id'],
									(int) $import_preflight['existing_workout_id']
								) );
							?></p></div>
						<?php elseif ( 'imported' === $import_preflight['status'] ) : ?>
							<div class="notice notice-success inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: imported workout ID. */
									__( 'Activity Imported: Garmin activity %1$d was successfully imported as workout #%2$d.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['workout_id']
								) );
							?> <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => (int) $import_preflight['workout_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Activity', 'chriss-swim-training-progress-evaluation' ); ?></a></p></div>
						<?php elseif ( 'pending_review' === $import_preflight['status'] ) : ?>
							<div class="notice notice-warning inline"><p><?php
								echo esc_html( sprintf(
									/* translators: 1: Garmin activity ID, 2: pending import ID, 3: candidate workout ID. */
									__( 'Import Review Required: Garmin activity %1$d was preserved as pending import #%2$d because it may match workout #%3$d. No automatic attachment or duplicate workout was created.', 'chriss-swim-training-progress-evaluation' ),
									(int) $import_preflight['activity_id'],
									(int) $import_preflight['import_id'],
									(int) $import_preflight['candidate_workout_id']
								) );
							?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ); ?>"><?php esc_html_e( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ); ?></a></p></div>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $debugging ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
						<?php wp_nonce_field( 'swimlog_garmin_download_fit' ); ?>
						<input type="hidden" name="action" value="swimlog_garmin_download_fit">
						<input type="hidden" name="garmin_activity_id" value="<?php echo esc_attr( (string) $detail_id ); ?>">
						<?php submit_button( __( 'Download Garmin FIT', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" style="margin-top:16px">
						<?php wp_nonce_field( 'swimlog_garmin_activity_detail' ); ?>
						<input type="hidden" name="swimlog_garmin_action" value="activity_detail">
						<input type="hidden" name="garmin_activity_id" value="<?php echo esc_attr( (string) $detail_id ); ?>">
						<?php submit_button( __( 'Get Activity Detail', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
					</form>
					<?php endif; ?>
					<?php if ( $debugging && is_array( $activity_full_detail ) ) :
						$detail_sections = array(
							'metadataDTO' => __( 'Metadata', 'chriss-swim-training-progress-evaluation' ),
							'summaryDTO' => __( 'Summary / Measurements', 'chriss-swim-training-progress-evaluation' ),
							'splitSummaries' => __( 'Split Summaries', 'chriss-swim-training-progress-evaluation' ),
							'activityWorkout' => __( 'Associated Workout', 'chriss-swim-training-progress-evaluation' ),
						);
					?>
						<h3><?php esc_html_e( 'Activity Detail Structure', 'chriss-swim-training-progress-evaluation' ); ?></h3>
						<p><?php esc_html_e( 'This diagnostic view shows which documented detail sections Garmin returned for this activity. Nothing is imported or stored.', 'chriss-swim-training-progress-evaluation' ); ?></p>
						<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Section', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Returned', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Fields / Items', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
						<?php foreach ( $detail_sections as $detail_key => $detail_label ) :
							$detail_value = $activity_full_detail[ $detail_key ] ?? null;
							$returned = null !== $detail_value && array() !== $detail_value && '' !== $detail_value;
							$count = is_array( $detail_value ) ? count( $detail_value ) : ( $returned ? 1 : 0 );
						?>
							<tr><th><?php echo esc_html( $detail_label ); ?></th><td><?php echo $returned ? esc_html__( 'Yes', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'No', 'chriss-swim-training-progress-evaluation' ); ?></td><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
						<?php foreach ( array( 'metadataDTO' => __( 'Metadata Fields', 'chriss-swim-training-progress-evaluation' ), 'summaryDTO' => __( 'Summary / Measurement Fields', 'chriss-swim-training-progress-evaluation' ) ) as $field_section_key => $field_section_label ) :
							$field_section = isset( $activity_full_detail[ $field_section_key ] ) && is_array( $activity_full_detail[ $field_section_key ] ) ? $activity_full_detail[ $field_section_key ] : array();
							if ( empty( $field_section ) ) {
								continue;
							}
						?>
							<h4><?php echo esc_html( $field_section_label ); ?></h4>
							<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Field', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Value', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
							<?php foreach ( $field_section as $field_name => $field_value ) :
								if ( is_array( $field_value ) ) {
									$display_value = sprintf(
										/* translators: %d: number of nested items. */
										__( '[nested structure: %d items]', 'chriss-swim-training-progress-evaluation' ),
										count( $field_value )
									);
								} elseif ( is_bool( $field_value ) ) {
									$display_value = $field_value ? __( 'true', 'chriss-swim-training-progress-evaluation' ) : __( 'false', 'chriss-swim-training-progress-evaluation' );
								} elseif ( null === $field_value || '' === $field_value ) {
									$display_value = '—';
								} elseif ( is_scalar( $field_value ) ) {
									$display_value = (string) $field_value;
								} else {
									$display_value = __( '[non-scalar value]', 'chriss-swim-training-progress-evaluation' );
								}
							?>
								<tr><th><code><?php echo esc_html( (string) $field_name ); ?></code></th><td><?php echo esc_html( $display_value ); ?></td></tr>
							<?php endforeach; ?>
							</tbody></table>
						<?php endforeach; ?>
						<h4><?php esc_html_e( 'Top-level Garmin fields returned', 'chriss-swim-training-progress-evaluation' ); ?></h4>
						<p><code><?php echo esc_html( implode( ', ', array_keys( $activity_full_detail ) ) ); ?></code></p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! $activity_mode ) { require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-garmin-measurements.php'; Admin_Garmin_Measurements::render(); } ?>
				<div id="swimlog-garmin-activities"><h2><?php esc_html_e( 'Garmin Activities', 'chriss-swim-training-progress-evaluation' ); ?></h2>
				<p><?php esc_html_e( 'Retrieve Garmin activities for a date range of up to 30 days. This inspection does not import or change swim training data.', 'chriss-swim-training-progress-evaluation' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'swimlog_garmin_recent_activities' ); ?>
					<input type="hidden" name="swimlog_garmin_action" value="activities">
					<p><label for="swimlog-garmin-start-date"><strong><?php esc_html_e( 'Start date', 'chriss-swim-training-progress-evaluation' ); ?></strong></label><br><input id="swimlog-garmin-start-date" type="date" name="garmin_start_date" value="<?php echo esc_attr( $garmin_start_date ); ?>" required></p>
					<p><label for="swimlog-garmin-end-date"><strong><?php esc_html_e( 'End date', 'chriss-swim-training-progress-evaluation' ); ?></strong></label><br><input id="swimlog-garmin-end-date" type="date" name="garmin_end_date" value="<?php echo esc_attr( $garmin_end_date ); ?>" max="<?php echo esc_attr( $garmin_today ); ?>" required></p>
					<fieldset><legend><strong><?php esc_html_e( 'Activity types', 'chriss-swim-training-progress-evaluation' ); ?></strong></legend>
						<label><input type="checkbox" name="garmin_pool_swim" value="1" <?php checked( $garmin_pool_swim ); ?>> <?php esc_html_e( 'Pool Swim', 'chriss-swim-training-progress-evaluation' ); ?></label><br>
						<label><input type="checkbox" name="garmin_open_water" value="1" <?php checked( $garmin_open_water ); ?>> <?php esc_html_e( 'Open Water', 'chriss-swim-training-progress-evaluation' ); ?></label>
					</fieldset>
					<?php submit_button( __( 'Retrieve Activities', 'chriss-swim-training-progress-evaluation' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( is_array( $activities ) ) : ?>
					<?php if ( $batch_summary ) : ?>
						<div class="notice notice-info inline"><p><?php echo esc_html( sprintf(
							/* translators: 1: selected count, 2: imported, 3: already imported, 4: pending review, 5: failed. */
							__( '%1$d selected — %2$d imported, %3$d already imported, %4$d require review, %5$d failed.', 'chriss-swim-training-progress-evaluation' ),
							(int) $batch_summary['selected'], (int) $batch_summary['imported'], (int) $batch_summary['already'], (int) $batch_summary['pending'], (int) $batch_summary['failed']
						) ); ?></p></div>
						<table class="widefat striped" style="margin-bottom:16px"><thead><tr><th><?php esc_html_e( 'Activity ID', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Result', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Action', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
						<?php foreach ( $batch_results as $batch_result ) : $batch_status = (string) ( $batch_result['status'] ?? 'failed' ); ?>
							<tr><td><?php echo esc_html( (string) $batch_result['activity_id'] ); ?></td><td><?php
								if ( 'imported' === $batch_status ) esc_html_e( 'Imported', 'chriss-swim-training-progress-evaluation' );
								elseif ( in_array( $batch_status, array( 'already_associated', 'associated' ), true ) ) esc_html_e( 'Already imported', 'chriss-swim-training-progress-evaluation' );
								elseif ( 'pending_review' === $batch_status ) esc_html_e( 'Review required', 'chriss-swim-training-progress-evaluation' );
								else echo esc_html( (string) ( $batch_result['message'] ?? __( 'Import failed.', 'chriss-swim-training-progress-evaluation' ) ) );
							?></td><td>
							<?php if ( 'imported' === $batch_status && ! empty( $batch_result['workout_id'] ) ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-workouts', 'workout_id' => (int) $batch_result['workout_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Activity', 'chriss-swim-training-progress-evaluation' ); ?></a>
							<?php elseif ( 'pending_review' === $batch_status ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ); ?>"><?php esc_html_e( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ); ?></a>
							<?php else : ?>—<?php endif; ?></td></tr>
						<?php endforeach; ?></tbody></table>
					<?php endif; ?>
					<?php if ( empty( $activities ) ) : ?>
						<p><?php esc_html_e( 'Garmin returned no activities.', 'chriss-swim-training-progress-evaluation' ); ?></p>
					<?php else : ?>
						<form method="post" id="swimlog-garmin-batch-form">
							<?php wp_nonce_field( 'swimlog_garmin_import_selected' ); ?>
							<input type="hidden" name="swimlog_garmin_action" value="import_selected">
							<input type="hidden" name="garmin_start_date" value="<?php echo esc_attr( $garmin_start_date ); ?>">
							<input type="hidden" name="garmin_end_date" value="<?php echo esc_attr( $garmin_end_date ); ?>">
							<input type="hidden" name="garmin_pool_swim" value="<?php echo $garmin_pool_swim ? '1' : '0'; ?>">
							<input type="hidden" name="garmin_open_water" value="<?php echo $garmin_open_water ? '1' : '0'; ?>">
							<p><label for="swimlog-garmin-batch-location"><strong><?php esc_html_e( 'Open Water Location', 'chriss-swim-training-progress-evaluation' ); ?></strong></label> <select id="swimlog-garmin-batch-location" name="garmin_batch_location_id"><option value="0"><?php esc_html_e( 'No location selected', 'chriss-swim-training-progress-evaluation' ); ?></option><?php foreach ( $garmin_locations as $location ) : ?><option value="<?php echo esc_attr( $location->id ); ?>" <?php selected( $garmin_batch_location_id, (int) $location->id ); ?>><?php echo esc_html( $location->name ); ?></option><?php endforeach; ?></select> <span class="description"><?php esc_html_e( 'Applied only to selected open-water activities. Pool imports ignore this setting.', 'chriss-swim-training-progress-evaluation' ); ?></span></p>
							<p><?php submit_button( __( 'Import Selected', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit', false, array( 'id' => 'swimlog-garmin-import-selected', 'data-swimlog-garmin-import-selected' => '1', 'disabled' => 'disabled' ) ); ?> <span class="description"><?php esc_html_e( 'Select up to 25 activities.', 'chriss-swim-training-progress-evaluation' ); ?></span></p>
							<div id="swimlog-garmin-import-progress" hidden aria-live="polite"><span class="spinner" aria-hidden="true"></span><strong class="swimlog-garmin-import-progress-text"></strong></div>
							<table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Select', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Activity ID', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Date', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Type', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Duration', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Import Status', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
							<?php foreach ( $activities as $activity ) :
								$activity_id = isset( $activity['activityId'] ) ? (string) $activity['activityId'] : '';
								$date = isset( $activity['startTimeLocal'] ) ? (string) $activity['startTimeLocal'] : '';
								$name = isset( $activity['activityName'] ) ? (string) $activity['activityName'] : '';
								$type = isset( $activity['activityType']['typeKey'] ) ? (string) $activity['activityType']['typeKey'] : '';
								$distance = isset( $activity['distance'] ) && is_numeric( $activity['distance'] ) ? number_format_i18n( (float) $activity['distance'], 1 ) . ' m' : '—';
								$duration = isset( $activity['duration'] ) && is_numeric( $activity['duration'] ) ? gmdate( 'H:i:s', max( 0, (int) round( (float) $activity['duration'] ) ) ) : '—';
								$match_status = self::garmin_activity_match_status( $user_id, $activity );
								$import_state = self::garmin_activity_import_state( $user_id, $activity_id );
							?>
							<?php
								$row_state = in_array( $import_state['status'], array( 'imported', 'pending', 'recorded' ), true )
									? $import_state['status']
									: ( 'exact' === $match_status ? 'exact-match' : ( 'similar' === $match_status ? 'possible-match' : 'new' ) );
							?>
							<tr class="<?php echo esc_attr( 'swimlog-garmin-state-' . $row_state ); ?>"><td><input class="swimlog-garmin-select" type="checkbox" name="garmin_activity_ids[]" value="<?php echo esc_attr( $activity_id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Garmin activity ID. */ __( 'Select Garmin activity %s', 'chriss-swim-training-progress-evaluation' ), $activity_id ) ); ?>" <?php disabled( '' === $activity_id || in_array( $import_state['status'], array( 'imported', 'pending' ), true ) ); ?>></td><td><?php if ( $activity_id ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-garmin', 'garmin_activity_id' => $activity_id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $activity_id ); ?></a><?php else : ?>—<?php endif; ?></td><td><?php echo esc_html( $date ?: '—' ); ?></td><td><?php echo esc_html( $name ?: '—' ); ?></td><td><?php echo esc_html( $type ?: '—' ); ?></td><td><?php echo esc_html( $distance ); ?></td><td><?php echo esc_html( $duration ); ?></td><td><?php
								if ( 'imported' === $import_state['status'] ) {
									esc_html_e( 'Imported', 'chriss-swim-training-progress-evaluation' );
									if ( $import_state['workout_id'] ) echo ' — <a href="' . esc_url( add_query_arg( array( 'page'=>'swimlog-workouts', 'workout_id'=>$import_state['workout_id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'View Workout', 'chriss-swim-training-progress-evaluation' ) . '</a>';
								} elseif ( 'pending' === $import_state['status'] ) {
									echo esc_html__( 'Review required', 'chriss-swim-training-progress-evaluation' ) . ' — <a href="' . esc_url( admin_url( 'admin.php?page=swimlog-upload' ) ) . '">' . esc_html__( 'Review Pending Import', 'chriss-swim-training-progress-evaluation' ) . '</a>';
								} elseif ( 'recorded' === $import_state['status'] ) {
									esc_html_e( 'Import recorded', 'chriss-swim-training-progress-evaluation' );
								} elseif ( 'exact' === $match_status ) {
									esc_html_e( 'Exact match — not yet associated', 'chriss-swim-training-progress-evaluation' );
								} elseif ( 'similar' === $match_status ) {
									esc_html_e( 'Possible match — not yet associated', 'chriss-swim-training-progress-evaluation' );
								} else {
									esc_html_e( 'New — not imported', 'chriss-swim-training-progress-evaluation' );
								}
							?></td></tr>
							<?php endforeach; ?></tbody></table>
							<p><?php submit_button( __( 'Import Selected', 'chriss-swim-training-progress-evaluation' ), 'primary', 'submit_bottom', false, array( 'data-swimlog-garmin-import-selected' => '1', 'disabled' => 'disabled' ) ); ?></p>
						</form>
					<?php endif; ?>
				<?php endif; ?>

				</div>
				<?php if ( ! $activity_mode ) : ?>
				<details id="swimlog-garmin-remove"><summary><strong><?php esc_html_e( 'Remove Garmin Connect Configuration', 'chriss-swim-training-progress-evaluation' ); ?></strong></summary><div style="margin-top:12px">
				<form method="post">
					<?php wp_nonce_field( 'swimlog_remove_garmin_key' ); ?>
					<input type="hidden" name="swimlog_garmin_action" value="remove">
					<?php submit_button( __( 'Remove API Key', 'chriss-swim-training-progress-evaluation' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Remove your stored Garmin Connect Personal API key?', 'chriss-swim-training-progress-evaluation' ) ) . "');" ) ); ?>
				</form>
				</div></details>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

}

