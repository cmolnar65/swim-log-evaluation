<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Plugin activation bootstrap. */
final class Activator {
	const SWIMMER_CAPABILITIES = array(
		'swimlog_upload_workouts',
		'swimlog_manage_own_workouts',
		'swimlog_manage_own_events',
		'swimlog_manage_own_locations',
		'swimlog_view_own_results',
	);

	const ADMIN_CAPABILITIES = array(
		'swimlog_manage_all_users',
		'swimlog_manage_settings',
	);

	public static function activate() {
		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';

		$previous_version = get_option( 'swimlog_db_version', '0.0.0' );

		if ( ! Database::install_schema() ) {
			update_option( 'swimlog_db_version', $previous_version );
			wp_die(
				esc_html__( 'Swim Log and Evaluation could not create or verify all required database tables. No existing swim history was deleted.', 'swim-log-evaluation' ),
				esc_html__( 'Plugin activation failed', 'swim-log-evaluation' ),
				array( 'back_link' => true )
			);
		}

		update_option( 'swimlog_db_version', SWIMLOG_EVALUATION_DB_VERSION );
		self::add_capabilities();
		self::install_default_settings();
	}

	private static function add_capabilities() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( array_merge( self::SWIMMER_CAPABILITIES, self::ADMIN_CAPABILITIES ) as $capability ) {
				$administrator->add_cap( $capability );
			}
		}

		// Subscriber-like users may use Swim Log without receiving content-editing capabilities.
		$subscriber = get_role( 'subscriber' );
		if ( $subscriber ) {
			foreach ( self::SWIMMER_CAPABILITIES as $capability ) {
				$subscriber->add_cap( $capability );
			}
		}
	}

	private static function install_default_settings() {
		$defaults = array(
			'swimlog_public_results_default' => '0',
			'swimlog_data_preservation'      => '1',
			'swimlog_default_course_unit'    => 'm',
			'swimlog_default_event_count'    => 5,
			'swimlog_allowed_upload_types'   => array( 'fit', 'csv' ),
		);

		foreach ( $defaults as $name => $value ) {
			if ( false === get_option( $name, false ) ) {
				add_option( $name, $value );
			}
		}
	}
}
