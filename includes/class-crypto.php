<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lightweight AES-256-CBC encryption for storing API secrets at rest.
 * Key is derived from WordPress's AUTH_KEY so it's unique per installation.
 */
class SMSentry_Crypto {

	private static function get_key(): string {
		return hash( 'sha256', AUTH_KEY . 'smsentry_v1', true );
	}

	public static function encrypt( string $plaintext ): string {
		if ( empty( $plaintext ) ) {
			return '';
		}

		$key    = self::get_key();
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return base64_encode( $iv . $cipher );
	}

	public static function decrypt( string $ciphertext ): string {
		if ( empty( $ciphertext ) ) {
			return '';
		}

		$data = base64_decode( $ciphertext, true );

		// Not encrypted or corrupted — return as-is (handles legacy plain-text values).
		if ( false === $data || strlen( $data ) < 17 ) {
			return $ciphertext;
		}

		$key    = self::get_key();
		$iv     = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );
		$result = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return false !== $result ? $result : '';
	}
}
