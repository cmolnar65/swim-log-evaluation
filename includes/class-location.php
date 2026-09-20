<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Per-user swim location persistence. */
final class Location {
	public static function all_for_user( $user_id ) {
		global $wpdb;
		$table = Database::table( 'locations' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY name ASC, id ASC", $user_id )
		);
	}

	public static function get_for_user( $id, $user_id ) {
		global $wpdb;
		$table = Database::table( 'locations' );
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND user_id = %d", $id, $user_id )
		);
	}

	public static function save( $user_id, $data, $id = 0 ) {
		global $wpdb;
		$table = Database::table( 'locations' );

		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return new \WP_Error( 'swimlog_location_name', __( 'Location name is required.', 'swim-log-evaluation' ) );
		}

		$unit = strtolower( sanitize_key( $data['pool_unit'] ?? '' ) );
		if ( ! in_array( $unit, array( 'm', 'yd' ), true ) ) {
			$unit = '';
		}

		$length = isset( $data['pool_length'] ) && '' !== $data['pool_length'] ? (float) $data['pool_length'] : null;
		if ( null !== $length && $length <= 0 ) {
			return new \WP_Error( 'swimlog_pool_length', __( 'Pool length must be greater than zero.', 'swim-log-evaluation' ) );
		}
		if ( null !== $length && '' === $unit ) {
			return new \WP_Error( 'swimlog_pool_unit', __( 'Select meters or yards when a pool length is entered.', 'swim-log-evaluation' ) );
		}

		$length_m = null;
		if ( null !== $length ) {
			$length_m = 'yd' === $unit ? $length * 0.9144 : $length;
		}

		$now = current_time( 'mysql' );
		$values = array(
			'user_id'       => (int) $user_id,
			'name'          => $name,
			'pool_length'   => $length,
			'pool_unit'     => $unit ?: null,
			'pool_length_m' => null === $length_m ? null : round( $length_m, 3 ),
			'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ),
			'updated_at'    => $now,
		);

		if ( $id ) {
			if ( ! self::get_for_user( $id, $user_id ) ) {
				return new \WP_Error( 'swimlog_location_not_found', __( 'Location not found.', 'swim-log-evaluation' ) );
			}
			$result = $wpdb->update( $table, $values, array( 'id' => (int) $id, 'user_id' => (int) $user_id ) );
			return false === $result ? new \WP_Error( 'swimlog_location_save', __( 'The location could not be saved.', 'swim-log-evaluation' ) ) : (int) $id;
		}

		$values['created_at'] = $now;
		$result = $wpdb->insert( $table, $values );
		return false === $result ? new \WP_Error( 'swimlog_location_save', __( 'The location could not be saved.', 'swim-log-evaluation' ) ) : (int) $wpdb->insert_id;
	}

	public static function delete( $id, $user_id ) {
		global $wpdb;
		$table = Database::table( 'locations' );
		if ( ! self::get_for_user( $id, $user_id ) ) {
			return new \WP_Error( 'swimlog_location_not_found', __( 'Location not found.', 'swim-log-evaluation' ) );
		}
		// Historical workouts retain their pool snapshot. Existing references may
		// retain this ID; display code must tolerate a missing location record.
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id, 'user_id' => (int) $user_id ) );
	}
}
