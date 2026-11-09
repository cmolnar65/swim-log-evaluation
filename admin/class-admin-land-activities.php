<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once dirname( __DIR__ ) . '/includes/class-land-activity.php';
require_once __DIR__ . '/class-admin-ui.php';

/** Private read-only recorded activity detail; no swim evaluation or post creation. */
final class Admin_Land_Activities {
    /** Render private Health Connect details on the owner-only swim detail screen. */
    public static function render_health_connect_details( $workout, $owner ) {
        if ( (int) $owner !== (int) get_current_user_id() || (int) $workout->user_id !== (int) $owner || ! current_user_can( 'swimlog_view_own_results' ) || ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'Workout not found.', 'chriss-swim-training-progress-evaluation' ) );
        global $wpdb;
        $row = Database::read( 'get_row', $wpdb->prepare( 'SELECT details_json FROM %i WHERE user_id=%d AND workout_id=%d', Database::table( 'health_connect_activities' ), $owner, (int) $workout->id ) );
        $details = $row && is_string( $row->details_json ) ? json_decode( $row->details_json, true ) : null;
        if ( ! is_array( $details ) ) return;
        $summary = array();
        if ( null !== ( $details['total_calories_kcal'] ?? null ) ) $summary[ __( 'Recorded energy', 'chriss-swim-training-progress-evaluation' ) ] = (string) $details['total_calories_kcal'] . ' kcal';
        if ( null !== ( $details['active_calories_kcal'] ?? null ) ) $summary[ __( 'Recorded active energy', 'chriss-swim-training-progress-evaluation' ) ] = (string) $details['active_calories_kcal'] . ' kcal';
        if ( null !== ( $details['average_speed_mps'] ?? null ) ) $summary[ __( 'Recorded average speed', 'chriss-swim-training-progress-evaluation' ) ] = (string) $details['average_speed_mps'] . ' m/s';
        if ( null !== ( $details['maximum_speed_mps'] ?? null ) ) $summary[ __( 'Recorded maximum speed', 'chriss-swim-training-progress-evaluation' ) ] = (string) $details['maximum_speed_mps'] . ' m/s';
        if ( null !== ( $details['elevation_gain_m'] ?? null ) ) $summary[ __( 'Recorded elevation gain', 'chriss-swim-training-progress-evaluation' ) ] = (string) $details['elevation_gain_m'] . ' m';
        if ( null !== ( $details['floors_climbed'] ?? null ) ) $summary[ __( 'Recorded floors climbed', 'chriss-swim-training-progress-evaluation' ) ] = (string) $details['floors_climbed'];
        foreach ( array( 'step_cadence_samples' => __( 'Recorded step-cadence samples', 'chriss-swim-training-progress-evaluation' ), 'cycling_cadence_samples' => __( 'Recorded cycling-cadence samples', 'chriss-swim-training-progress-evaluation' ), 'power_samples' => __( 'Recorded power samples', 'chriss-swim-training-progress-evaluation' ) ) as $field => $label ) if ( ! empty( $details[$field] ) ) $summary[$label] = (string) count( $details[$field] );
        ?><section class="swimlog-health-connect-details" aria-labelledby="swimlog-hc-details"><h2 id="swimlog-hc-details"><?php esc_html_e( 'Health Connect workout details', 'chriss-swim-training-progress-evaluation' ); ?></h2>
        <?php if ( $summary ) : ?><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><tbody><?php foreach ( $summary as $label => $value ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php if ( ! empty( $details['laps'] ) ) : ?><h3><?php esc_html_e( 'Recorded laps / splits', 'chriss-swim-training-progress-evaluation' ); ?></h3><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Elapsed', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><?php foreach ( $details['laps'] as $index => $lap ) : $duration = max( 0, ( new \DateTimeImmutable( $lap['end_time'] ) )->getTimestamp() - ( new \DateTimeImmutable( $lap['start_time'] ) )->getTimestamp() ); ?><tr><td><?php echo esc_html( $index + 1 ); ?></td><td><?php echo esc_html( null !== ( $lap['distance_m'] ?? null ) ? Admin_UI::activity_distance( $lap['distance_m'], $workout->workout_type, $owner ) : '—' ); ?></td><td><?php echo esc_html( Workout::format_duration( $duration * 1000 ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php if ( ! empty( $details['segments'] ) ) : ?><h3><?php esc_html_e( 'Recorded exercise segments', 'chriss-swim-training-progress-evaluation' ); ?></h3><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e( 'Health Connect segment type', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Start', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'End', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><?php foreach ( $details['segments'] as $index => $segment ) : ?><tr><td><?php echo esc_html( $index + 1 ); ?></td><td><?php echo esc_html( (string) ( $segment['segment_label'] ?? $segment['segment_type'] ) ); ?> <span class="description">(<?php echo esc_html( (string) $segment['segment_type'] ); ?>)</span></td><td><?php echo esc_html( Admin_UI::datetime_text( ( new \DateTimeImmutable( $segment['start_time'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) ) ); ?></td><td><?php echo esc_html( Admin_UI::datetime_text( ( new \DateTimeImmutable( $segment['end_time'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <p class="description"><?php esc_html_e( 'Source detail is kept separate from normalized swim lengths and personal-best calculations.', 'chriss-swim-training-progress-evaluation' ); ?></p></section><?php
    }

    public static function render( $workout, $owner ) {
        if ( (int) $owner !== (int) get_current_user_id() || (int) $workout->user_id !== (int) $owner || ! current_user_can( 'swimlog_view_own_results' ) || ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'Activity not found.', 'chriss-swim-training-progress-evaluation' ) );
        $points = Workout::trackpoints( (int) $workout->id, $owner );
        $laps = Workout::laps( (int) $workout->id, $owner );
        global $wpdb;
        $health_row = Database::read( 'get_row', $wpdb->prepare( 'SELECT details_json FROM %i WHERE user_id=%d AND workout_id=%d', Database::table( 'health_connect_activities' ), $owner, (int) $workout->id ) );
        $health_details = $health_row && is_string( $health_row->details_json ) ? json_decode( $health_row->details_json, true ) : null;
        $labels = Land_Activity::labels();
        $environment = array( 'indoor' => __( 'Indoor', 'chriss-swim-training-progress-evaluation' ), 'outdoor' => __( 'Outdoor', 'chriss-swim-training-progress-evaluation' ) );
        $fields = array(
            __( 'Activity', 'chriss-swim-training-progress-evaluation' ) => $labels[$workout->workout_type],
            __( 'Environment', 'chriss-swim-training-progress-evaluation' ) => $environment[$workout->activity_environment ?? ''] ?? __( 'Unknown', 'chriss-swim-training-progress-evaluation' ),
            __( 'Recorded start', 'chriss-swim-training-progress-evaluation' ) => Admin_UI::datetime_text($workout->workout_start),
            __( 'Distance', 'chriss-swim-training-progress-evaluation' ) => null !== $workout->total_distance_m ? Admin_UI::activity_distance( $workout->total_distance_m, $workout->workout_type, $owner ) : '—',
            __( 'Elapsed', 'chriss-swim-training-progress-evaluation' ) => Workout::format_duration( $workout->elapsed_time_ms ),
            __( 'Recorded moving time', 'chriss-swim-training-progress-evaluation' ) => Workout::format_duration( $workout->moving_time_ms ),
            __( 'Recording device', 'chriss-swim-training-progress-evaluation' ) => trim( (string) $workout->device_manufacturer . ' ' . (string) $workout->device_model ),
            __( 'Recorded average heart rate', 'chriss-swim-training-progress-evaluation' ) => null !== $workout->average_heart_rate ? $workout->average_heart_rate . ' bpm' : '—',
            __( 'Notes', 'chriss-swim-training-progress-evaluation' ) => $workout->notes ?? '',
        );
        if ( is_array( $health_details ) ) {
            if ( null !== ( $health_details['total_calories_kcal'] ?? null ) ) $fields[ __( 'Recorded total energy', 'chriss-swim-training-progress-evaluation' ) ] = (string) $health_details['total_calories_kcal'] . ' kcal';
            if ( null !== ( $health_details['active_calories_kcal'] ?? null ) ) $fields[ __( 'Recorded active energy', 'chriss-swim-training-progress-evaluation' ) ] = (string) $health_details['active_calories_kcal'] . ' kcal';
            if ( null !== ( $health_details['average_speed_mps'] ?? null ) ) $fields[ __( 'Recorded average speed', 'chriss-swim-training-progress-evaluation' ) ] = (string) $health_details['average_speed_mps'] . ' m/s';
            if ( null !== ( $health_details['maximum_speed_mps'] ?? null ) ) $fields[ __( 'Recorded maximum speed', 'chriss-swim-training-progress-evaluation' ) ] = (string) $health_details['maximum_speed_mps'] . ' m/s';
            if ( null !== ( $health_details['elevation_gain_m'] ?? null ) ) $fields[ __( 'Recorded elevation gain', 'chriss-swim-training-progress-evaluation' ) ] = (string) $health_details['elevation_gain_m'] . ' m';
            if ( null !== ( $health_details['floors_climbed'] ?? null ) ) $fields[ __( 'Recorded floors climbed', 'chriss-swim-training-progress-evaluation' ) ] = (string) $health_details['floors_climbed'];
            if ( ! empty( $health_details['segments'] ) ) $fields[ __( 'Health Connect exercise segments', 'chriss-swim-training-progress-evaluation' ) ] = (string) count( $health_details['segments'] );
            if ( ! empty( $health_details['speed_samples'] ) ) $fields[ __( 'Health Connect speed samples', 'chriss-swim-training-progress-evaluation' ) ] = (string) count( $health_details['speed_samples'] );
            if ( ! empty( $health_details['step_cadence_samples'] ) ) $fields[ __( 'Health Connect step-cadence samples', 'chriss-swim-training-progress-evaluation' ) ] = (string) count( $health_details['step_cadence_samples'] );
            if ( ! empty( $health_details['cycling_cadence_samples'] ) ) $fields[ __( 'Health Connect cycling-cadence samples', 'chriss-swim-training-progress-evaluation' ) ] = (string) count( $health_details['cycling_cadence_samples'] );
            if ( ! empty( $health_details['power_samples'] ) ) $fields[ __( 'Health Connect power samples', 'chriss-swim-training-progress-evaluation' ) ] = (string) count( $health_details['power_samples'] );
        }
        ?><div class="wrap swimlog-admin swimlog-land-activity"><h1><?php echo esc_html( $labels[$workout->workout_type] ); ?></h1>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=swimlog-workouts' ) ); ?>"><?php esc_html_e( 'Back to Workouts', 'chriss-swim-training-progress-evaluation' ); ?></a></p>
        <div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><tbody><?php foreach ( $fields as $label => $value ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( '' === $value ? '—' : $value ); ?></td></tr><?php endforeach; ?></tbody></table></div>
        <h2><?php esc_html_e( 'GPS Route', 'chriss-swim-training-progress-evaluation' ); ?></h2>
        <?php $svg = Admin_Workouts::activity_route_svg( $points ); ?>
        <?php if ( '' !== $svg ) : ?>
        <?php echo wp_kses( $svg, array( 'svg' => array( 'class' => true, 'viewbox' => true, 'role' => true, 'aria-label' => true, 'style' => true ), 'line' => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'opacity' => true ), 'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'vector-effect' => true ), 'circle' => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ), 'text' => array( 'x' => true, 'y' => true, 'font-size' => true, 'font-weight' => true ) ) ); ?>
        <p><?php esc_html_e( 'Recorded GPS only. Missing points and recording gaps are not filled in.', 'chriss-swim-training-progress-evaluation' ); ?></p>
        <?php else : ?><p><?php esc_html_e( 'No usable GPS route is stored for this activity.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>
        <h2><?php esc_html_e( 'Recorded laps / splits', 'chriss-swim-training-progress-evaluation' ); ?></h2>
        <div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Elapsed', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><?php foreach ( $laps as $lap ) : ?><tr><td><?php echo esc_html( $lap->sequence_no ); ?></td><td><?php echo esc_html( null !== $lap->distance_m ? Admin_UI::activity_distance( $lap->distance_m, $workout->workout_type, $owner ) : '—' ); ?></td><td><?php echo esc_html( Workout::format_duration( $lap->elapsed_time_ms ) ); ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php if ( is_array( $health_details ) && ! empty( $health_details['segments'] ) ) : ?><h2><?php esc_html_e( 'Recorded exercise segments', 'chriss-swim-training-progress-evaluation' ); ?></h2>
        <div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e( 'Health Connect segment type', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Start', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'End', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><?php foreach ( $health_details['segments'] as $index => $segment ) : ?><tr><td><?php echo esc_html( $index + 1 ); ?></td><td><?php echo esc_html( (string) ( $segment['segment_label'] ?? $segment['segment_type'] ?? '' ) ); ?> <span class="description">(<?php echo esc_html( (string) ( $segment['segment_type'] ?? '' ) ); ?>)</span></td><td><?php echo esc_html( Admin_UI::datetime_text( ( new \DateTimeImmutable( $segment['start_time'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) ) ); ?></td><td><?php echo esc_html( Admin_UI::datetime_text( ( new \DateTimeImmutable( $segment['end_time'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php if ( is_array( $health_details ) ) : foreach ( array( 'speed_samples' => array( __( 'Recorded speed samples', 'chriss-swim-training-progress-evaluation' ), 'meters_per_second', 'm/s' ), 'step_cadence_samples' => array( __( 'Recorded step-cadence samples', 'chriss-swim-training-progress-evaluation' ), 'value', 'steps/min' ), 'cycling_cadence_samples' => array( __( 'Recorded cycling-cadence samples', 'chriss-swim-training-progress-evaluation' ), 'value', 'rpm' ), 'power_samples' => array( __( 'Recorded power samples', 'chriss-swim-training-progress-evaluation' ), 'value', 'W' ) ) as $field => $series ) : if ( ! empty( $health_details[$field] ) ) : ?><h2><?php echo esc_html( $series[0] ); ?></h2><div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Time', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php echo esc_html( $series[2] ); ?></th></tr></thead><tbody><?php foreach ( $health_details[$field] as $sample ) : ?><tr><td><?php echo esc_html( Admin_UI::datetime_text( ( new \DateTimeImmutable( $sample['time'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) ) ); ?></td><td><?php echo esc_html( (string) ( $sample[$series[1]] ?? $sample['value'] ?? '' ) ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; endforeach; endif; ?>
        <?php if ( 'health_connect' !== ( $workout->source_type ?? '' ) ) : ?><?php Workout_Visibility::controls((int)$workout->id); ?><?php else : ?><p class="description"><?php esc_html_e( 'Health Connect workouts and routes remain private to this account.', 'chriss-swim-training-progress-evaluation' ); ?></p><?php endif; ?>
        </div><?php
    }
}


