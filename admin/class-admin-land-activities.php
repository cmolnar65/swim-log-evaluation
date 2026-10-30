<?php
namespace SwimLogEvaluation;
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once dirname( __DIR__ ) . '/includes/class-land-activity.php';
require_once __DIR__ . '/class-admin-ui.php';

/** Private read-only recorded activity detail; no swim evaluation or post creation. */
final class Admin_Land_Activities {
    public static function render( $workout, $owner ) {
        if ( (int) $owner !== (int) get_current_user_id() || (int) $workout->user_id !== (int) $owner || ! current_user_can( 'swimlog_view_own_results' ) || ! current_user_can( 'swimlog_manage_own_workouts' ) ) wp_die( esc_html__( 'Activity not found.', 'chriss-swim-training-progress-evaluation' ) );
        $points = Workout::trackpoints( (int) $workout->id, $owner );
        $laps = Workout::laps( (int) $workout->id, $owner );
        $labels = Land_Activity::labels();
        $environment = array( 'indoor' => __( 'Indoor', 'chriss-swim-training-progress-evaluation' ), 'outdoor' => __( 'Outdoor', 'chriss-swim-training-progress-evaluation' ) );
        $fields = array(
            __( 'Activity', 'chriss-swim-training-progress-evaluation' ) => $labels[$workout->workout_type],
            __( 'Environment', 'chriss-swim-training-progress-evaluation' ) => $environment[$workout->activity_environment ?? ''] ?? __( 'Unknown', 'chriss-swim-training-progress-evaluation' ),
            __( 'Recorded start', 'chriss-swim-training-progress-evaluation' ) => Admin_UI::datetime_text($workout->workout_start),
            __( 'Distance', 'chriss-swim-training-progress-evaluation' ) => null !== $workout->total_distance_m ? Admin_UI::display_measurement( $workout->total_distance_m, 'm' ) : '—',
            __( 'Elapsed', 'chriss-swim-training-progress-evaluation' ) => Workout::format_duration( $workout->elapsed_time_ms ),
            __( 'Recorded moving time', 'chriss-swim-training-progress-evaluation' ) => Workout::format_duration( $workout->moving_time_ms ),
            __( 'Recording device', 'chriss-swim-training-progress-evaluation' ) => trim( (string) $workout->device_manufacturer . ' ' . (string) $workout->device_model ),
            __( 'Recorded average heart rate', 'chriss-swim-training-progress-evaluation' ) => null !== $workout->average_heart_rate ? $workout->average_heart_rate . ' bpm' : '—',
            __( 'Notes', 'chriss-swim-training-progress-evaluation' ) => $workout->notes ?? '',
        );
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
        <div class="swimlog-table-wrap" tabindex="0" role="group"><table class="widefat striped"><thead><tr><th>#</th><th><?php esc_html_e( 'Distance', 'chriss-swim-training-progress-evaluation' ); ?></th><th><?php esc_html_e( 'Elapsed', 'chriss-swim-training-progress-evaluation' ); ?></th></tr></thead><tbody><?php foreach ( $laps as $lap ) : ?><tr><td><?php echo esc_html( $lap->sequence_no ); ?></td><td><?php echo esc_html( null !== $lap->distance_m ? Admin_UI::display_measurement( $lap->distance_m, 'm' ) : '—' ); ?></td><td><?php echo esc_html( Workout::format_duration( $lap->elapsed_time_ms ) ); ?></td></tr><?php endforeach; ?></tbody></table></div>
        </div><?php
    }
}


