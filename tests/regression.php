<?php
/** Local regression checks with a stubbed WordPress API; no network calls. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array();
$GLOBALS['requests'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['salt'] = 'local-test-salt';
function __( $message, $domain = '' ) { return $message; }
function get_option( $name, $default = array() ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = false ) { $GLOBALS['options'][ $name ] = $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_email( $value ) { return trim( $value ); }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_salt( $scheme ) { return $GLOBALS['salt'] . $scheme; }
function add_settings_error( $setting, $code, $message, $type ) { $GLOBALS['errors'][] = $message; }
function get_bloginfo( $field ) { return 'Test Site'; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_generate_uuid4() { return 'local-request-id'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_remote_post( $url, $args ) { $GLOBALS['requests'][] = array( $url, $args ); return array( 'code' => $GLOBALS['response_code'] ?? 200, 'body' => '{"message":"Rejected"}' ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $value ) { return $value['code']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
function do_action( $hook, $value ) { $GLOBALS['actions'][] = array( $hook, $value ); }
class WP_Error { public function __construct( $code, $message ) {} }
$root = getenv( 'SIMPLY_SEND_PLUGIN_ROOT' ) ?: dirname( __DIR__ );
require $root . '/includes/class-simply-send-settings.php';
require $root . '/includes/class-simply-send-mailer.php';
function check( $condition, $name ) {
	if ( ! $condition ) { throw new RuntimeException( $name ); }
	echo "PASS: $name\n";
}
$encrypted = Simply_Send_Settings::encrypt( 'test-api-key' );
$no_openssl = in_array( '--no-openssl', $argv, true );
if ( $no_openssl ) {
	check( false === $encrypted, 'encryption unavailable fails closed' );
	check( false === Simply_Send_Settings::save( array( 'api_key' => 'secret' ) ), 'settings save fails without encryption' );
	check( empty( $GLOBALS['options'] ), 'failed save persists no secret' );
	check( ! empty( $GLOBALS['errors'] ), 'failed save explains requirement' );
	exit;
}
check( is_string( $encrypted ) && 0 === strpos( $encrypted, 'ss2:' ), 'authenticated encrypted format' );
check( 'test-api-key' === Simply_Send_Settings::decrypt( $encrypted ), 'encrypted key round trip' );
$raw = base64_decode( substr( $encrypted, 4 ) );
$raw[15] = chr( ord( $raw[15] ) ^ 1 );
check( '' === Simply_Send_Settings::decrypt( 'ss2:' . base64_encode( $raw ) ), 'tampered key rejected' );
foreach ( array( 'ss2:bad', 'ss1:old', base64_encode( 'legacy' ), 'plaintext' ) as $bad ) {
	check( '' === Simply_Send_Settings::decrypt( $bad ), 'insecure or malformed key rejected' );
}
$GLOBALS['salt'] = 'changed';
check( '' === Simply_Send_Settings::decrypt( $encrypted ), 'changed salts reject key' );
$GLOBALS['salt'] = 'local-test-salt';
$settings = Simply_Send_Settings::save( array( 'enabled' => 1, 'api_key' => 'test-api-key', 'account_id' => 'test-account', 'from_email' => 'sender@example.com', 'force_from' => 1, 'api_url' => 'http://untrusted.example/send' ) );
check( Simply_Send_Settings::API_URL === $settings['api_url'], 'custom endpoint ignored on save' );
$saved = Simply_Send_Settings::save( array( 'api_key' => '', 'account_id' => 'test-account', 'from_email' => 'sender@example.com', 'enabled' => 1 ) );
check( $settings['api_key'] === $saved['api_key'], 'blank key preserves encrypted value' );
$GLOBALS['options'][Simply_Send_Settings::OPTION_KEY] = $settings;
$mailer = Simply_Send_Mailer::instance();
$base = array( 'to' => 'recipient@example.com', 'subject' => 'Test', 'message' => 'Hello' );
$markers = array( '{{company_address_html}}', '{{unsubscribe_email_html}}', '{{report_abuse_email_html}}' );
for ( $mask = 0; $mask < 8; $mask++ ) {
	$message = '<p>Original HTML</p>';
	foreach ( $markers as $index => $marker ) {
		if ( $mask & ( 1 << $index ) ) {
			$message .= '<div>' . $marker . '</div>';
		}
	}
	$GLOBALS['requests'] = array();
	check( $mailer->send_from_wp_mail( array_merge( $base, array( 'message' => $message, 'headers' => array( 'Content-Type: text/html' ) ) ), $settings ), "placeholder combination $mask sends" );
	$payload = json_decode( $GLOBALS['requests'][0][1]['body'], true );
	foreach ( $markers as $marker ) {
		check( 1 === substr_count( $payload['html'], $marker ), "placeholder combination $mask contains $marker exactly once" );
	}
	check( 0 === strpos( $payload['html'], $message ), "placeholder combination $mask preserves original HTML" );
	if ( 7 === $mask ) {
		check( $message === $payload['html'], 'complete footer is unchanged' );
	}
}

$file = tempnam( sys_get_temp_dir(), 'ss-attachment-' );
file_put_contents( $file, 'attachment contents' );
try {
	$cases = array(
		'mixed To/BCC' => array( 'headers' => array( 'Bcc: hidden@example.com' ) ),
		'mixed CC/BCC' => array( 'to' => '', 'headers' => array( 'Cc: cc@example.com', 'Bcc: hidden@example.com' ) ),
		'missing attachment' => array( 'attachments' => array( $file . '-missing' ) ),
		'directory attachment' => array( 'attachments' => array( dirname( $file ) ) ),
		'remote attachment' => array( 'attachments' => array( 'https://example.com/file' ) ),
		'eleven attachments' => array( 'attachments' => array_fill( 0, 11, $file ) ),
		'attachment with BCC' => array( 'to' => '', 'headers' => array( 'Bcc: hidden@example.com' ), 'attachments' => array( $file ) ),
		'attachment with CC' => array( 'headers' => array( 'Cc: cc@example.com' ), 'attachments' => array( $file ) ),
		'attachment with multiple To' => array( 'to' => array( 'one@example.com', 'two@example.com' ), 'attachments' => array( $file ) ),
	);
	foreach ( $cases as $name => $atts ) {
		$GLOBALS['requests'] = array();
		check( false === $mailer->send_from_wp_mail( array_merge( $base, $atts ), $settings ), "$name fails" );
		check( empty( $GLOBALS['requests'] ) && '' !== $mailer->get_last_error(), "$name sends nothing and reports error" );
	}
	foreach ( array(
		'BCC only' => array( 'to' => '', 'headers' => array( 'Bcc: hidden@example.com' ) ),
		'CC only' => array( 'to' => '', 'headers' => array( 'Cc: cc@example.com' ) ),
		'To and CC' => array( 'headers' => array( 'Cc: cc@example.com', 'Reply-To: reply@example.com' ) ),
		'ten attachments' => array( 'attachments' => array_fill( 0, 10, $file ) ),
		'newline attachments' => array( 'attachments' => $file . "\n" . $file ),
	) as $name => $atts ) {
		$GLOBALS['requests'] = array();
		check( $mailer->send_from_wp_mail( array_merge( $base, $atts ), $settings ), "$name accepted" );
		$payload = json_decode( $GLOBALS['requests'][0][1]['body'], true );
		if ( 'BCC only' === $name ) { check( array( 'hidden@example.com' ) === $payload['bcc'] && ! isset( $payload['to'] ), 'BCC stays hidden' ); }
		if ( 'ten attachments' === $name ) { check( 10 === count( $payload['attachments'] ) && 'attachment contents' === base64_decode( $payload['attachments'][0]['content'] ), 'all attachments preserved' ); }
		if ( 'To and CC' === $name ) { check( array( 'cc@example.com' ) === $payload['cc'] && 'reply@example.com' === $payload['replyTo'], 'CC and Reply-To preserved' ); }
	}
	$GLOBALS['requests'] = array();
	$settings['api_url'] = 'http://untrusted.example/send';
	check( $mailer->send_from_wp_mail( $base, $settings ), 'normal send succeeds' );
	list( $url, $args ) = $GLOBALS['requests'][0];
	check( Simply_Send_Settings::API_URL === $url && 0 === $args['redirection'] && true === $args['sslverify'], 'fixed HTTPS endpoint with TLS verification and no redirects' );
	check( 'test-api-key' === $args['headers']['X-Api-Key'], 'key decrypted only for request' );
	$GLOBALS['response_code'] = 302;
	check( false === $mailer->send_from_wp_mail( $base, $settings ), 'redirect response is failure' );
	$GLOBALS['response_code'] = 200;
	$GLOBALS['requests'] = array();
	check( true === $mailer->pre_wp_mail( true, $base ) && false === $mailer->pre_wp_mail( false, $base ) && empty( $GLOBALS['requests'] ), 'earlier mail interception respected' );
	$GLOBALS['options'][Simply_Send_Settings::OPTION_KEY]['api_key'] = 'legacy';
	check( false === $mailer->pre_wp_mail( null, $base ) && empty( $GLOBALS['requests'] ), 'enabled plugin with invalid key does not fall back to PHP mail' );
	check( 'wp_mail_failed' === end( $GLOBALS['actions'] )[0], 'failure reported through WordPress hook' );
	$GLOBALS['options'][Simply_Send_Settings::OPTION_KEY]['enabled'] = false;
	check( null === $mailer->pre_wp_mail( null, $base ), 'disabled plugin leaves mail alone' );
} finally {
	unlink( $file );
}
echo "All regression checks passed.\n";
