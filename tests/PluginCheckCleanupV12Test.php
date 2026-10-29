<?php
use PHPUnit\Framework\TestCase;
use SwimLogEvaluation\Database;
use SwimLogEvaluation\Importer;

final class PluginCheckCleanupV12Test extends TestCase {
    public function test_form_authority_queries_bind_the_correct_table_and_workout() {
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public $queries = array();
            public function prepare( $sql, ...$args ) {
                $this->queries[] = array( $sql, $args );
                return $sql;
            }
            public function get_row( $sql, $output = null ) { return null; }
            public function get_col( $sql ) { return array( 'FORM_2026-10-27.fit' ); }
        };
        if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
        $method = new ReflectionMethod( Importer::class, 'workout_has_form_authority' );
        $method->setAccessible( true );
        $this->assertTrue( $method->invoke( null, 123 ) );
        $this->assertSame( array( 'wp_swimlog_workouts', 123 ), $wpdb->queries[0][1] );
        $this->assertSame( array( 'wp_swimlog_imports', 123 ), $wpdb->queries[1][1] );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_request_cache_invalidation_transaction_bypass_and_object_isolation() {
        if ( ! function_exists( 'add_filter' ) ) {
            function add_filter( $hook, $callback, $priority = 10 ) {
                $GLOBALS['swimlog_cleanup_query_observer'] = $callback;
            }
        }
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public $last_error = '';
            public $reads = 0;
            public function get_results( $sql, $output = null ) {
                ++$this->reads;
                return array( (object) array( 'id' => $this->reads ) );
            }
            public function update( ...$args ) { return 1; }
            public function query( $sql ) { return 1; }
        };
        $sql = 'SELECT id FROM wp_swimlog_workouts WHERE user_id=42';
        $first = Database::read( 'get_results', $sql );
        $first[0]->id = 999;
        $second = Database::read( 'get_results', $sql );
        $this->assertSame( 1, $second[0]->id );
        $this->assertSame( 1, $wpdb->reads );
        Database::write( 'update', 'wp_swimlog_workouts', array( 'notes' => 'changed' ), array( 'id' => 1 ) );
        Database::read( 'get_results', $sql );
        $this->assertSame( 2, $wpdb->reads );
        call_user_func( $GLOBALS['swimlog_cleanup_query_observer'], 'UPDATE wp_swimlog_workouts SET notes=NULL' );
        Database::read( 'get_results', $sql );
        $this->assertSame( 3, $wpdb->reads );
        Database::write( 'query', 'START TRANSACTION' );
        Database::read( 'get_results', $sql );
        Database::read( 'get_results', $sql );
        $this->assertSame( 5, $wpdb->reads );
        Database::write( 'query', 'ROLLBACK' );
        Database::read( 'get_results', $sql );
        Database::read( 'get_results', $sql );
        $this->assertSame( 6, $wpdb->reads );
        $wpdb->last_error = 'query failed';
        Database::read( 'get_results', $sql . ' LIMIT 1' );
        Database::read( 'get_results', $sql . ' LIMIT 1' );
        $this->assertSame( 8, $wpdb->reads );
    }
}
