<?php
/**
 * Simply Send transactional API client + wp_mail interception.
 *
 * @package Simply_Send
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simply_Send_Mailer {

	/**
	 * Singleton instance.
	 *
	 * @var Simply_Send_Mailer|null
	 */
	private static $instance = null;

	/**
	 * Last API error message for admin/test UI.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Get singleton.
	 *
	 * @return Simply_Send_Mailer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		// WP 5.7+: short-circuit wp_mail and send via API.
		add_filter( 'pre_wp_mail', array( $this, 'pre_wp_mail' ), 10, 2 );
	}

	/**
	 * Last error from API send.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Intercept wp_mail when Simply Send is enabled.
	 *
	 * @param null|bool            $return Short-circuit value.
	 * @param array<string, mixed> $atts   wp_mail attributes.
	 * @return null|bool
	 */
	public function pre_wp_mail( $return, $atts ) {
		if ( null !== $return ) {
			return $return;
		}

		$settings = Simply_Send_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return $return;
		}
		if ( ! Simply_Send_Settings::is_ready( $settings ) ) {
			$this->last_error = __( 'Simply Send is enabled but its credentials are missing or cannot be decrypted securely. Re-enter the API key in Settings.', 'simply-send' );
			do_action( 'wp_mail_failed', new WP_Error( 'simply_send_failed', $this->last_error ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress mail-failure hook.
			return false;
		}

		$result = $this->send_from_wp_mail( $atts, $settings );
		if ( ! $result ) {
			do_action( 'wp_mail_failed', new WP_Error( 'simply_send_failed', $this->last_error ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Standard WordPress mail-failure hook.
		}
		return $result;
	}

	/**
	 * Send using wp_mail-style attributes.
	 *
	 * @param array<string, mixed> $atts     wp_mail atts.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return bool
	 */
	public function send_from_wp_mail( $atts, $settings = null ) {
		$settings = $settings ? $settings : Simply_Send_Settings::get();
		$this->last_error = '';

		$to      = isset( $atts['to'] ) ? $atts['to'] : '';
		$subject = isset( $atts['subject'] ) ? (string) $atts['subject'] : '';
		$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';
		$headers = isset( $atts['headers'] ) ? $atts['headers'] : array();
		$files   = isset( $atts['attachments'] ) ? $atts['attachments'] : array();

		$parsed = $this->parse_headers( $headers );

		$from_email = ! empty( $settings['force_from'] ) || empty( $parsed['from_email'] )
			? $settings['from_email']
			: $parsed['from_email'];
		$from_name = ! empty( $settings['force_from'] ) || ( empty( $parsed['from_name'] ) && ! empty( $settings['from_name'] ) )
			? ( ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' ) )
			: ( ! empty( $parsed['from_name'] ) ? $parsed['from_name'] : get_bloginfo( 'name' ) );

		if ( empty( $from_email ) ) {
			$this->last_error = __( 'From email is not configured.', 'simply-send' );
			return false;
		}

		$to_list = $this->normalize_addresses( $to );
		if ( empty( $to_list ) && empty( $parsed['cc'] ) && empty( $parsed['bcc'] ) ) {
			$this->last_error = __( 'No valid recipient address.', 'simply-send' );
			return false;
		}

		$html = $this->build_html( $message, $parsed['content_type'] );
		$text = $this->build_text( $message, $parsed['content_type'] );

		$payload = array(
			'from'    => $this->format_from( $from_name, $from_email ),
			'subject' => $subject,
			'html'    => $html,
		);

		if ( ! empty( $text ) ) {
			$payload['text'] = $text;
		}

		// The public API supports either To/Cc or Bcc, never both.
		if ( ! empty( $parsed['bcc'] ) && ( ! empty( $to_list ) || ! empty( $parsed['cc'] ) ) ) {
			$this->last_error = __( 'Simply Send does not support BCC together with To or CC. No email was sent.', 'simply-send' );
			return false;
		}
		foreach ( array( 'to' => $to_list, 'cc' => $parsed['cc'], 'bcc' => $parsed['bcc'] ) as $field => $addresses ) {
			if ( ! empty( $addresses ) ) {
				$payload[ $field ] = $addresses;
			}
		}

		if ( ! empty( $parsed['reply_to'] ) ) {
			$payload['replyTo'] = $parsed['reply_to'];
		}

		$attachments = $this->build_attachments( $files );
		if ( false === $attachments ) {
			return false;
		}
		if ( ! empty( $attachments ) ) {
			if ( 1 !== count( $to_list ) || ! empty( $parsed['cc'] ) || ! empty( $parsed['bcc'] ) ) {
				$this->last_error = __( 'Attachments require exactly one To recipient and no CC or BCC. No email was sent.', 'simply-send' );
				return false;
			}
			$payload['attachments'] = $attachments;
		}

		return $this->send_api( $payload, $settings );
	}

	/**
	 * POST to Simply Send transactional API.
	 *
	 * @param array<string, mixed> $payload  JSON body.
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public function send_api( $payload, $settings = null ) {
		$settings = $settings ? $settings : Simply_Send_Settings::get();
		$api_key  = Simply_Send_Settings::get_api_key( $settings );

		if ( empty( $api_key ) || empty( $settings['account_id'] ) ) {
			$this->last_error = __( 'API key or Account ID is missing.', 'simply-send' );
			return false;
		}

		$url = Simply_Send_Settings::API_URL;

		$headers = array(
			'Content-Type' => 'application/json',
			'X-Api-Key'    => $api_key,
			'X-Id'         => $settings['account_id'],
			'Idempotency-Key' => wp_generate_uuid4(),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'redirection' => 0,
				'sslverify' => true,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		$message = '';
		if ( is_array( $data ) ) {
			if ( ! empty( $data['message'] ) ) {
				$message = (string) $data['message'];
			} elseif ( ! empty( $data['error'] ) ) {
				$message = is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
			}
		}
		if ( '' === $message ) {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Simply Send API error (HTTP %d).', 'simply-send' ),
				$code
			);
		}

		$this->last_error = $message;
		return false;
	}

	/**
	 * Parse wp_mail headers into structured fields.
	 *
	 * @param string|array<int, string> $headers Headers.
	 * @return array<string, mixed>
	 */
	private function parse_headers( $headers ) {
		$result = array(
			'content_type' => 'text/plain',
			'from_email'   => '',
			'from_name'    => '',
			'reply_to'     => '',
			'cc'           => array(),
			'bcc'          => array(),
		);

		if ( empty( $headers ) ) {
			return $result;
		}

		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}

		foreach ( $headers as $header ) {
			$header = trim( (string) $header );
			if ( '' === $header || false === strpos( $header, ':' ) ) {
				continue;
			}
			list( $name, $value ) = array_map( 'trim', explode( ':', $header, 2 ) );
			$lname = strtolower( $name );

			if ( 'content-type' === $lname ) {
				if ( false !== stripos( $value, 'text/html' ) ) {
					$result['content_type'] = 'text/html';
				}
			} elseif ( 'from' === $lname ) {
				$parsed = $this->parse_address( $value );
				$result['from_email'] = $parsed['email'];
				$result['from_name']  = $parsed['name'];
			} elseif ( 'reply-to' === $lname ) {
				$parsed = $this->parse_address( $value );
				$result['reply_to'] = $parsed['email'] ? $this->format_from( $parsed['name'], $parsed['email'] ) : $value;
			} elseif ( 'cc' === $lname ) {
				$result['cc'] = array_merge( $result['cc'], $this->normalize_addresses( $value ) );
			} elseif ( 'bcc' === $lname ) {
				$result['bcc'] = array_merge( $result['bcc'], $this->normalize_addresses( $value ) );
			}
		}

		return $result;
	}

	/**
	 * Normalize address list to array of email strings.
	 *
	 * @param mixed $addresses Addresses.
	 * @return array<int, string>
	 */
	private function normalize_addresses( $addresses ) {
		if ( empty( $addresses ) ) {
			return array();
		}
		if ( is_array( $addresses ) ) {
			$list = $addresses;
		} else {
			$list = explode( ',', (string) $addresses );
		}

		$out = array();
		foreach ( $list as $item ) {
			$parsed = $this->parse_address( $item );
			if ( ! empty( $parsed['email'] ) && is_email( $parsed['email'] ) ) {
				$out[] = $parsed['name']
					? $this->format_from( $parsed['name'], $parsed['email'] )
					: $parsed['email'];
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Parse "Name <email>" or bare email.
	 *
	 * @param string $value Address string.
	 * @return array{name:string,email:string}
	 */
	private function parse_address( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(.*)<([^>]+)>$/', $value, $m ) ) {
			return array(
				'name'  => trim( $m[1], " \t\"'" ),
				'email' => sanitize_email( $m[2] ),
			);
		}
		return array(
			'name'  => '',
			'email' => sanitize_email( $value ),
		);
	}

	/**
	 * Format From field.
	 *
	 * @param string $name  Display name.
	 * @param string $email Email.
	 * @return string
	 */
	private function format_from( $name, $email ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return $email;
		}
		return sprintf( '%s <%s>', $name, $email );
	}

	/**
	 * Build HTML body and append compliance placeholders.
	 *
	 * @param string $message      Message body.
	 * @param string $content_type Detected content type.
	 * @return string
	 */
	private function build_html( $message, $content_type ) {
		if ( 'text/html' === $content_type ) {
			$html = $message;
		} else {
			$html = '<div>' . nl2br( esc_html( $message ) ) . '</div>';
		}

		$footer = '';
		foreach ( array( '{{company_address_html}}', '{{unsubscribe_email_html}}', '{{report_abuse_email_html}}' ) as $placeholder ) {
			if ( false === strpos( $html, $placeholder ) ) {
				$footer .= '<div>' . $placeholder . '</div>';
			}
		}
		if ( '' !== $footer ) {
			$html .= '<br><br>' . $footer;
		}

		return $html;
	}

	/**
	 * Build plain-text alternative.
	 *
	 * @param string $message      Message body.
	 * @param string $content_type Detected content type.
	 * @return string
	 */
	private function build_text( $message, $content_type ) {
		if ( 'text/html' === $content_type ) {
			$text = wp_strip_all_tags( $message );
		} else {
			$text = $message;
		}
		return trim( $text );
	}

	/**
	 * Convert file-path attachments to API payload objects.
	 *
	 * @param mixed $files Attachment paths.
	 * @return array<int, array<string, string>>|false
	 */
	private function build_attachments( $files ) {
		if ( empty( $files ) ) {
			return array();
		}
		if ( ! is_array( $files ) ) {
			$files = explode( "\n", str_replace( "\r\n", "\n", (string) $files ) );
		}

		if ( count( $files ) > 10 ) {
			$this->last_error = __( 'Simply Send supports at most 10 attachments. No email was sent.', 'simply-send' );
			return false;
		}

		$out = array();
		foreach ( $files as $path ) {
			$path = (string) $path;
			if ( '' === $path || false !== strpos( $path, '://' ) || ! is_file( $path ) || ! is_readable( $path ) ) {
				$this->last_error = __( 'An attachment is not a readable local file. No email was sent.', 'simply-send' );
				return false;
			}
			$contents = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $contents ) {
				$this->last_error = __( 'An attachment could not be read. No email was sent.', 'simply-send' );
				return false;
			}
			$name = basename( $path );
			$type = function_exists( 'mime_content_type' ) ? mime_content_type( $path ) : 'application/octet-stream';
			if ( ! $type ) {
				$type = 'application/octet-stream';
			}
			$out[] = array(
				'name'        => $name,
				'contentType' => $type,
				'content'     => base64_encode( $contents ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}
		return $out;
	}
}
