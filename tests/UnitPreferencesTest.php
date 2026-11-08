<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Blocks;
use SwimLogEvaluation\Unit_Preferences as Preferences;

final class UnitPreferencesTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['swimlog_test_options'] = array();
		$GLOBALS['swimlog_test_user_meta'] = array();
	}

	private function prefs( $overrides = array() ) {
		return array_merge( array(
			'swim_course' => 'm', 'running' => 'km', 'walking' => 'km', 'cycling' => 'km', 'weight' => 'kg', 'show_secondary' => false,
		), $overrides );
	}

	public function test_defaults_keep_swim_course_setting_and_current_metric_display() {
		$GLOBALS['swimlog_test_options']['swimlog_default_course_unit'] = 'yd';
		$this->assertSame( 'yd', Preferences::defaults()['swim_course'] );
		$this->assertSame( '2.00 km', Preferences::distance( 2000, 'running', 9 ) );
		$this->assertSame( '70.0 kg', Preferences::weight( 70, 9 ) );
	}

	public function test_swimmer_units_and_secondary_displays_are_owner_scoped() {
		$GLOBALS['swimlog_test_user_meta'][9][Preferences::META_KEY] = $this->prefs( array( 'running' => 'mi', 'walking' => 'mi', 'cycling' => 'mi', 'weight' => 'lb', 'show_secondary' => true ) );
		$GLOBALS['swimlog_test_user_meta'][10][Preferences::META_KEY] = $this->prefs();
		$this->assertSame( '1.00 mi (1.61 km)', Preferences::distance( 1609.344, 'running', 9 ) );
		$this->assertSame( '1.61 km', Preferences::distance( 1609.344, 'running', 10 ) );
		$this->assertSame( '154.3 lb (70.0 kg)', Preferences::weight( 70, 9 ) );
	}

	public function test_save_persists_only_valid_owner_preferences() {
		$saved = Preferences::save( 9, $this->prefs( array( 'running' => 'mi', 'weight' => 'lb', 'show_secondary' => '1' ) ) );
		$this->assertSame( 'mi', $saved['running'] );
		$this->assertSame( 'lb', Preferences::get( 9 )['weight'] );
		$this->assertTrue( Preferences::get( 9 )['show_secondary'] );
		$this->assertTrue( is_wp_error( Preferences::save( 0, $this->prefs() ) ) );
	}

	public function test_swimming_values_keep_their_recorded_meter_or_yard_course() {
		$GLOBALS['swimlog_test_user_meta'][9][Preferences::META_KEY] = $this->prefs( array( 'swim_course' => 'yd', 'show_secondary' => true ) );
		$format = new ReflectionMethod( Blocks::class, 'calendar_distance_text' );
		$format->setAccessible( true );
		$this->assertSame( '1,000 m', $format->invoke( null, 1000, 'pool', 'm', 9 ) );
		$this->assertSame( '1,094 yd', $format->invoke( null, 1000, 'pool', 'yd', 9 ) );
	}

	public function test_invalid_units_and_secondary_checkbox_values_are_rejected() {
		$input = $this->prefs( array( 'running' => 'feet' ) );
		$this->assertTrue( is_wp_error( Preferences::sanitize( $input ) ) );
		$input = $this->prefs( array( 'show_secondary' => 'yes' ) );
		$this->assertTrue( is_wp_error( Preferences::sanitize( $input ) ) );
		$input = $this->prefs( array( 'show_secondary' => '1' ) );
		$this->assertTrue( Preferences::sanitize( $input )['show_secondary'] );
	}
}
