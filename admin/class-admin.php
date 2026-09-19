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
		if ( ! current_user_can( 'swimlog_manage_own_events' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Swim Log page.', 'swim-log-evaluation' ) );
		}
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';

		$user_id = get_current_user_id();
		$error = null;
		if ( isset( $_POST['swimlog_event_action'] ) && 'save' === $_POST['swimlog_event_action'] ) {
			check_admin_referer( 'swimlog_save_event' );
			$id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
			$result = Event::save( $user_id, wp_unslash( $_POST ), $id );
			if ( is_wp_error( $result ) ) $error = $result->get_error_message();
			else { wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','saved'=>1 ), admin_url('admin.php') ) ); exit; }
		}
		if ( isset( $_POST['swimlog_event_action'] ) && 'delete' === $_POST['swimlog_event_action'] ) {
			check_admin_referer( 'swimlog_delete_event' );
			$result=Event::delete(absint($_POST['event_id'] ?? 0),$user_id);
			if(is_wp_error($result)||!$result) $error=is_wp_error($result)?$result->get_error_message():__( 'The event could not be deleted.', 'swim-log-evaluation' );
			else { wp_safe_redirect( add_query_arg( array( 'page'=>'swimlog-events','view'=>'upcoming','deleted'=>1 ), admin_url('admin.php') ) ); exit; }
		}

		$view=sanitize_key($_GET['view'] ?? 'upcoming');
		if(!in_array($view,array('upcoming','past','all'),true)) $view='upcoming';
		$edit_id=absint($_GET['edit'] ?? 0);
		$editing=$edit_id?Event::get_for_user($edit_id,$user_id):null;
		$events=Event::all_for_user($user_id,$view);
		$locations=Location::all_for_user($user_id);
		$strokes=array('FR'=>__('Freestyle','swim-log-evaluation'),'BR'=>__('Breaststroke','swim-log-evaluation'),'BACK'=>__('Backstroke','swim-log-evaluation'),'FLY'=>__('Butterfly','swim-log-evaluation'),'MIXED'=>__('Mixed','swim-log-evaluation'));
		?>
		<div class="wrap swimlog-admin">
			<h1><?php esc_html_e('Events','swim-log-evaluation'); ?></h1>
			<p><?php esc_html_e('Manage upcoming and historical swim events. Past events remain in your history.','swim-log-evaluation'); ?></p>
			<?php if(isset($_GET['saved'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event saved.','swim-log-evaluation'); ?></p></div><?php endif; ?>
			<?php if(isset($_GET['deleted'])):?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Event deleted.','swim-log-evaluation'); ?></p></div><?php endif; ?>
			<?php if($error):?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

			<h2><?php echo $editing?esc_html__('Edit Event','swim-log-evaluation'):esc_html__('Add Event','swim-log-evaluation'); ?></h2>
			<form method="post"><?php wp_nonce_field('swimlog_save_event'); ?><input type="hidden" name="swimlog_event_action" value="save"><input type="hidden" name="event_id" value="<?php echo esc_attr($editing?$editing->id:0); ?>">
			<table class="form-table" role="presentation">
			<tr><th><label for="swimlog-event-name"><?php esc_html_e('Event name','swim-log-evaluation'); ?></label></th><td><input required class="regular-text" maxlength="191" id="swimlog-event-name" name="event_name" value="<?php echo esc_attr($editing?$editing->event_name:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-date"><?php esc_html_e('Date','swim-log-evaluation'); ?></label></th><td><input required type="date" id="swimlog-event-date" name="event_date" value="<?php echo esc_attr($editing?$editing->event_date:''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-time"><?php esc_html_e('Time','swim-log-evaluation'); ?></label></th><td><input type="time" id="swimlog-event-time" name="event_time" value="<?php echo esc_attr($editing&&$editing->event_time?substr($editing->event_time,0,5):''); ?>"></td></tr>
			<tr><th><label for="swimlog-event-location"><?php esc_html_e('Location','swim-log-evaluation'); ?></label></th><td><select id="swimlog-event-location" name="location_id"><option value="0"><?php esc_html_e('No location selected','swim-log-evaluation'); ?></option><?php foreach($locations as $loc):?><option value="<?php echo esc_attr($loc->id); ?>" <?php selected($editing?$editing->location_id:0,$loc->id); ?>><?php echo esc_html($loc->name); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-distance"><?php esc_html_e('Distance','swim-log-evaluation'); ?></label></th><td><select required id="swimlog-event-distance" name="distance_value"><?php foreach(Event::DISTANCES as $d):?><option value="<?php echo esc_attr($d); ?>" <?php selected($editing?$editing->distance_value:0,$d); ?>><?php echo esc_html($d); ?></option><?php endforeach; ?></select> <select required name="course_unit"><option value="m" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'m'); ?>><?php esc_html_e('Meters','swim-log-evaluation'); ?></option><option value="yd" <?php selected($editing?$editing->course_unit:get_option('swimlog_default_course_unit','m'),'yd'); ?>><?php esc_html_e('Yards','swim-log-evaluation'); ?></option></select></td></tr>
			<tr><th><label for="swimlog-event-stroke"><?php esc_html_e('Stroke','swim-log-evaluation'); ?></label></th><td><select required id="swimlog-event-stroke" name="stroke"><?php foreach($strokes as $code=>$label):?><option value="<?php echo esc_attr($code); ?>" <?php selected($editing?$editing->stroke:'FR',$code); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="swimlog-event-notes"><?php esc_html_e('Notes','swim-log-evaluation'); ?></label></th><td><textarea class="large-text" rows="4" id="swimlog-event-notes" name="notes"><?php echo esc_textarea($editing?$editing->notes:''); ?></textarea></td></tr>
			</table><?php submit_button($editing?__('Update Event','swim-log-evaluation'):__('Add Event','swim-log-evaluation')); ?><?php if($editing):?> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events')); ?>"><?php esc_html_e('Cancel','swim-log-evaluation'); ?></a><?php endif; ?></form>

			<h2><?php esc_html_e('Your Events','swim-log-evaluation'); ?></h2>
			<p><a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=upcoming')); ?>"><?php esc_html_e('Upcoming','swim-log-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=past')); ?>"><?php esc_html_e('Past','swim-log-evaluation'); ?></a> | <a href="<?php echo esc_url(admin_url('admin.php?page=swimlog-events&view=all')); ?>"><?php esc_html_e('All','swim-log-evaluation'); ?></a></p>
			<?php if(empty($events)):?><p><?php esc_html_e('No events found for this view.','swim-log-evaluation'); ?></p><?php else:?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','swim-log-evaluation'); ?></th><th><?php esc_html_e('Event','swim-log-evaluation'); ?></th><th><?php esc_html_e('Distance','swim-log-evaluation'); ?></th><th><?php esc_html_e('Stroke','swim-log-evaluation'); ?></th><th><?php esc_html_e('Location','swim-log-evaluation'); ?></th><th><?php esc_html_e('Actions','swim-log-evaluation'); ?></th></tr></thead><tbody>
			<?php foreach($events as $event): $locname='—'; foreach($locations as $loc){if((int)$loc->id===(int)$event->location_id){$locname=$loc->name;break;}} ?><tr><td><?php echo esc_html(wp_date(get_option('date_format','F j, Y'),strtotime($event->event_date.' 12:00:00'))); ?><?php if($event->event_time) echo '<br>'.esc_html(wp_date(get_option('time_format','g:i a'),strtotime($event->event_date.' '.$event->event_time))); ?></td><td><?php echo esc_html($event->event_name); ?></td><td><?php echo esc_html($event->distance_value.' '.$event->course_unit); ?></td><td><?php echo esc_html($strokes[$event->stroke]??$event->stroke); ?></td><td><?php echo esc_html($locname); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'swimlog-events','view'=>$view,'edit'=>$event->id),admin_url('admin.php'))); ?>"><?php esc_html_e('Edit','swim-log-evaluation'); ?></a> <form method="post" style="display:inline"><?php wp_nonce_field('swimlog_delete_event'); ?><input type="hidden" name="swimlog_event_action" value="delete"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>"><button class="button button-small" onclick="return confirm('<?php echo esc_js(__('Delete this event?','swim-log-evaluation')); ?>');"><?php esc_html_e('Delete','swim-log-evaluation'); ?></button></form></td></tr><?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div><?php
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
