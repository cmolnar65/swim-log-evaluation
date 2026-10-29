<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Read-only, owner-scoped dashboard. No training measurements are inferred. */
final class Admin_Dashboard {
    public static function summary( $user_id, $range ) {
        global $wpdb;
        $table = Database::table( 'workouts' );
        $now = new \DateTimeImmutable( 'now', wp_timezone() );
        $start = 'all' === $range ? null : $now->modify( '-' . (int) $range . ' months' )->format( 'Y-m-d H:i:s' );
        $where = $wpdb->prepare( 'user_id = %d', $user_id );
        if ( $start ) $where .= $wpdb->prepare( ' AND workout_start >= %s', $start );
        $where .= $wpdb->prepare( ' AND workout_start <= %s', $now->format( 'Y-m-d H:i:s' ) );
        $totals = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS sessions, COALESCE(SUM(total_distance_m),0) AS meters, COALESCE(SUM(elapsed_time_ms),0) AS elapsed_ms FROM %i WHERE $where", $table ) );
        $volume = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(workout_start,'%Y-%m') AS month, SUM(COALESCE(total_distance_m,0)) AS meters FROM $table WHERE $where GROUP BY DATE_FORMAT(workout_start,'%Y-%m') ORDER BY month ASC" );
        return array( 'totals' => $totals, 'volume' => $volume, 'start' => $start, 'end' => $now->format( 'Y-m-d H:i:s' ) );
    }

    public static function swolf( $user_id, $start, $end, $course, $stroke ) {
        global $wpdb;
        $workouts = Database::table( 'workouts' );
        $lengths = Database::table( 'lengths' );
        $where = $wpdb->prepare( "w.user_id = %d AND w.workout_type = 'pool' AND w.pool_length_unit = %s AND n.length_type = 'active' AND n.swolf_score IS NOT NULL AND n.swolf_score > 0 AND w.workout_start <= %s", $user_id, $course, $end );
        if ( $start ) $where .= $wpdb->prepare( ' AND w.workout_start >= %s', $start );
        if ( 'all' !== $stroke ) $where .= $wpdb->prepare( ' AND n.stroke = %s', $stroke );
        // Group by native pool length: a 25 m SWOLF must never be averaged with 50 m.
        return $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(w.workout_start,'%Y-%m') AS month, w.original_pool_length AS pool_length, AVG(n.swolf_score) AS score, COUNT(*) AS samples FROM %i w INNER JOIN %i n ON n.workout_id = w.id WHERE $where AND w.original_pool_length > 0 GROUP BY DATE_FORMAT(w.workout_start,'%Y-%m'), w.original_pool_length ORDER BY month ASC, pool_length ASC", $workouts, $lengths ) );
    }

    private static function link( $page, $user_id, $args = array() ) {
        if ( current_user_can( 'swimlog_manage_all_users' ) ) $args['swimmer_id'] = $user_id;
        return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
    }

    private static function tile( $label, $value, $url ) {
        echo '<a class="swimlog-dash-tile" href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></a>';
    }

    private static function chart( $rows, $value_key, $title, $unit ) {
        if ( ! $rows ) {
            echo '<p>' . esc_html__( 'No recorded measurements are available for this period and filter.', 'chriss-swim-training-progress-evaluation' ) . '</p>';
            return;
        }
        $max = 0;
        foreach ( $rows as $row ) $max = max( $max, (float) $row->$value_key );
        echo '<div class="swimlog-dash-bars" role="list" aria-label="' . esc_attr( $title ) . '">';
        foreach ( $rows as $row ) {
            $value = (float) $row->$value_key;
            $pct = $max > 0 ? max( 1, round( 100 * $value / $max, 1 ) ) : 0;
            $month = (string) $row->month;
            $label = $month . ( isset( $row->pool_length ) ? ' / ' . $row->pool_length . ' ' . $unit : '' );
            echo '<div class="swimlog-dash-bar" role="listitem" aria-label="' . esc_attr( $label . ': ' . round( $value, 1 ) ) . '">';
            echo '<div class="swimlog-dash-bar-track"><div class="swimlog-dash-bar-fill" style="height:' . esc_attr( $pct ) . '%"></div></div>';
            echo '<span>' . esc_html( $month ) . '</span><strong>' . esc_html( number_format_i18n( $value, 1 ) ) . '</strong>';
            if ( isset( $row->pool_length ) ) echo '<small>' . esc_html( $row->pool_length . ' ' . $unit ) . '</small>';
            echo '</div>';
        }
        echo '</div>';
    }

    public static function render() {
        if ( ! current_user_can( 'swimlog_view_own_results' ) ) wp_die( esc_html__( 'You do not have permission to view these results.', 'chriss-swim-training-progress-evaluation' ) );
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-workout.php';
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-event.php';
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-evaluator.php';
        require_once SWIMLOG_EVALUATION_DIR . 'includes/class-records.php';
        $user_id = Admin_UI::selected_user_id();
        // Read-only URL controls. Values are explicitly whitelisted.
        $range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '3'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $range, array( '1', '3', '6', '12', 'all' ), true ) ) $range = '3';
        $course = isset( $_GET['course'] ) ? sanitize_key( wp_unslash( $_GET['course'] ) ) : 'm'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) $course = 'm';
        $stroke = isset( $_GET['stroke'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['stroke'] ) ) ) : 'ALL'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $stroke, array( 'ALL', 'FR', 'BR', 'BACK', 'FLY' ), true ) ) $stroke = 'ALL';
        $summary = self::summary( $user_id, $range );
        $recent = Workout::recent_for_user( $user_id, 5 );
        $latest = $recent ? $recent[0] : null;
        $pbs = Records::personal_bests( $user_id, $course );
        $events = Event::upcoming_for_user( $user_id, max( 1, min( 50, (int) get_option( 'swimlog_default_event_count', 5 ) ) ) );
        $swolf = self::swolf( $user_id, $summary['start'], $summary['end'], $course, 'ALL' === $stroke ? 'all' : $stroke );
        $totals = $summary['totals'];
        $hours = (float) $totals->elapsed_ms / 3600000;
        $pb_count = 0;
        foreach ( $pbs as $by_stroke ) $pb_count += count( (array) $by_stroke );
        $args = array( 'range' => $range, 'course' => $course, 'stroke' => strtolower( $stroke ) );
        echo '<div class="wrap swimlog-admin swimlog-dashboard"><h1>' . esc_html__( 'Swim Training Dashboard', 'chriss-swim-training-progress-evaluation' ) . '</h1>';
        Admin_UI::swimmer_selector( $user_id, 'swimlog-dashboard' );
        echo '<form class="swimlog-dash-filters" method="get"><input type="hidden" name="page" value="swimlog-dashboard">';
        if ( current_user_can( 'swimlog_manage_all_users' ) ) echo '<input type="hidden" name="swimmer_id" value="' . esc_attr( $user_id ) . '">';
        echo '<label for="swimlog-dash-range">' . esc_html__( 'Period', 'chriss-swim-training-progress-evaluation' ) . '</label><select name="range" id="swimlog-dash-range">';
        foreach ( array( '1' => '1 month', '3' => '3 months', '6' => '6 months', '12' => '12 months', 'all' => 'All time' ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '"' . selected( $range, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
        echo '</select><label for="swimlog-dash-course">' . esc_html__( 'SWOLF course', 'chriss-swim-training-progress-evaluation' ) . '</label><select id="swimlog-dash-course" name="course"><option value="m"' . selected( $course, 'm', false ) . '>Meters</option><option value="yd"' . selected( $course, 'yd', false ) . '>Yards</option></select>';
        echo '<label for="swimlog-dash-stroke">' . esc_html__( 'SWOLF stroke', 'chriss-swim-training-progress-evaluation' ) . '</label><select id="swimlog-dash-stroke" name="stroke">';
        foreach ( array( 'all' => 'All strokes', 'fr' => 'Freestyle', 'br' => 'Breaststroke', 'back' => 'Backstroke', 'fly' => 'Butterfly' ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '"' . selected( strtolower( $stroke ), $value, false ) . '>' . esc_html( $label ) . '</option>';
        echo '</select><button class="button">' . esc_html__( 'Apply', 'chriss-swim-training-progress-evaluation' ) . '</button></form>';
        echo '<div class="swimlog-dash-tiles">';
        self::tile( __( 'Training distance', 'chriss-swim-training-progress-evaluation' ), number_format_i18n( (float) $totals->meters / 1000, 2 ) . ' km', self::link( 'swimlog-training-volume', $user_id ) );
        self::tile( __( 'Sessions', 'chriss-swim-training-progress-evaluation' ), number_format_i18n( (int) $totals->sessions ), self::link( 'swimlog-workouts', $user_id ) );
        self::tile( __( 'Elapsed time', 'chriss-swim-training-progress-evaluation' ), number_format_i18n( $hours, 1 ) . ' h', self::link( 'swimlog-training-trends', $user_id ) );
        self::tile( __( 'Personal best categories', 'chriss-swim-training-progress-evaluation' ), number_format_i18n( $pb_count ), self::link( 'swimlog-personal-bests', $user_id, array( 'course' => $course ) ) );
        self::tile( __( 'Latest swim', 'chriss-swim-training-progress-evaluation' ), $latest ? wp_date( 'M j, Y', strtotime( $latest->workout_start ) ) : '—', self::link( 'swimlog-workouts', $user_id, $latest ? array( 'workout_id' => $latest->id ) : array() ) );
        self::tile( __( 'Next event', 'chriss-swim-training-progress-evaluation' ), $events ? $events[0]->event_name : '—', self::link( 'swimlog-events', $user_id ) );
        echo '</div>';
        echo '<section class="swimlog-dash-carousel" aria-label="' . esc_attr__( 'Training charts', 'chriss-swim-training-progress-evaluation' ) . '"><div class="swimlog-dash-carousel-nav"><h2>' . esc_html__( 'Training charts', 'chriss-swim-training-progress-evaluation' ) . '</h2><button type="button" class="button" data-swimlog-chart-prev aria-label="Previous chart">‹</button><button type="button" class="button" data-swimlog-chart-next aria-label="Next chart">›</button></div>';
        echo '<div class="swimlog-dash-chart-track" tabindex="0"><article class="swimlog-dash-chart"><h3>' . esc_html__( 'Training volume (km)', 'chriss-swim-training-progress-evaluation' ) . '</h3>';
        self::chart( array_map( static function( $row ) { $row->meters = (float) $row->meters / 1000; return $row; }, $summary['volume'] ), 'meters', 'Training volume', 'km' );
        echo '<p><a href="' . esc_url( self::link( 'swimlog-training-trends', $user_id ) ) . '">' . esc_html__( 'View Training Trends', 'chriss-swim-training-progress-evaluation' ) . '</a></p></article>';
        echo '<article class="swimlog-dash-chart"><h3>' . esc_html__( 'SWOLF by month and pool length', 'chriss-swim-training-progress-evaluation' ) . '</h3>';
        self::chart( $swolf, 'score', 'SWOLF', $course );
        echo '<p>' . esc_html__( 'Only measured active lengths are included; pool lengths are kept separate.', 'chriss-swim-training-progress-evaluation' ) . ' <a href="' . esc_url( self::link( 'swimlog-pool-analytics', $user_id ) ) . '">' . esc_html__( 'View Pool Analytics', 'chriss-swim-training-progress-evaluation' ) . '</a></p></article></div></section>';
        echo '<h2>' . esc_html__( 'Recent workouts', 'chriss-swim-training-progress-evaluation' ) . '</h2>';
        if ( ! $recent ) echo '<p>' . esc_html__( 'No recorded workouts yet.', 'chriss-swim-training-progress-evaluation' ) . '</p>';
        else {
            echo '<div class="swimlog-table-wrap"><table class="widefat striped"><thead><tr><th>Workout</th><th>Date</th><th>Distance</th><th>Elapsed</th><th>Details</th></tr></thead><tbody>';
            foreach ( $recent as $workout ) echo '<tr><td>' . esc_html( $workout->id ) . '</td><td>' . esc_html( $workout->workout_start ) . '</td><td>' . esc_html( Admin_UI::display_measurement( $workout->original_distance, $workout->original_distance_unit ) ) . '</td><td>' . esc_html( Workout::format_duration( $workout->elapsed_time_ms ) ) . '</td><td><a href="' . esc_url( self::link( 'swimlog-workouts', $user_id, array( 'workout_id' => $workout->id ) ) ) . '">View</a></td></tr>';
            echo '</tbody></table></div>';
        }
        if ( $events ) {
            echo '<h2>' . esc_html__( 'Upcoming events and race readiness', 'chriss-swim-training-progress-evaluation' ) . '</h2>';
            echo '<p>' . esc_html__( 'Race Readiness 2.0 summarizes recorded evidence only.', 'chriss-swim-training-progress-evaluation' ) . '</p>';
            require_once SWIMLOG_EVALUATION_DIR . 'admin/class-admin-race-readiness.php';
            $strokes = array( 'FR' => 'Freestyle', 'BR' => 'Breaststroke', 'BACK' => 'Backstroke', 'FLY' => 'Butterfly', 'MIXED' => 'Mixed' );
            foreach ( $events as $event ) {
                echo '<div class="swimlog-dash-event"><h3><a href="' . esc_url( self::link( 'swimlog-events', $user_id ) ) . '">' . esc_html( $event->event_name ) . '</a></h3><p>' . esc_html( $event->event_date ) . '</p>';
                Admin_Race_Readiness::render( Workout::event_readiness( $user_id, $event, Workout::EVENT_READINESS_LOOKBACK_DAYS ), $event, $strokes );
                echo '</div>';
            }
        }
        echo '</div>';
    }
}
