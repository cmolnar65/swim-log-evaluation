<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers the Swim Log wp-admin shell and capability-gated pages. */
final class Admin {
	public function register_menu() {
		add_menu_page(
			__( 'Swim Log', 'swim-log-evaluation' ),
			__( 'Swim Log', 'swim-log-evaluation' ),
			'swimlog_view_own_results',
			'swimlog-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-chart-line',
			56
		);

		$this->submenu( 'swimlog-dashboard', __( 'Dashboard', 'swim-log-evaluation' ), 'swimlog_view_own_results', 'swimlog-dashboard', 'dashboard' );
		$this->submenu( 'swimlog-dashboard', __( 'Workouts', 'swim-log-evaluation' ), 'swimlog_manage_own_workouts', 'swimlog-workouts', 'workouts' );
		$this->submenu( 'swimlog-dashboard', __( 'Upload Workout', 'swim-log-evaluation' ), 'swimlog_upload_workouts', 'swimlog-upload', 'upload' );
		$this->submenu( 'swimlog-dashboard', __( 'Personal Bests', 'swim-log-evaluation' ), 'swimlog_view_own_results', 'swimlog-personal-bests', 'personal_bests' );
		$this->submenu( 'swimlog-dashboard', __( 'Events', 'swim-log-evaluation' ), 'swimlog_manage_own_events', 'swimlog-events', 'events' );
		$this->submenu( 'swimlog-dashboard', __( 'Locations', 'swim-log-evaluation' ), 'swimlog_manage_own_locations', 'swimlog-locations', 'locations' );

		if ( current_user_can( 'swimlog_manage_settings' ) ) {
			$this->submenu( 'swimlog-dashboard', __( 'Shortcodes', 'swim-log-evaluation' ), 'swimlog_manage_settings', 'swimlog-shortcodes', 'shortcodes' );
			$this->submenu( 'swimlog-dashboard', __( 'Settings', 'swim-log-evaluation' ), 'swimlog_manage_settings', 'swimlog-settings', 'settings' );
		}
	}

	private function submenu( $parent, $title, $capability, $slug, $method ) {
		add_submenu_page( $parent, $title, $title, $capability, $slug, array( $this, $method ) );
	}

	private function page( $title, $description, $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Swim Log page.', 'swim-log-evaluation' ) );
		}
		?>
		<div class="wrap swimlog-admin">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	public function dashboard() {
		$this->page( __( 'Swim Log Dashboard', 'swim-log-evaluation' ), __( 'Your swim summary, recent workouts, personal bests, and upcoming events will appear here.', 'swim-log-evaluation' ), 'swimlog_view_own_results' );
	}
	public function workouts() {
		$this->page( __( 'Workouts', 'swim-log-evaluation' ), __( 'Your imported and normalized workout history will appear here.', 'swim-log-evaluation' ), 'swimlog_manage_own_workouts' );
	}
	public function upload() {
		$this->page( __( 'Upload Workout', 'swim-log-evaluation' ), __( 'Upload FIT and CSV swim workout files here.', 'swim-log-evaluation' ), 'swimlog_upload_workouts' );
	}
	public function personal_bests() {
		$this->page( __( 'Personal Bests', 'swim-log-evaluation' ), __( 'Your native meter and yard personal bests will appear here.', 'swim-log-evaluation' ), 'swimlog_view_own_results' );
	}
	public function events() {
		$this->page( __( 'Events', 'swim-log-evaluation' ), __( 'Manage your upcoming and historical swim events here.', 'swim-log-evaluation' ), 'swimlog_manage_own_events' );
	}
	public function locations() {
		if ( ! current_user_can( 'swimlog_manage_own_locations' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Swim Log page.', 'swim-log-evaluation' ) );
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';

		$user_id = get_current_user_id();
		$error = null;

		if ( isset( $_POST['swimlog_location_action'] ) && 'save' === $_POST['swimlog_location_action'] ) {
			check_admin_referer( 'swimlog_save_location' );
			$id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
			$result = Location::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
			} else {
				wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		if ( isset( $_POST['swimlog_location_action'] ) && 'delete' === $_POST['swimlog_location_action'] ) {
			check_admin_referer( 'swimlog_delete_location' );
			$id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
			$result = Location::delete( $id, $user_id );
			if ( is_wp_error( $result ) || ! $result ) {
				$error = is_wp_error( $result ) ? $result->get_error_message() : __( 'The location could not be deleted.', 'swim-log-evaluation' );
			} else {
				wp_safe_redirect( add_query_arg( array( 'page' => 'swimlog-locations', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing = $edit_id ? Location::get_for_user( $edit_id, $user_id ) : null;
		$locations = Location::all_for_user( $user_id );
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e( 'Locations', 'swim-log-evaluation' ); ?></h1>
			<p><?php esc_html_e( 'Manage your pool and swim locations. Editing a location does not change the pool information stored with historical workouts.', 'swim-log-evaluation' ); ?></p>
			<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location saved.', 'swim-log-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Location deleted. Historical workout snapshots were preserved.', 'swim-log-evaluation' ); ?></p></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Location', 'swim-log-evaluation' ) : esc_html__( 'Add Location', 'swim-log-evaluation' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'swimlog_save_location' ); ?>
				<input type="hidden" name="swimlog_location_action" value="save">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="swimlog-location-name"><?php esc_html_e( 'Name', 'swim-log-evaluation' ); ?></label></th><td><input class="regular-text" required id="swimlog-location-name" name="name" type="text" maxlength="191" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="swimlog-pool-length"><?php esc_html_e( 'Pool length', 'swim-log-evaluation' ); ?></label></th><td><input id="swimlog-pool-length" name="pool_length" type="number" min="0.001" step="0.001" value="<?php echo esc_attr( $editing ? $editing->pool_length : '' ); ?>"> <select name="pool_unit" aria-label="<?php esc_attr_e( 'Pool length unit', 'swim-log-evaluation' ); ?>"><option value=""><?php esc_html_e( 'Select unit', 'swim-log-evaluation' ); ?></option><option value="m" <?php selected( $editing ? $editing->pool_unit : '', 'm' ); ?>><?php esc_html_e( 'Meters', 'swim-log-evaluation' ); ?></option><option value="yd" <?php selected( $editing ? $editing->pool_unit : '', 'yd' ); ?>><?php esc_html_e( 'Yards', 'swim-log-evaluation' ); ?></option></select></td></tr>
					<tr><th scope="row"><label for="swimlog-location-notes"><?php esc_html_e( 'Notes', 'swim-log-evaluation' ); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-location-notes" name="notes"><?php echo esc_textarea( $editing ? $editing->notes : '' ); ?></textarea></td></tr>
				</table>
				<?php submit_button( $editing ? __( 'Update Location', 'swim-log-evaluation' ) : __( 'Add Location', 'swim-log-evaluation' ) ); ?>
				<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-locations' ) ); ?>"><?php esc_html_e( 'Cancel', 'swim-log-evaluation' ); ?></a><?php endif; ?>
			</form>

			<h2><?php esc_html_e( 'Your Locations', 'swim-log-evaluation' ); ?></h2>
			<?php if ( empty( $locations ) ) : ?>
				<p><?php esc_html_e( 'No locations have been added yet.', 'swim-log-evaluation' ); ?></p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'swim-log-evaluation' ); ?></th><th><?php esc_html_e( 'Pool', 'swim-log-evaluation' ); ?></th><th><?php esc_html_e( 'Notes', 'swim-log-evaluation' ); ?></th><th><?php esc_html_e( 'Actions', 'swim-log-evaluation' ); ?></th></tr></thead><tbody>
				<?php foreach ( $locations as $location ) : ?>
					<tr><td><?php echo esc_html( $location->name ); ?></td><td><?php echo null !== $location->pool_length ? esc_html( $location->pool_length . ' ' . $location->pool_unit ) : '&mdash;'; ?></td><td><?php echo esc_html( $location->notes ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'swimlog-locations', 'edit' => $location->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'swim-log-evaluation' ); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field( 'swimlog_delete_location' ); ?><input type="hidden" name="swimlog_location_action" value="delete"><input type="hidden" name="location_id" value="<?php echo esc_attr( $location->id ); ?>"><button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Delete this location? Historical workout snapshots will remain unchanged.', 'swim-log-evaluation' ) ); ?>');"><?php esc_html_e( 'Delete', 'swim-log-evaluation' ); ?></button></form></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}
	public function shortcodes() {
		$this->page( __( 'Shortcodes', 'swim-log-evaluation' ), __( 'Public display shortcode documentation will appear here.', 'swim-log-evaluation' ), 'swimlog_manage_settings' );
	}
	public function settings() {
		$this->page( __( 'Swim Log Settings', 'swim-log-evaluation' ), __( 'Plugin defaults, privacy, upload controls, data preservation, and rebuild tools will appear here.', 'swim-log-evaluation' ), 'swimlog_manage_settings' );
	}
}
