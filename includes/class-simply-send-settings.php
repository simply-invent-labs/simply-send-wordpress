<?php
/**
 * Settings helpers for Simply Send.
 *
 * @package Simply_Send
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simply_Send_Settings {

	const OPTION_KEY = 'simply_send_settings';
	const API_URL = 'https://tapi.simplysend.email/send';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'    => false,
			'api_key'    => '',
			'account_id' => '',
			'api_url'    => self::API_URL,
			'from_email' => '',
			'from_name'  => '',
			'force_from' => true,
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Persist settings (API key left unchanged when blank).
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|false
	 */
	public static function save( $input ) {
		$current = self::get();

		$settings = array(
			'enabled'    => ! empty( $input['enabled'] ),
			'account_id' => isset( $input['account_id'] ) ? sanitize_text_field( $input['account_id'] ) : '',
			'api_url'    => self::API_URL,
			'from_email' => isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '',
			'from_name'  => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
			'force_from' => ! empty( $input['force_from'] ),
		);

		if ( isset( $input['api_key'] ) && '' !== (string) $input['api_key'] ) {
			$encrypted = self::encrypt( sanitize_text_field( (string) $input['api_key'] ) );
			if ( false === $encrypted ) {
				add_settings_error( 'simply_send', 'simply_send_encryption', __( 'Secure API key storage requires OpenSSL with AES-256-GCM. Settings were not saved.', 'simply-send' ), 'error' );
				return false;
			}
			$settings['api_key'] = $encrypted;
		} else {
			$settings['api_key'] = isset( $current['api_key'] ) ? $current['api_key'] : '';
		}

		// Drop legacy SMTP fields if present from earlier installs.
		update_option( self::OPTION_KEY, $settings, false );
		return $settings;
	}

	/**
	 * Whether the plugin is ready to send.
	 *
	 * @param array<string, mixed>|null $settings Optional settings.
	 * @return bool
	 */
	public static function is_ready( $settings = null ) {
		$s = $settings ? $settings : self::get();
		return ! empty( $s['enabled'] )
			&& '' !== self::get_api_key( $s )
			&& ! empty( $s['account_id'] )
			&& ! empty( $s['from_email'] );
	}

	/**
	 * Decrypt stored API key.
	 *
	 * @param array<string, mixed>|null $settings Optional settings.
	 * @return string
	 */
	public static function get_api_key( $settings = null ) {
		$s = $settings ? $settings : self::get();
		if ( empty( $s['api_key'] ) ) {
			return '';
		}
		return self::decrypt( (string) $s['api_key'] );
	}

	/**
	 * Encrypt an API key with authenticated encryption; never store plaintext.
	 *
	 * @param string $plain Plaintext.
	 * @return string|false
	 */
	public static function encrypt( $plain ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			return false;
		}
		try {
			$iv = random_bytes( 12 );
		} catch ( Exception $error ) {
			return false;
		}
		$tag = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::crypto_key(), OPENSSL_RAW_DATA, $iv, $tag, self::OPTION_KEY );
		if ( false === $cipher || 16 !== strlen( $tag ) ) {
			return false;
		}
		return 'ss2:' . base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt authenticated API keys. Older installations must re-enter the key.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		if ( 0 !== strpos( $stored, 'ss2:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::crypto_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), self::OPTION_KEY );
		return false !== $plain ? $plain : '';
	}

	/**
	 * Derive encryption key from WP salts.
	 *
	 * @return string
	 */
	private static function crypto_key() {
		$material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		return hash( 'sha256', $material, true );
	}
}
