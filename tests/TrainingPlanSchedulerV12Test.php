<?php
use PHPUnit\Framework\TestCase;
final class TrainingPlanSchedulerV12Test extends TestCase {
    public function test_scheduler_checks_owner_and_dates_and_rolls_back() {
        $s = file_get_contents( dirname( __DIR__ ) . '/includes/class-training-plan-scheduler.php' );
        foreach ( array( 'get_current_user_id()', 'Training_Plan_Collection::get', 'Training_Plan_Collection::workouts', 'DateTimeImmutable::createFromFormat', 'Planned_Workout::save', 'Planned_Structure_Link::attach', 'self::rollback', 'Planned_Workout::delete' ) as $term ) {
            $this->assertStringContainsString( $term, $s );
        }
    }
    public function test_schedule_form_is_nonce_protected() {
        $s = file_get_contents( dirname( __DIR__ ) . '/admin/class-admin-structured-workouts.php' );
        $this->assertStringContainsString( "check_admin_referer( 'swimlog_plan_schedule' )", $s );
        $this->assertStringContainsString( 'swimlog_schedule_dates', $s );
    }
}
