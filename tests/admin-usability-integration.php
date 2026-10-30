<?php
// Actual disposable WordPress forms and persistence, using only synthetic records.
use SwimLogEvaluation\{Admin_UI,Admin_Planned_Workouts as Plans_UI,Admin_Events_Locations as Events_UI,Admin_Structured_Workouts as Structures_UI,Planned_Workout as Plans,Location,Event,Structured_Workout_Store as Store};
foreach ( array( 'ui', 'planned-workouts', 'events-locations', 'structured-workouts', 'analytics', 'personal-bests', 'shortcodes', 'settings', 'workouts', 'manual-workout', 'dashboard' ) as $file ) require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-' . $file . '.php';
$old_user = get_current_user_id(); $post = $_POST; $get = $_GET; $request = $_REQUEST; $screen = $GLOBALS['current_screen'] ?? null;
$users = array(); $ids = array(); $checks = 0; $die = null; $html = array();
$verify = static function ( $ok, $message ) use ( &$checks ) { ++$checks; if ( ! $ok ) throw new RuntimeException( $message ); };
$reset = static function () {
    foreach ( array( Plans_UI::class => array( 'action_error', 'submitted_form' ), Events_UI::class => array( 'event_action_error', 'event_form', 'location_action_error', 'location_form' ), Structures_UI::class => array( 'submitted_form' ) ) as $class => $properties ) foreach ( $properties as $name ) { $property = new ReflectionProperty( $class, $name ); $property->setAccessible( true ); $property->setValue( null, null ); }
    unset( $GLOBALS['swimlog_structure_error'] );
};
$render = static function ( $callback ) { ob_start(); try { call_user_func( $callback ); return ob_get_contents(); } finally { ob_end_clean(); } };
$set = static function ( $page, $fields = array(), $query = array() ) { $_GET = array_merge( array( 'page' => $page ), $query ); $_POST = $fields; $_REQUEST = array_merge( $_GET, $_POST ); };
try {
    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php'; require_once ABSPATH . 'wp-admin/includes/screen.php'; set_current_screen( 'dashboard' );
    foreach ( array( 'owner', 'other' ) as $name ) {
        $user = wp_create_user( 'swimlog_ux_' . $name . '_' . wp_generate_password( 8, false ), wp_generate_password( 32 ) );
        if ( is_wp_error( $user ) ) throw new RuntimeException( 'Create usability owner.' );
        $users[$name] = (int) $user;
        foreach ( array( 'swimlog_view_own_results', 'swimlog_manage_own_workouts', 'swimlog_manage_own_events', 'swimlog_manage_own_locations', 'swimlog_manage_settings', 'swimlog_upload_workouts' ) as $cap ) ( new WP_User( $user ) )->add_cap( $cap );
    }
    $owner = $users['owner']; wp_set_current_user( $owner ); $date = wp_date( 'Y-m-d', time() + 86400 );
    $input = array( 'title' => 'Synthetic yard plan', 'planned_date' => $date, 'workout_type' => 'pool', 'pool_course' => 'yd', 'primary_stroke' => 'FR', 'planned_distance' => '2000', 'planned_distance_unit' => 'yd', 'pool_length_native' => '25', 'planned_duration' => '45:00.123', 'workout_instructions' => "8 x 250 FR\nSynthetic instruction", 'notes' => 'Synthetic original notes' );
    $id = Plans::save( $owner, Plans_UI::normalize_form( $input ) ); $verify( ! is_wp_error( $id ), 'Save canonical plan from native input.' ); $ids['plan'] = $id;
    $plan = Plans::get_for_user( $id, $owner );
    $verify( abs( (float) $plan->planned_distance_m - 1828.8 ) < 0.000001 && abs( (float) $plan->pool_length_m - 22.86 ) < 0.000001 && 2700123 === (int) $plan->planned_duration_ms, 'Actual persistence keeps canonical meters and exact milliseconds.' );
    $set( 'swimlog-planned-workouts', array(), array( 'edit' => $id ) ); $initial = $render( array( Plans_UI::class, 'page' ) );
    foreach ( array( 'value="2000"', 'value="25"', 'value="45:00.123"' ) as $needle ) $verify( false !== strpos( $initial, $needle ), 'Existing plan round-trip displays ' . $needle );
    $verify( false === strpos( $initial, '2700123 ms' ) && false !== strpos( $initial, '45:00.123' ), 'List duration is human-readable.' );
    $attempt = array_merge( $input, array( 'swimlog_planned_workout_action' => 'save', 'planned_workout_id' => $id, 'title' => 'Attempted plan title', 'planned_duration' => '45:99', 'notes' => "Attempted notes\nSecond line", '_wpnonce' => wp_create_nonce( 'swimlog_save_planned_workout' ) ) );
    $set( 'swimlog-planned-workouts', $attempt, array( 'edit' => $id ) ); Plans_UI::handle_actions(); $html['PLAN'] = $render( array( Plans_UI::class, 'page' ) );
    foreach ( array( 'Attempted plan title', '45:99', "Attempted notes\nSecond line", 'data-swimlog-form-error' ) as $needle ) $verify( false !== strpos( $html['PLAN'], $needle ), 'Failed planned save retains ' . $needle );
    $verify( 'Synthetic yard plan' === Plans::get_for_user( $id, $owner )->title && 2700123 === (int) Plans::get_for_user( $id, $owner )->planned_duration_ms, 'Failed planned save does not overwrite stored plan.' );
    $reset(); $attempt['planned_workout_id'] = 0; $attempt['title'] = 'New unsaved plan'; $set( 'swimlog-planned-workouts', $attempt ); Plans_UI::handle_actions();
    $verify( false !== strpos( $render( array( Plans_UI::class, 'page' ) ), 'New unsaved plan' ), 'New planned form also retains rejected input.' ); $reset();
    $location = Location::save( $owner, array( 'name' => 'Synthetic pool', 'pool_length' => 25, 'pool_unit' => 'yd', 'notes' => 'Original location' ) ); $verify( ! is_wp_error( $location ), 'Seed location.' ); $ids['location'] = $location;
    $set( 'swimlog-locations', array( 'swimlog_location_action' => 'save', 'location_id' => $location, 'name' => 'Attempted pool name', 'pool_length' => '25', 'pool_unit' => 'yd', 'latitude' => '40', 'longitude' => '', 'notes' => "Attempted pool notes\nSecond line", '_wpnonce' => wp_create_nonce( 'swimlog_save_location' ) ), array( 'edit' => $location ) );
    Events_UI::handle_location_actions(); $html['LOCATION'] = $render( array( Events_UI::class, 'locations' ) );
    foreach ( array( 'Attempted pool name', "Attempted pool notes\nSecond line", 'value="40"' ) as $needle ) $verify( false !== strpos( $html['LOCATION'], $needle ), 'Location recovery retains ' . $needle );
    $verify( 'Synthetic pool' === Location::get_for_user( $location, $owner )->name, 'Rejected location leaves saved history unchanged.' ); $reset();
    $event = Event::save( $owner, array( 'event_name' => 'Synthetic event', 'event_date' => $date, 'distance_value' => 100, 'course_unit' => 'yd', 'stroke' => 'FR', 'notes' => 'Original event' ) ); $verify( ! is_wp_error( $event ), 'Seed event.' ); $ids['event'] = $event;
    $set( 'swimlog-events', array( 'swimlog_event_action' => 'save', 'event_id' => $event, 'event_name' => 'Attempted event name', 'event_date' => '2030-02-30', 'event_time' => '09:30', 'distance_value' => 100, 'course_unit' => 'yd', 'stroke' => 'BR', 'notes' => "Attempted event notes\nSecond line", '_wpnonce' => wp_create_nonce( 'swimlog_save_event' ) ), array( 'edit' => $event ) );
    Events_UI::handle_event_actions(); $html['EVENT'] = $render( array( Events_UI::class, 'events' ) );
    foreach ( array( 'Attempted event name', "Attempted event notes\nSecond line", 'value="09:30"' ) as $needle ) $verify( false !== strpos( $html['EVENT'], $needle ), 'Event recovery retains ' . $needle );
    $verify( 'Synthetic event' === Event::get_for_user( $event, $owner )->event_name, 'Rejected event leaves original unchanged.' ); $reset();
    $document = array( 'version' => 1, 'unit' => 'm', 'sections' => array( array( 'type' => 'section', 'label' => 'Synthetic Main', 'children' => array( array( 'type' => 'step', 'distance' => 100, 'stroke' => 'FR' ) ) ) ) );
    $structure = Store::save( $owner, 'Synthetic saved structure', $document ); $verify( ! is_wp_error( $structure ), 'Seed structured workout.' ); $ids['structure'] = $structure;
    $draft = $document; $draft['sections'][0]['label'] = 'Attempted section'; $draft['sections'][0]['children'][0]['distance'] = 0;
    $set( 'swimlog-structured-workouts', array( 'swimlog_structure_action' => 'save', 'structure_id' => $structure, 'structure_title' => 'Attempted structure title', 'structure_document' => wp_json_encode( $draft ), '_wpnonce' => wp_create_nonce( 'swimlog_structure_save' ) ), array( 'edit' => $structure ) );
    Structures_UI::handle_actions(); $html['STRUCTURE'] = $render( array( Structures_UI::class, 'page' ) );
    $verify( false !== strpos( $html['STRUCTURE'], 'Attempted structure title' ) && false !== strpos( $html['STRUCTURE'], 'Attempted section' ), 'Rejected structured title and document remain editable.' );
    $verify( 'Synthetic saved structure' === Store::get( $structure, $owner )['title'] && 100 === Store::get( $structure, $owner )['document']['sections'][0]['children'][0]['distance'], 'Rejected structure leaves saved source unchanged.' ); $reset();
    // Capture each other menu renderer with populated owner records for the responsive table pass.
    $pages = array( 'WORKOUTS' => array( 'swimlog-workouts', array( SwimLogEvaluation\Admin_Workouts::class, 'workouts' ) ), 'MANUAL' => array( 'swimlog-manual-workout', array( SwimLogEvaluation\Admin_Manual_Workout::class, 'manual_workout' ) ), 'B Personal Bests' => array( 'swimlog-personal-bests', array( SwimLogEvaluation\Admin_Personal_Bests::class, 'personal_bests' ) ), 'VOLUME' => array( 'swimlog-training-volume', array( SwimLogEvaluation\Admin_Analytics::class, 'training_volume' ) ), 'TRENDS' => array( 'swimlog-training-trends', array( SwimLogEvaluation\Admin_Analytics::class, 'training_trends' ) ), 'ANALYTICS' => array( 'swimlog-pool-analytics', array( SwimLogEvaluation\Admin_Analytics::class, 'pool_analytics' ) ), 'SHORTCODES' => array( 'swimlog-shortcodes', array( SwimLogEvaluation\Admin_Shortcodes::class, 'shortcodes' ) ), 'SETTINGS' => array( 'swimlog-settings', array( SwimLogEvaluation\Admin_Settings::class, 'settings' ) ), 'DASHBOARD' => array( 'swimlog-dashboard', array( SwimLogEvaluation\Admin_Dashboard::class, 'render' ) ) );
    foreach ( $pages as $name => $page ) { $set( $page[0] ); $html[preg_replace( '/[^A-Z]/', '', $name )] = $render( $page[1] ); }
    // Nonces and owner boundaries are unchanged by request-local form recovery.
    $set( 'swimlog-planned-workouts', array_merge( $attempt, array( '_wpnonce' => 'invalid' ) ) );
    $die = static function () { return static function () { throw new RuntimeException( 'expected nonce denial' ); }; }; add_filter( 'wp_die_handler', $die ); $denied = false;
    try { Plans_UI::handle_actions(); } catch ( RuntimeException $error ) { $denied = 'expected nonce denial' === $error->getMessage(); }
    remove_filter( 'wp_die_handler', $die ); $die = null; $verify( $denied, 'Bad nonce cannot update plans.' ); $reset();
    wp_set_current_user( $users['other'] ); $set( 'swimlog-structured-workouts', array(), array( 'edit' => $structure ) );
    $verify( false === strpos( $render( array( Structures_UI::class, 'page' ) ), 'Synthetic saved structure' ), 'Another owner cannot recover a foreign stored document.' );
    foreach ( $html as $name => $markup ) echo 'SWIMLOG_UX_' . $name . '_HTML_BASE64=' . base64_encode( $markup ) . "\n";
    WP_CLI::success( 'Admin friendly units, failed-save recovery, canonical precision and owner/nonce boundaries passed ' . $checks . ' assertions.' );
} finally {
    if ( $die ) remove_filter( 'wp_die_handler', $die ); $reset();
    wp_set_current_user( $users['owner'] ?? 0 );
    if ( isset( $ids['plan'] ) ) Plans::delete( $ids['plan'], $users['owner'] );
    if ( isset( $ids['event'] ) ) Event::delete( $ids['event'], $users['owner'] );
    if ( isset( $ids['location'] ) ) Location::delete( $ids['location'], $users['owner'] );
    if ( isset( $ids['structure'] ) ) wp_delete_post( $ids['structure'], true );
    require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $users as $user ) wp_delete_user( $user );
    $_POST = $post; $_GET = $get; $_REQUEST = $request; $GLOBALS['current_screen'] = $screen; wp_set_current_user( $old_user );
}
