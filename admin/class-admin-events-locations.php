<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Event and location administration UI and actions.
 *
 * Extracted from Admin while preserving the existing Admin callback surface.
 */
final class Admin_Events_Locations {
	private static $location_action_error = null;
	private static $event_action_error = null;

	public static function handle_location_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_locations' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-locations' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_location_action'] ) ) return;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';

		$user_id = get_current_user_id();
		$action = sanitize_key( wp_unslash( $_POST['swimlog_location_action'] ) );

		if ( 'save' === $action ) {
			check_admin_referer( 'swimlog_save_location' );
			$id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
			$result = Location::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) {
				self::$location_action_error = $result->get_error_message();
				return;
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'swimlog_delete_location' );
			$id = isset( $_POST['location_id'] ) ? absint( wp_unslash( $_POST['location_id'] ) ) : 0;
			$result = Location::delete( $id, $user_id );
			if ( is_wp_error( $result ) || ! $result ) {
				self::$location_action_error = is_wp_error( $result ) ? $result->get_error_message() : __( 'The location could not be deleted.', 'chriss-swim-training-progress-evaluation' );
				return;
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}


	public static function handle_event_actions() {
		if ( ! is_admin() || ! current_user_can( 'swimlog_manage_own_events' ) ) return;
		if ( empty( $_GET['page'] ) || 'swimlog-events' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		if ( empty( $_POST['swimlog_event_action'] ) ) return;

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';

		$user_id = get_current_user_id();
		$action = sanitize_key( wp_unslash( $_POST['swimlog_event_action'] ) );

		if ( 'save' === $action ) {
			check_admin_referer( 'swimlog_save_event' );
			$id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
			$result = Event::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) {
				self::$event_action_error = $result->get_error_message();
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','saved'=>1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'swimlog_delete_event' );
			$result = Event::delete( absint( wp_unslash( $_POST['event_id'] ?? 0 ) ), $user_id );
			if ( is_wp_error( $result ) || ! $result ) {
				self::$event_action_error = is_wp_error( $result ) ? $result->get_error_message() : __( 'The event could not be deleted.', 'chriss-swim-training-progress-evaluation' );
				return;
			}
			wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','deleted'=>1 ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}


	public static function events() {
		if ( ! current_user_can( 'swimlog_manage_own_events' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';

		$user_id = get_current_user_id();
		$error = self::$event_action_error;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only event list filter.
		$view=sanitize_key(wp_unslash($_GET['view'] ?? 'upcoming'));
		if(!in_array($view,array('upcoming','past','all'),true)) $view='upcoming';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only event edit selector.
		$edit_id=absint(wp_unslash($_GET['edit'] ?? 0));
		$editing=$edit_id?Event::get_for_user($edit_id,$user_id):null;
		$events=Event::all_for_user($user_id,$view);
		$locations=Location::all_for_user($user_id);
		$strokes=array('FR'=>__('Freestyle','chriss-swim-training-progress-evaluation'),'BR'=>__('Breaststroke','chriss-swim-training-progress-evaluation'),'BACK'=>__('Backstroke','chriss-swim-training-progress-evaluation'),'FLY'=>__('Butterfly','chriss-swim-training-progress-evaluation'),'MIXED'=>__('Mixed','chriss-swim-training-progress-evaluation'));
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e('Events','chriss-swim-training-progress-evaluation'); ?></h1>
			<p><?php esc_html_e('Manage upcoming and historical swim events. Past events remain in your history.','chriss-swim-training-progress-evaluation'); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if(isset($_GET['saved'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event saved.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if(isset($_GET['deleted'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event deleted.','chriss-swim-training-progress-evaluation'); ?></p></div><?php endif; ?>
			<?php if($error):?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

			<h2><?php echo $editing?esc_html__('Edit Event','chriss-swim-training-progress-evaluation'):esc_html__('Add Event','chriss-swim-training-progress-evaluation'); ?></h2>
			<form method="post"><?php wp_nonce_field('swimlog_save_event'); ?><input type="hidden" name="swimlog_event_action" value="save"><input type="hidden" name="event_id" value="<?php echo esc_attr($editing?$editing->id:0); ?>">
			<table class="form-table" role="presentation">
			<tr><th><label for="swimlog-event-name"><?php esc_html_e('Event name','chriss-swim-training-progress-evaluation'); ?></label></th><td><input required class="regular-text" maxlength="191" id="swimlog-event-name" name="event_name" value="<?php echo esc_attr($editing?$editing->event_name:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-date"><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></label></th><td><input required type="date" id="swimlog-event-date" name="event_date" value="<?php echo esc_attr($editing?$editing->event_date:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-time"><?php esc_html_e('Time','chriss-swim-training-progress-evaluation'); ?></label></th><td><input type="time" id="swimlog-event-time" name="event_time" value="<?php echo esc_attr($editing&&$editing->event_time?substr($editing->event_time,0,5):''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-location"><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></label></th><td><select id="swimlog-event-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','chriss-swim-training-progress-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected($editing?$editing->location_id:0,$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-distance"><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></label></th><td><select required id="swimlog-event-distance" name="distance_value"><?php foreach(Event::DISTANCES as $d):?><option value="<?php echo esc_attr($d); ?>" <?php selected($editing?$editing->distance_value:0,$d); ?>><?php echo esc_html($d); ?></option><?php endforeach; ?></select> <select required name="course_unit"><option value="m" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'m'); ?>><?php esc_html_e('Meters','chriss-swim-training-progress-evaluation'); ?></option><option value="yd" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'yd'); ?>><?php esc_html_e('Yards','chriss-swim-training-progress-evaluation'); ?></option></select></td></tr>
			<tr><th><label for="swimlog-event-stroke"><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></label></th><td><select required id="swimlog-event-stroke" name="stroke"><?php foreach($strokes as $code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($editing?$editing->stroke:'FR',$code); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-notes"><?php esc_html_e('Notes','chriss-swim-training-progress-evaluation'); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-event-notes" name="notes"><?php echo esc_textarea($editing?$editing->notes:''); ?></textarea></td></tr>
			</table><?php submit_button($editing?__('Update Event','chriss-swim-training-progress-evaluation'):__('Add Event','chriss-swim-training-progress-evaluation')); ?><?php if($editing):?> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Cancel','chriss-swim-training-progress-evaluation'); ?></a><?php endif; ?></form>

			<h2><?php esc_html_e('Your Events','chriss-swim-training-progress-evaluation'); ?></h2>
			<p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=upcoming')); ?>"><?php esc_html_e('Upcoming','chriss-swim-training-progress-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=past')); ?>"><?php esc_html_e('Past','chriss-swim-training-progress-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=all')); ?>"><?php esc_html_e('All','chriss-swim-training-progress-evaluation'); ?></a></p>
			<?php if(empty($events)):?><p><?php esc_html_e('No events found for this view.','chriss-swim-training-progress-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Event','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Distance','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Stroke','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Location','chriss-swim-training-progress-evaluation'); ?></th><th><?php esc_html_e('Actions','chriss-swim-training-progress-evaluation'); ?></th></tr></thead><tbody>
			<?php foreach($events as $event): $locname='—'; foreach($locations as $loc){if((int)$loc->id===(int)$event->location_id){$locname=$loc->name;break;}} ?><tr><td><?php echo esc_html(wp_date('F j, Y',(new \DateTimeImmutable($event->event_date.' 12:00:00',wp_timezone()))->getTimestamp(),wp_timezone())); ?><?php if($event->event_time) echo '<br>'.esc_html(wp_date( get_option( 'time_format', 'g:i a' ), ( new \DateTimeImmutable( $event->event_date . ' ' . $event->event_time, wp_timezone() ) )->getTimestamp(), wp_timezone() )); ?></td><td><?php echo esc_html($event->event_name); ?></td><td><?php echo esc_html($event->distance_value.' '.$event->course_unit); ?></td><td><?php echo esc_html($strokes[$event->stroke]??$event->stroke); ?></td><td><?php echo esc_html($locname); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-events','view'=>$view,'edit'=>$event->id),admin_url('admin.php'))); ?>"><?php esc_html_e('Edit','chriss-swim-training-progress-evaluation'); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field('swimlog_delete_event'); ?><input type="hidden" name="swimlog_event_action" value="delete"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>"><button class="button button-small" onclick="return confirm('<?php echo esc_js(__('Delete this event?','chriss-swim-training-progress-evaluation')); ?>');"><?php esc_html_e('Delete','chriss-swim-training-progress-evaluation'); ?></button></form></td></tr><?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div><?php
	}

	public static function locations() {
		if ( ! current_user_can( 'swimlog_manage_own_locations' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this swim training page.', 'chriss-swim-training-progress-evaluation' ) );
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';

		$user_id = get_current_user_id();
		$error = self::$location_action_error;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only location edit selector.
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$editing = $edit_id ? Location::get_for_user( $edit_id, $user_id ) : null;
		$locations = Location::all_for_user( $user_id );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Locations', 'chriss-swim-training-progress-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'Manage your pool and swim locations. Editing a location does not change the pool information stored with historical workouts.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location saved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag. ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location deleted. Historical workout snapshots were preserved.', 'chriss-swim-training-progress-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Location', 'chriss-swim-training-progress-evaluation' ) : esc_html__( 'Add Location', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_location' ); ?>
				<input type="hidden" name="swimlog_location_action" value="save">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="swimlog-location-name"><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input class="regular-text" required id="swimlog-location-name" name="name" type="text" maxlength="191" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="swimlog-pool-length"><?php esc_html_e( 'Pool length', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><input id="swimlog-pool-length" name="pool_length" type="number" min="1" step="1" value="<?php echo esc_attr( $editing && null !== $editing->pool_length ? (string) (int) round( (float) $editing->pool_length ) : '' ); ?>"> <select name="pool_unit" aria-label="<?php esc_attr_e( 'Pool length unit', 'chriss-swim-training-progress-evaluation' ); ?>"><option value=""><?php esc_html_e( 'Select unit', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="m" <?php selected( $editing ? $editing->pool_unit : '', 'm' ); ?>><?php esc_html_e( 'Meters', 'chriss-swim-training-progress-evaluation' ); ?></option><option value="yd" <?php selected( $editing ? $editing->pool_unit : '', 'yd' ); ?>><?php esc_html_e( 'Yards', 'chriss-swim-training-progress-evaluation' ); ?></option></select></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Open-water reference coordinates', 'chriss-swim-training-progress-evaluation' ); ?></th><td><label for="swimlog-location-latitude"><?php esc_html_e( 'Latitude', 'chriss-swim-training-progress-evaluation' ); ?></label> <input id="swimlog-location-latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="<?php echo esc_attr( $editing && null !== $editing->latitude ? $editing->latitude : '' ); ?>"> <label for="swimlog-location-longitude"><?php esc_html_e( 'Longitude', 'chriss-swim-training-progress-evaluation' ); ?></label> <input id="swimlog-location-longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="<?php echo esc_attr( $editing && null !== $editing->longitude ? $editing->longitude : '' ); ?>"><p class="description"><?php esc_html_e( 'Optional. Open-water imports whose first valid GPS fix is within 500 meters can be assigned to this saved location automatically.', 'chriss-swim-training-progress-evaluation' ); ?></p></td></tr>
					<tr><th scope="row"><label for="swimlog-location-notes"><?php esc_html_e( 'Notes', 'chriss-swim-training-progress-evaluation' ); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-location-notes" name="notes"><?php echo esc_textarea( $editing ? $editing->notes : '' ); ?></textarea></td></tr>
				</table>
				<?php submit_button( $editing ? __( 'Update Location', 'chriss-swim-training-progress-evaluation' ) : __( 'Add Location', 'chriss-swim-training-progress-evaluation' ) ); ?>
				<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-locations' ) ); ?>"><?php esc_html_e( 'Cancel', 'chriss-swim-training-progress-evaluation' ); ?></a><?php endif; ?>
			</form>

			<h2><?php esc_html_e( 'Your Locations', 'chriss-swim-training-progress-evaluation' ); ?></h2>
			<?php if ( empty( $locations ) ) : ?>
				<p><?php esc_html_e( 'No locations have been added yet.', 'chriss-swim-training-progress-evaluation' ); ?></p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Pool', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'OWS GPS', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Notes', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Actions', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $locations as $location ) : ?>
					<tr><td><?php echo esc_html( $location->name ); ?></td><td><?php echo null !== $location->pool_length ? esc_html( number_format_i18n( (float) $location->pool_length, 0 ) . ' ' . $location->pool_unit ) : '&mdash;'; ?></td><td><?php echo null !== $location->latitude && null !== $location->longitude ? esc_html( $location->latitude . ', ' . $location->longitude ) : '&mdash;'; ?></td><td><?php echo esc_html( $location->notes ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-locations', 'edit' => $location->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'chriss-swim-training-progress-evaluation' ); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_delete_location' ); ?><input type="hidden" name="swimlog_location_action" value="delete"><input type="hidden" name="location_id" value="<?php echo esc_attr( $location->id ); ?>"><button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete this location? Historical workout snapshots will remain unchanged.', 'chriss-swim-training-progress-evaluation' ) ); ?>');"><?php esc_html_e( 'Delete', 'chriss-swim-training-progress-evaluation' ); ?></button></form></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}
}
