<?php
use PHPUnit\Framework\TestCase;

final class CalendarUxRefinementTest extends TestCase {
	public function test_mobile_agenda_renders_every_date_so_empty_dates_can_be_planned() {
		$view = file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' );
		$start = strpos( $view, 'function renderAgenda' );
		$end = strpos( $view, 'function renderMonth', $start );
		$body = substr( $view, $start, $end - $start );
		$this->assertStringContainsString( "var planButton = planDateButton( key, root ); if ( planButton ) group.appendChild( planButton );", $body );
		$this->assertStringContainsString( "No workouts or events.", $body );
		$this->assertStringNotContainsString( "if ( items.length )", $body );
	}

	public function test_initial_client_sync_populates_responsive_agenda() {
		$view = file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' );
		$start = strpos( $view, 'function init( root )' );
		$body = substr( $view, $start );
		$this->assertStringContainsString( "loadMonth( root, new Date( Number( root.dataset.year ), Number( root.dataset.month ) - 1, 1 ) );", $body );
	}

	public function test_week_recorded_workouts_keep_detail_payload() {
		$view = file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' );
		$start = strpos( $view, 'function renderWeek' );
		$end = strpos( $view, 'function showDuplicateWeekEditor', $start );
		$body = substr( $view, $start, $end - $start );
		$this->assertStringContainsString( "item( 'swimlog-calendar-workout', parts, false, workout )", $body );
	}

	public function test_detail_close_collapses_recorded_and_planned_triggers() {
		$view = file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' );
		$start = strpos( $view, 'function closeWorkoutDetail' );
		$end = strpos( $view, 'function weekStart', $start );
		$body = substr( $view, $start, $end - $start );
		$this->assertStringContainsString( '[data-calendar-workout][aria-expanded="true"],[data-calendar-planned-workout][aria-expanded="true"]', $body );
		$this->assertStringContainsString( "item.setAttribute( 'aria-expanded', 'false' )", $body );
	}

	public function test_item_types_are_textually_distinct_not_color_only() {
		$blocks = file_get_contents( dirname( __DIR__ ) . '/includes/class-blocks.php' );
		$view = file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' );
		foreach ( array( 'Recorded', 'Planned', 'Event' ) as $label ) {
			$this->assertStringContainsString( $label, $blocks );
			$this->assertStringContainsString( $label, $view );
		}
		$this->assertStringContainsString( 'swimlog-calendar-kind', $blocks );
	}

	public function test_both_loaders_manage_busy_state_and_return_requests() {
		$view = file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' );
		foreach ( array( 'function loadWeek', 'function loadMonth' ) as $method ) {
			$start = strpos( $view, $method );
			$end = strpos( $view, "\n\tfunction ", $start + 20 );
			$body = false === $end ? substr( $view, $start ) : substr( $view, $start, $end - $start );
			$this->assertStringContainsString( "root.setAttribute( 'aria-busy', 'true' )", $body, $method );
			$this->assertStringContainsString( 'return fetch(', $body, $method );
			$this->assertStringContainsString( "root.setAttribute( 'aria-busy', 'false' )", $body, $method );
		}
	}

	public function test_phase_nine_b_preserves_privacy_and_database_contracts() {
		$view = strtolower( file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' ) );
		foreach ( array( 'latitude', 'longitude', 'trackpoint', 'device_model', 'device_manufacturer', 'source_type' ) as $term ) {
			$this->assertStringNotContainsString( $term, $view );
		}
		$rest = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		$this->assertStringNotContainsString( 'CREATABLE', $rest );
		$this->assertStringNotContainsString( 'EDITABLE', $rest );
		$this->assertStringNotContainsString( 'DELETABLE', $rest );
		$main = file_get_contents( dirname( __DIR__ ) . '/swim-log-evaluation.php' );
		$this->assertStringContainsString( "SWIMLOG_EVALUATION_DB_VERSION', '1.1'", $main );
	}
}
