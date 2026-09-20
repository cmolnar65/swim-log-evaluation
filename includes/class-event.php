<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Per-user swim event persistence. */
final class Event {
	public const DISTANCES = array( 50, 100, 200, 500, 800, 1000, 1500, 1650, 2000, 2500, 3300, 5000 );
	public const STROKES = array( 'FR', 'BR', 'BACK', 'FLY', 'MIXED' );

	public static function all_for_user( $user_id, $view = 'upcoming' ) {
		global $wpdb;
		$table = Database::table( 'events' );
		$today = current_time( 'Y-m-d' );
		// Keep the prepared query shapes static so placeholder counts are explicit.
		// The view controls only which fixed query is selected; it is never inserted into SQL.
		if ( 'upcoming' === $view ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE user_id = %d AND event_date >= %s ORDER BY event_date ASC, event_time ASC, id ASC",
					$user_id,
					$today
				)
			);
		}
		if ( 'past' === $view ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE user_id = %d AND event_date < %s ORDER BY event_date DESC, event_time DESC, id DESC",
					$user_id,
					$today
				)
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d ORDER BY event_date ASC, event_time ASC, id ASC",
				$user_id
			)
		);
	}

	public static function upcoming_for_user( $user_id, $limit=5 ) {
		global $wpdb;
		$table=Database::table('events'); $locations=Database::table('locations');
		$today=current_time('Y-m-d'); $limit=max(1,min(50,(int)$limit));
		return $wpdb->get_results($wpdb->prepare("SELECT e.*, l.name AS location_name FROM $table e LEFT JOIN $locations l ON l.id=e.location_id AND l.user_id=e.user_id WHERE e.user_id=%d AND e.event_date >= %s ORDER BY e.event_date ASC, CASE WHEN e.event_time IS NULL THEN 1 ELSE 0 END ASC, e.event_time ASC, e.id ASC LIMIT %d",$user_id,$today,$limit));
	}

	public static function get_for_user( $id, $user_id ) {
		global $wpdb;
		$table = Database::table( 'events' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND user_id = %d", $id, $user_id ) );
	}

	public static function save( $user_id, $data, $id = 0 ) {
		global $wpdb;
		$table = Database::table( 'events' );
		$name = sanitize_text_field( $data['event_name'] ?? '' );
		$date = sanitize_text_field( $data['event_date'] ?? '' );
		$time = sanitize_text_field( $data['event_time'] ?? '' );
		$distance = absint( $data['distance_value'] ?? 0 );
		$course = strtolower( sanitize_key( $data['course_unit'] ?? '' ) );
		$stroke = strtoupper( sanitize_key( $data['stroke'] ?? '' ) );
		$location_id = absint( $data['location_id'] ?? 0 );

		if ( '' === $name ) return new \WP_Error( 'swimlog_event_name', __( 'Event name is required.', 'swim-log-evaluation' ) );
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $date ) return new \WP_Error( 'swimlog_event_date', __( 'Enter a valid event date.', 'swim-log-evaluation' ) );
		if ( '' !== $time && ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) return new \WP_Error( 'swimlog_event_time', __( 'Enter a valid event time.', 'swim-log-evaluation' ) );
		if ( ! in_array( $distance, self::DISTANCES, true ) ) return new \WP_Error( 'swimlog_event_distance', __( 'Select a supported event distance.', 'swim-log-evaluation' ) );
		if ( ! in_array( $course, array( 'm', 'yd' ), true ) ) return new \WP_Error( 'swimlog_event_course', __( 'Select meters or yards.', 'swim-log-evaluation' ) );
		if ( ! in_array( $stroke, self::STROKES, true ) ) return new \WP_Error( 'swimlog_event_stroke', __( 'Select a supported stroke.', 'swim-log-evaluation' ) );
		if ( $location_id ) {
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
			if ( ! Location::get_for_user( $location_id, $user_id ) ) return new \WP_Error( 'swimlog_event_location', __( 'Select one of your own locations.', 'swim-log-evaluation' ) );
		}

		$now = current_time( 'mysql' );
		$values = array(
			'user_id'=>(int)$user_id, 'event_name'=>$name, 'event_date'=>$date,
			'event_time'=>'' === $time ? null : $time . ':00', 'location_id'=>$location_id ?: null,
			'distance_value'=>$distance, 'course_unit'=>$course, 'stroke'=>$stroke,
			'notes'=>sanitize_textarea_field( $data['notes'] ?? '' ), 'updated_at'=>$now,
		);
		if ( $id ) {
			if ( ! self::get_for_user( $id, $user_id ) ) return new \WP_Error( 'swimlog_event_not_found', __( 'Event not found.', 'swim-log-evaluation' ) );
			$result=$wpdb->update($table,$values,array('id'=>(int)$id,'user_id'=>(int)$user_id));
			return false===$result ? new \WP_Error('swimlog_event_save',__( 'The event could not be saved.', 'swim-log-evaluation' )) : (int)$id;
		}
		$values['created_at']=$now;
		$result=$wpdb->insert($table,$values);
		return false===$result ? new \WP_Error('swimlog_event_save',__( 'The event could not be saved.', 'swim-log-evaluation' )) : (int)$wpdb->insert_id;
	}

	public static function delete( $id, $user_id ) {
		global $wpdb;
		$table=Database::table('events');
		if(!self::get_for_user($id,$user_id)) return new \WP_Error('swimlog_event_not_found',__( 'Event not found.', 'swim-log-evaluation' ));
		return false !== $wpdb->delete($table,array('id'=>(int)$id,'user_id'=>(int)$user_id));
	}
}
