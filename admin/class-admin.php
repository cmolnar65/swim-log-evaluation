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
		$this->page( __( 'Locations', 'swim-log-evaluation' ), __( 'Manage your pool and swim locations here.', 'swim-log-evaluation' ), 'swimlog_manage_own_locations' );
	}
	public function shortcodes() {
		$this->page( __( 'Shortcodes', 'swim-log-evaluation' ), __( 'Public display shortcode documentation will appear here.', 'swim-log-evaluation' ), 'swimlog_manage_settings' );
	}
	public function settings() {
		$this->page( __( 'Swim Log Settings', 'swim-log-evaluation' ), __( 'Plugin defaults, privacy, upload controls, data preservation, and rebuild tools will appear here.', 'swim-log-evaluation' ), 'swimlog_manage_settings' );
	}
}
