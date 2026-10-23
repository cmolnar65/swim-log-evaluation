<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Secure per-user storage and connection testing for Garmin Connect Personal API credentials. */
final class Garmin_Personal_API {
	const META_KEY = 'swimlog_garmin_personal_api_key';
	const TOKEN_ENDPOINT = 'https://personal.connectapi.garmin.com/personalapitoken-service/personal-api/personal-api-key/token';
	const API_BASE = 'https://personal.connectapi.garmin.com/';

	public static function is_configured( $user_id ) {
		return '' !== (string) get_user_meta( (int) $user_id, self::META_KEY, true );
	}

	public static function save_key( $user_id, $api_key ) {
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			return new \WP_Error( 'swimlog_garmin_empty_key', __( 'Enter a Garmin Connect Personal API key.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$encrypted = self::encrypt( $api_key );
		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
		return (bool) update_user_meta( (int) $user_id, self::META_KEY, $encrypted );
	}

	public static function remove_key( $user_id ) {
		delete_user_meta( (int) $user_id, self::META_KEY );
	}

	public static function test_connection( $user_id ) {
		$token = self::access_token( $user_id );
		return is_wp_error( $token ) ? $token : true;
	}

	public static function recent_activities( $user_id, $limit = 50, $start_date = '', $end_date = '', $swimming_only = false ) {
		$token = self::access_token( $user_id );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$limit = max( 1, min( 100, (int) $limit ) );
		$args = array( 'start' => 0, 'limit' => $limit );
		if ( '' !== $start_date ) {
			$args['startDate'] = $start_date;
		}
		if ( '' !== $end_date ) {
			$args['endDate'] = $end_date;
		}
		$url = add_query_arg( $args, self::API_BASE . 'activitylist-service/activities/search/activities' );
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'redirection' => 0,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'swimlog_garmin_activities_request_failed', __( 'Garmin Connect activities could not be reached. Please try again.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'swimlog_garmin_activities_failed', sprintf( /* translators: %d: HTTP response status. */ __( 'Garmin Connect rejected the activity request (HTTP %d).', 'chriss-swim-training-progress-evaluation' ), $status ) );
		}
		$activities = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $activities ) ) {
			return new \WP_Error( 'swimlog_garmin_activities_invalid', __( 'Garmin Connect returned an unexpected activity-list response.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( $swimming_only ) {
			$activities = array_values( array_filter( $activities, static function ( $activity ) {
				return isset( $activity['activityType']['typeKey'] ) && 'lap_swimming' === (string) $activity['activityType']['typeKey'];
			} ) );
		}
		return $activities;
	}


	public static function activity( $user_id, $activity_id ) {
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) {
			return new \WP_Error( 'swimlog_garmin_invalid_activity', __( 'Invalid Garmin activity ID.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$token = self::access_token( $user_id );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$response = wp_remote_get( self::API_BASE . 'activity-service/activity/' . $activity_id, array(
			'timeout' => 15,
			'redirection' => 0,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'swimlog_garmin_activity_request_failed', __( 'Garmin Connect activity details could not be reached. Please try again.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'swimlog_garmin_activity_failed', sprintf( /* translators: %d: HTTP response status. */ __( 'Garmin Connect rejected the activity detail request (HTTP %d).', 'chriss-swim-training-progress-evaluation' ), $status ) );
		}
		$activity = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $activity ) ) {
			return new \WP_Error( 'swimlog_garmin_activity_invalid', __( 'Garmin Connect returned an unexpected activity detail response.', 'chriss-swim-training-progress-evaluation' ) );
		}
		return $activity;
	}


	public static function download_activity_file( $user_id, $activity_id ) {
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) {
			return new \WP_Error( 'swimlog_garmin_invalid_activity', __( 'Invalid Garmin activity ID.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$token = self::access_token( $user_id );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$response = wp_remote_get( self::API_BASE . 'download-service/files/activity/' . $activity_id, array(
			'timeout' => 30,
			'redirection' => 0,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'swimlog_garmin_download_request_failed', __( 'Garmin Connect activity file could not be reached. Please try again.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'swimlog_garmin_download_failed', sprintf( /* translators: %d: HTTP response status. */ __( 'Garmin Connect rejected the activity file request (HTTP %d).', 'chriss-swim-training-progress-evaluation' ), $status ) );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return new \WP_Error( 'swimlog_garmin_download_empty', __( 'Garmin Connect returned an empty activity file.', 'chriss-swim-training-progress-evaluation' ) );
		}
		return array(
			'body' => $body,
			'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
			'content_disposition' => (string) wp_remote_retrieve_header( $response, 'content-disposition' ),
		);
	}


	/**
	 * Download a Garmin activity archive and safely extract its single FIT member
	 * to a temporary file. The caller owns the returned temporary file and must
	 * delete it when finished.
	 */
	public static function prepare_activity_fit( $user_id, $activity_id ) {
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) {
			return new \WP_Error( 'swimlog_garmin_invalid_activity', __( 'Invalid Garmin activity ID.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( ! class_exists( '\\ZipArchive' ) ) {
			return new \WP_Error( 'swimlog_garmin_zip_unavailable', __( 'This server does not provide the ZIP support required to process Garmin activity files.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$download = self::download_activity_file( $user_id, $activity_id );
		if ( is_wp_error( $download ) ) {
			return $download;
		}

		// Keep the diagnostic transport bounded before writing Garmin's archive to disk.
		$archive_body = $download['body'];
		$archive_size = strlen( $archive_body );
		if ( $archive_size > 12582912 ) {
			return new \WP_Error( 'swimlog_garmin_archive_size', __( 'The Garmin activity archive is unexpectedly large and was not processed.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$archive_path = wp_tempnam( 'swimlog-garmin-' . $activity_id . '.zip' );
		if ( ! $archive_path ) {
			return new \WP_Error( 'swimlog_garmin_temp_failed', __( 'A temporary Garmin activity file could not be created.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$fit_path = '';
		$written = file_put_contents( $archive_path, $archive_body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private temporary binary archive.
		unset( $archive_body );
		if ( false === $written || $written !== $archive_size ) {
			wp_delete_file( $archive_path );
			return new \WP_Error( 'swimlog_garmin_temp_failed', __( 'The temporary Garmin activity archive could not be written completely.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $archive_path ) ) {
			wp_delete_file( $archive_path );
			return new \WP_Error( 'swimlog_garmin_archive_invalid', __( 'Garmin Connect returned an invalid activity archive.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$fit_index = -1;
		$fit_name = '';
		$fit_size = 0;
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$stat = $zip->statIndex( $index );
			if ( ! is_array( $stat ) || ! isset( $stat['name'] ) ) {
				$zip->close();
				wp_delete_file( $archive_path );
				return new \WP_Error( 'swimlog_garmin_archive_invalid', __( 'The Garmin activity archive could not be inspected safely.', 'chriss-swim-training-progress-evaluation' ) );
			}
			$member_name = (string) $stat['name'];
			$normalized = str_replace( '\\\\', '/', $member_name );
			if ( '' === $normalized || '/' === substr( $normalized, -1 ) || 0 === strpos( $normalized, '/' ) || preg_match( '#(^|/)\\.\\.(/|$)#', $normalized ) || preg_match( '#^[A-Za-z]:/#', $normalized ) ) {
				$zip->close();
				wp_delete_file( $archive_path );
				return new \WP_Error( 'swimlog_garmin_archive_unsafe', __( 'The Garmin activity archive contains an unsafe file path.', 'chriss-swim-training-progress-evaluation' ) );
			}
			if ( 'fit' === strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) ) ) {
				if ( -1 !== $fit_index ) {
					$zip->close();
					wp_delete_file( $archive_path );
					return new \WP_Error( 'swimlog_garmin_multiple_fit', __( 'The Garmin activity archive contains more than one FIT file and was not processed automatically.', 'chriss-swim-training-progress-evaluation' ) );
				}
				$fit_index = $index;
				$fit_name = $member_name;
				$fit_size = isset( $stat['size'] ) ? (int) $stat['size'] : 0;
			}
		}

		if ( -1 === $fit_index ) {
			$zip->close();
			wp_delete_file( $archive_path );
			return new \WP_Error( 'swimlog_garmin_fit_missing', __( 'The Garmin activity archive does not contain a FIT file.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( $fit_size <= 0 || $fit_size > 10485760 ) {
			$zip->close();
			wp_delete_file( $archive_path );
			return new \WP_Error( 'swimlog_garmin_fit_size', __( 'The Garmin FIT file is empty or exceeds the 10 MB workout-file limit.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$fit_stream = $zip->getStream( $fit_name );
		if ( ! is_resource( $fit_stream ) ) {
			$zip->close();
			wp_delete_file( $archive_path );
			return new \WP_Error( 'swimlog_garmin_fit_extract', __( 'The Garmin FIT file could not be opened from the activity archive.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$fit_path = wp_tempnam( basename( $fit_name ) );
		if ( ! $fit_path ) {
			fclose( $fit_stream );
			$zip->close();
			wp_delete_file( $archive_path );
			return new \WP_Error( 'swimlog_garmin_temp_failed', __( 'A temporary Garmin FIT file could not be created.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$output = fopen( $fit_path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private temporary binary FIT.
		if ( false === $output ) {
			fclose( $fit_stream );
			$zip->close();
			wp_delete_file( $archive_path );
			wp_delete_file( $fit_path );
			return new \WP_Error( 'swimlog_garmin_temp_failed', __( 'The temporary Garmin FIT file could not be opened for writing.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$copied = stream_copy_to_stream( $fit_stream, $output, 10485761 );
		fclose( $output );
		fclose( $fit_stream );
		$zip->close();
		wp_delete_file( $archive_path );
		if ( false === $copied || $copied !== $fit_size || $copied > 10485760 ) {
			wp_delete_file( $fit_path );
			return new \WP_Error( 'swimlog_garmin_fit_extract', __( 'The Garmin FIT file could not be extracted completely and safely.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$header = file_get_contents( $fit_path, false, null, 0, 12 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local private temporary FIT.
		if ( false === $header || strlen( $header ) < 12 || '.FIT' !== substr( $header, 8, 4 ) ) {
			wp_delete_file( $fit_path );
			return new \WP_Error( 'swimlog_garmin_fit_invalid', __( 'The extracted Garmin file is not a valid FIT activity file.', 'chriss-swim-training-progress-evaluation' ) );
		}

		return array(
			'activity_id' => $activity_id,
			'filename'    => $fit_name,
			'tmp_name'    => $fit_path,
			'size'        => $fit_size,
		);
	}


	/**
	 * Perform a read-only Garmin import preflight.
	 *
	 * This deliberately stops before source preservation, import-row changes,
	 * FIT parsing, workout changes, or PB evaluation.
	 */
	public static function preflight_activity_import( $user_id, $activity_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$activity_id = absint( $activity_id );
		if ( ! $user_id || ! $activity_id ) {
			return new \WP_Error( 'swimlog_garmin_invalid_activity', __( 'Invalid Garmin activity import request.', 'chriss-swim-training-progress-evaluation' ) );
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		$imports = Database::table( 'imports' );
		$external_id = (string) $activity_id;
		$existing_activity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, workout_id, original_filename, file_hash, import_status FROM $imports WHERE user_id=%d AND source_provider=%s AND external_activity_id=%s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				'garmin',
				$external_id
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $existing_activity ) {
			return array(
				'status'       => 'activity_exists',
				'activity_id'  => $activity_id,
				'import_id'    => (int) $existing_activity->id,
				'workout_id'   => (int) $existing_activity->workout_id,
				'filename'     => (string) $existing_activity->original_filename,
				'file_hash'    => (string) $existing_activity->file_hash,
				'import_status'=> (string) $existing_activity->import_status,
			);
		}

		$prepared = self::prepare_activity_fit( $user_id, $activity_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$fit_path = $prepared['tmp_name'];
		try {
			$hash = hash_file( 'sha256', $fit_path );
			if ( false === $hash || '' === $hash ) {
				return new \WP_Error( 'swimlog_garmin_hash_failed', __( 'The Garmin FIT file could not be fingerprinted safely.', 'chriss-swim-training-progress-evaluation' ) );
			}
			$existing_file = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, workout_id, source_type, source_provider, external_activity_id, original_filename, import_status FROM $imports WHERE user_id=%d AND file_hash=%s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$hash
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( $existing_file ) {
				return array(
					'status'                => 'file_exists',
					'activity_id'           => $activity_id,
					'filename'              => (string) $prepared['filename'],
					'file_hash'             => $hash,
					'file_size'             => (int) $prepared['size'],
					'existing_import_id'    => (int) $existing_file->id,
					'existing_workout_id'   => (int) $existing_file->workout_id,
					'existing_source_type'  => (string) $existing_file->source_type,
					'existing_provider'     => (string) $existing_file->source_provider,
					'existing_external_id'  => (string) $existing_file->external_activity_id,
					'existing_filename'     => (string) $existing_file->original_filename,
					'existing_import_status'=> (string) $existing_file->import_status,
				);
			}

			return array(
				'status'      => 'new_candidate',
				'activity_id' => $activity_id,
				'filename'    => (string) $prepared['filename'],
				'file_hash'   => $hash,
				'file_size'   => (int) $prepared['size'],
			);
		} finally {
			if ( is_file( $fit_path ) ) {
				wp_delete_file( $fit_path );
			}
		}
	}


	/**
	 * Associate a Garmin activity with an existing import only when the
	 * downloaded Garmin FIT is byte-identical to that import's file hash.
	 *
	 * No workout, source file, normalized swim data, or PB data is changed.
	 */
	public static function associate_existing_activity( $user_id, $activity_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$activity_id = absint( $activity_id );
		if ( ! $user_id || ! $activity_id ) {
			return new \WP_Error( 'swimlog_garmin_invalid_activity', __( 'Invalid Garmin activity association request.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$preflight = self::preflight_activity_import( $user_id, $activity_id );
		if ( is_wp_error( $preflight ) ) {
			return $preflight;
		}
		if ( 'activity_exists' === $preflight['status'] ) {
			return array(
				'status'      => 'already_associated',
				'activity_id' => $activity_id,
				'import_id'   => (int) $preflight['import_id'],
				'workout_id'  => (int) $preflight['workout_id'],
			);
		}
		if ( 'file_exists' !== $preflight['status'] ) {
			return array(
				'status'      => 'new_candidate',
				'activity_id' => $activity_id,
				'filename'    => (string) $preflight['filename'],
				'file_hash'   => (string) $preflight['file_hash'],
				'file_size'   => (int) $preflight['file_size'],
			);
		}

		$existing_provider = trim( (string) $preflight['existing_provider'] );
		$existing_external = trim( (string) $preflight['existing_external_id'] );
		if ( '' !== $existing_provider && 'garmin' !== $existing_provider ) {
			return new \WP_Error( 'swimlog_garmin_provider_conflict', __( 'The matching import is already associated with another source provider. No data was changed.', 'chriss-swim-training-progress-evaluation' ) );
		}
		if ( '' !== $existing_external && (string) $activity_id !== $existing_external ) {
			return new \WP_Error( 'swimlog_garmin_activity_conflict', __( 'The matching import is already associated with a different external activity. No data was changed.', 'chriss-swim-training-progress-evaluation' ) );
		}

		require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
		$imports = Database::table( 'imports' );
		$conflicting_activity = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $imports WHERE user_id=%d AND source_provider=%s AND external_activity_id=%s AND id<>%d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				'garmin',
				(string) $activity_id,
				(int) $preflight['existing_import_id']
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $conflicting_activity ) {
			return new \WP_Error( 'swimlog_garmin_activity_conflict', __( 'This Garmin activity is already associated with a different import. No data was changed.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$updated = $wpdb->update(
			$imports,
			array(
				'source_provider'      => 'garmin',
				'external_activity_id' => (string) $activity_id,
				'updated_at'           => current_time( 'mysql' ),
			),
			array(
				'id'      => (int) $preflight['existing_import_id'],
				'user_id' => $user_id,
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $updated ) {
			return new \WP_Error( 'swimlog_garmin_association_failed', __( 'The Garmin activity association could not be saved. No workout data was changed.', 'chriss-swim-training-progress-evaluation' ) );
		}

		return array(
			'status'      => 'associated',
			'activity_id' => $activity_id,
			'import_id'   => (int) $preflight['existing_import_id'],
			'workout_id'  => (int) $preflight['existing_workout_id'],
			'filename'    => (string) $preflight['filename'],
		);
	}


	/**
	 * Import a genuinely new Garmin lap-swimming FIT through the existing
	 * normalized FIT importer. Exact-hash duplicates are associated safely.
	 */
	public static function import_activity( $user_id, $activity_id ) {
		$user_id = absint( $user_id );
		$activity_id = absint( $activity_id );
		if ( ! $user_id || ! $activity_id ) {
			return new \WP_Error( 'swimlog_garmin_invalid_activity', __( 'Invalid Garmin activity import request.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$existing = self::associate_existing_activity( $user_id, $activity_id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		if ( 'new_candidate' !== $existing['status'] ) {
			return $existing;
		}

		$prepared = self::prepare_activity_fit( $user_id, $activity_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$fit_path = (string) $prepared['tmp_name'];
		try {
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-database.php';
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-location.php';
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-fit-importer.php';
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-csv-importer.php';
			require_once SWIMLOG_EVALUATION_DIR . 'includes/class-importer.php';
			$result = Importer::import_trusted_fit(
				$user_id,
				$fit_path,
				(string) $prepared['filename'],
				0,
				'garmin',
				(string) $activity_id
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! empty( $result['pending'] ) ) {
				return array(
					'status'                => 'pending_review',
					'activity_id'           => $activity_id,
					'import_id'             => (int) $result['import_id'],
					'candidate_workout_id'  => (int) $result['candidate_workout_id'],
					'filename'              => (string) $prepared['filename'],
				);
			}
			return array(
				'status'      => 'imported',
				'activity_id' => $activity_id,
				'import_id'   => (int) $result['import_id'],
				'workout_id'  => (int) $result['workout_id'],
				'filename'    => (string) $prepared['filename'],
			);
		} finally {
			if ( is_file( $fit_path ) ) {
				wp_delete_file( $fit_path );
			}
		}
	}

	private static function access_token( $user_id ) {
		$api_key = self::get_key( $user_id );
		if ( is_wp_error( $api_key ) ) {
			return $api_key;
		}
		if ( '' === $api_key ) {
			return new \WP_Error( 'swimlog_garmin_not_configured', __( 'No Garmin Connect Personal API key is configured.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'ApiKey ' . $api_key,
				),
				'body'        => '',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'swimlog_garmin_request_failed', __( 'Garmin Connect could not be reached. Please try again.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$token  = trim( wp_remote_retrieve_body( $response ) );
		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'swimlog_garmin_auth_failed', sprintf( /* translators: %d: HTTP response status. */ __( 'Garmin Connect rejected the connection request (HTTP %d). Check or replace the Personal API key.', 'chriss-swim-training-progress-evaluation' ), $status ) );
		}
		if ( '' === $token || 2 !== substr_count( $token, '.' ) ) {
			return new \WP_Error( 'swimlog_garmin_invalid_response', __( 'Garmin Connect responded, but no valid access token was returned.', 'chriss-swim-training-progress-evaluation' ) );
		}

		return $token;
	}

	private static function get_key( $user_id ) {
		$stored = (string) get_user_meta( (int) $user_id, self::META_KEY, true );
		if ( '' === $stored ) {
			return '';
		}
		return self::decrypt( $stored );
	}

	private static function encryption_key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	private static function encrypt( $plaintext ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'random_bytes' ) ) {
			return new \WP_Error( 'swimlog_garmin_crypto_unavailable', __( 'This server does not provide the cryptography functions required to store the Garmin API key securely.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$iv = random_bytes( 12 );
		$tag = '';
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $ciphertext ) {
			return new \WP_Error( 'swimlog_garmin_encrypt_failed', __( 'The Garmin API key could not be encrypted.', 'chriss-swim-training-progress-evaluation' ) );
		}
		return base64_encode( wp_json_encode( array(
			'v' => 1,
			'iv' => base64_encode( $iv ),
			'tag' => base64_encode( $tag ),
			'data' => base64_encode( $ciphertext ),
		) ) );
	}

	private static function decrypt( $stored ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new \WP_Error( 'swimlog_garmin_crypto_unavailable', __( 'This server does not provide the cryptography functions required to read the Garmin API key securely.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$decoded = base64_decode( $stored, true );
		$payload = false === $decoded ? null : json_decode( $decoded, true );
		if ( ! is_array( $payload ) || 1 !== (int) ( $payload['v'] ?? 0 ) || empty( $payload['iv'] ) || empty( $payload['tag'] ) || empty( $payload['data'] ) ) {
			return new \WP_Error( 'swimlog_garmin_invalid_credential', __( 'The stored Garmin API credential is invalid. Remove it and save a new key.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$iv = base64_decode( $payload['iv'], true );
		$tag = base64_decode( $payload['tag'], true );
		$ciphertext = base64_decode( $payload['data'], true );
		if ( false === $iv || false === $tag || false === $ciphertext ) {
			return new \WP_Error( 'swimlog_garmin_invalid_credential', __( 'The stored Garmin API credential is invalid. Remove it and save a new key.', 'chriss-swim-training-progress-evaluation' ) );
		}
		$plaintext = openssl_decrypt( $ciphertext, 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $plaintext ) {
			return new \WP_Error( 'swimlog_garmin_decrypt_failed', __( 'The stored Garmin API credential could not be decrypted. Remove it and save a new key.', 'chriss-swim-training-progress-evaluation' ) );
		}
		return $plaintext;
	}
}
