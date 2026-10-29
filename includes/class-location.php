<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Per-user swim location persistence. */
final class Location {
	public static function all_for_user( $user_id ) {
		global $wpdb;
		$table = Database::table( 'locations' );
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY name ASC, id ASC', $table, $user_id )
		);
	}

	public static function get_for_user( $id, $user_id ) {
		global $wpdb;
		$table = Database::table( 'locations' );
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', $table, $id, $user_id )
		);
	}

	public static function save( $user_id, $data, $id = 0 ) {
		global $wpdb;
		$table = Database::table( 'locations' );

		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return new \WP_Error( 'swimlog_location_name', __( 'Location name is required.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$unit = strtolower( sanitize_key( $data['pool_unit'] ?? '' ) );
		if ( ! in_array( $unit, array( 'm', 'yd' ), true ) ) {
			$unit = '';
		}

		$length = isset( $data['pool_length'] ) && '' !== $data['pool_length'] ? (float) $data['pool_length'] : null;
		if ( null !== $length && $length <= 0 ) {
			return new \WP_Error( 'swimlog_pool_length', __( 'Pool length must be greater than zero.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( null !== $length && '' === $unit ) {
			return new \WP_Error( 'swimlog_pool_unit', __( 'Select meters or yards when a pool length is entered.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$length_m = null;
		if ( null !== $length ) {
			$length_m = 'yd' === $unit ? $length * 0.9144 : $length;
		}

		$latitude=isset($data['latitude'])&&''!==trim((string)$data['latitude'])?(float)$data['latitude']:null;$longitude=isset($data['longitude'])&&''!==trim((string)$data['longitude'])?(float)$data['longitude']:null;
		if((null===$latitude)!==(null===$longitude))return new \WP_Error('swimlog_location_coordinates',__('Enter both latitude and longitude, or leave both blank.','chriss-swim-training-progress-evaluation'));
		if(null!==$latitude&&($latitude < -90||$latitude > 90||$longitude < -180||$longitude > 180))return new \WP_Error('swimlog_location_coordinates',__('Location coordinates are outside the valid latitude/longitude range.','chriss-swim-training-progress-evaluation'));

		$now = current_time( 'mysql' );
		$values = array(
			'user_id'       => (int) $user_id,
			'name'          => $name,
			'pool_length'   => $length,
			'pool_unit'     => $unit ?: null,
			'pool_length_m' => null === $length_m ? null : round( $length_m, 3 ),
			'latitude'      => null === $latitude ? null : round($latitude,7),
			'longitude'     => null === $longitude ? null : round($longitude,7),
			'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ),
			'updated_at'    => $now,
		);

		if ( $id ) {
			if ( ! self::get_for_user( $id, $user_id ) ) {
				return new \WP_Error( 'swimlog_location_not_found', __( 'Location not found.', 'chriss-swim-training-progress-evaluation' ) );
			}
			$result = $wpdb->update( $table, $values, array( 'id' => (int) $id, 'user_id' => (int) $user_id ) );
			return false === $result ? new \WP_Error( 'swimlog_location_save', __( 'The location could not be saved.', 'chriss-swim-training-progress-evaluation' ) ) : (int) $id;
		}

		$values['created_at'] = $now;
		$result = $wpdb->insert( $table, $values );
		return false === $result ? new \WP_Error( 'swimlog_location_save', __( 'The location could not be saved.', 'chriss-swim-training-progress-evaluation' ) ) : (int) $wpdb->insert_id;
	}


	public static function nearest_for_user($user_id,$latitude,$longitude,$max_m=500.0){
		$latitude=(float)$latitude;$longitude=(float)$longitude;$max_m=max(1.0,(float)$max_m);$candidates=array();
		foreach(self::all_for_user($user_id)as$location){if(null===$location->latitude||null===$location->longitude)continue;$d=self::distance_m($latitude,$longitude,(float)$location->latitude,(float)$location->longitude);if($d<=$max_m)$candidates[]=array('location'=>$location,'distance_m'=>$d);}
		usort($candidates,function($a,$b){return$a['distance_m']<=>$b['distance_m'];});if(!$candidates)return null;if(isset($candidates[1])&&($candidates[1]['distance_m']-$candidates[0]['distance_m'])<50.0)return null;return$candidates[0];
	}
	public static function distance_m($lat1,$lon1,$lat2,$lon2){$r=6371000.0;$p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=deg2rad($lat2-$lat1);$dl=deg2rad($lon2-$lon1);$a=sin($dp/2)*sin($dp/2)+cos($p1)*cos($p2)*sin($dl/2)*sin($dl/2);return$r*2*atan2(sqrt($a),sqrt(max(0,1-$a)));}

	public static function delete( $id, $user_id ) {
		global $wpdb;
		$table = Database::table( 'locations' );
		if ( ! self::get_for_user( $id, $user_id ) ) {
			return new \WP_Error( 'swimlog_location_not_found', __( 'Location not found.', 'chriss-swim-training-progress-evaluation' ) );
		}
		// Historical workouts retain their pool snapshot. Existing references may
		// retain this ID; display code must tolerate a missing location record.
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id, 'user_id' => (int) $user_id ) );
	}
}
