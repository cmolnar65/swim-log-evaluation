<?php
use PHPUnit\Framework\TestCase;

final class BlockSecurityPrivacyTest extends TestCase {
	public function test_public_gate_requires_explicit_consent_without_owner_admin_or_site_default_bypass() {
        $s=file_get_contents(dirname(__DIR__).'/includes/class-shortcodes.php');$a=strpos($s,'private static function allowed');$b=strpos($s,'private static function attrs',$a);$gate=substr($s,$a,$b-$a);
        $this->assertStringContainsString("get_user_meta(\$uid,'swimlog_public_results',true)",$gate);
        foreach(array('get_current_user_id','swimlog_manage_all_users','get_option')as$term)$this->assertStringNotContainsString($term,$gate);
    }

	public function test_owner_resolution_has_no_logged_in_viewer_fallback() {
		$s = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		$start = strpos( $s, 'private static function owner' );
		$end = strpos( $s, 'private static function allowed', $start );
		$owner = substr( $s, $start, $end - $start );
		$this->assertStringContainsString( "if(\$workout)return(int)\$workout->user_id;", $owner );
		$this->assertStringContainsString( "if(!empty(\$atts['user']))return absint(\$atts['user']);", $owner );
		$this->assertStringContainsString( 'get_post()', $owner );
		$this->assertStringNotContainsString( 'get_current_user_id', $owner );
	}

	public function test_every_shared_public_renderer_checks_authorization() {
		$s = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
		foreach ( array( 'render_latest', 'render_bests', 'render_events', 'render_workout', 'render_log', 'render_progress' ) as $method ) {
			$start = strpos( $s, 'public static function ' . $method );
			$this->assertNotFalse( $start, $method );
			$next = strpos( $s, 'public static function ', $start + 25 );
			$body = false === $next ? substr( $s, $start ) : substr( $s, $start, $next - $start );
			$this->assertStringContainsString( 'render_workout'===$method?'Workout_Visibility::get_public(':'self::allowed($uid)', $body, $method );
			$this->assertStringContainsString( 'return self::unavailable()', $body, $method );
		}
	}

	public function test_workout_route_requires_canonical_explicit_opt_in_before_trackpoints_are_loaded() {
        $s=file_get_contents(dirname(__DIR__).'/includes/class-workout-visibility.php');
        $this->assertStringContainsString("if(self::route_allowed(\$w,\$w))",$s);
        $this->assertLessThan(strpos($s,"Database::table('trackpoints')"),strpos($s,'if(self::route_allowed($w,$w))'));
        $this->assertStringContainsString("if ( ! \$state || ! (int)\$state->route_public ) return false;",$s);
    }

	public function test_no_block_attribute_or_editor_script_contains_gps_data() {
		foreach ( array( 'latest-workouts', 'personal-bests', 'upcoming-events', 'workout', 'training-log', 'progress', 'calendar' ) as $dir ) {
			$metadata = strtolower( file_get_contents( dirname( __DIR__ ) . '/blocks/' . $dir . '/block.json' ) );
			$editor = strtolower( file_get_contents( dirname( __DIR__ ) . '/blocks/' . $dir . '/index.js' ) );
			foreach ( array( 'latitude', 'longitude', 'trackpoint', 'gps route', 'coordinates' ) as $term ) {
				$this->assertStringNotContainsString( $term, $metadata, $dir . ' metadata: ' . $term );
				$this->assertStringNotContainsString( $term, $editor, $dir . ' editor: ' . $term );
			}
		}
	}

	public function test_calendar_detail_script_does_not_request_private_workout_fields() {
		$s = strtolower( file_get_contents( dirname( __DIR__ ) . '/blocks/calendar/view.js' ) );
		foreach ( array( 'latitude', 'longitude', 'trackpoint', 'device_model', 'device_manufacturer', 'source_type' ) as $term ) {
			$this->assertStringNotContainsString( $term, $s, $term );
		}
		$this->assertStringNotContainsString( '/workouts/', $s );
	}

	public function test_rest_api_remains_authenticated_and_excludes_trackpoints() {
		$r = file_get_contents( dirname( __DIR__ ) . '/includes/class-rest-api.php' );
		$this->assertStringContainsString( "!is_user_logged_in()||!current_user_can('swimlog_view_own_results')", $r );
		$this->assertStringContainsString( "get_user_meta(get_current_user_id(),'swimlog_rest_api_access',true)", $r );
		$this->assertStringContainsString( 'Workout::get_for_user($id,$uid)', $r );
		$this->assertStringNotContainsString( 'Workout::trackpoints', $r );
		$this->assertStringNotContainsString( "'latitude'", $r );
		$this->assertStringNotContainsString( "'longitude'", $r );
		$this->assertStringContainsString( 'safe_analytics', $r );
	}
}
