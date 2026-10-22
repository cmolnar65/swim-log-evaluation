<?php
namespace SwimLogEvaluation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Secure per-user storage and connection testing for Garmin Connect Personal API credentials. */
final class Garmin_Personal_API {
	const META_KEY = 'swimlog_garmin_personal_api_key';
	const TOKEN_ENDPOINT = 'https://personal.connectapi.garmin.com/personalapitoken-service/personal-api/personal-api-key/token';

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
					'Accept'        => 'application/json',
				),
				'body'        => '',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'swimlog_garmin_request_failed', __( 'Garmin Connect could not be reached. Please try again.', 'chriss-swim-training-progress-evaluation' ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'swimlog_garmin_auth_failed', sprintf( /* translators: %d: HTTP response status. */ __( 'Garmin Connect rejected the connection request (HTTP %d). Check or replace the Personal API key.', 'chriss-swim-training-progress-evaluation' ), $status ) );
		}
		if ( ! is_array( $body ) || empty( $body['access_token'] ) || ! is_string( $body['access_token'] ) ) {
			return new \WP_Error( 'swimlog_garmin_invalid_response', __( 'Garmin Connect responded, but no access token was returned.', 'chriss-swim-training-progress-evaluation' ) );
		}

		return true;
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
