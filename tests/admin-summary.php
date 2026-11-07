<?php
/** Overview rendering and setup navigation checks with dummy WordPress data. */
require __DIR__ . '/regression.php';
define( 'SIMPLY_SEND_PLUGIN_URL', '/plugin/' );
$GLOBALS['can_manage'] = true;
function current_user_can( $capability ) { return $GLOBALS['can_manage']; }
function admin_url( $path ) { return '/wp-admin/' . $path; }
function wp_unslash( $value ) { return $value; }
function check_admin_referer( $action ) { $GLOBALS['checked_nonce'] = $action; }
function esc_html__( $text, $domain ) { return esc_html( $text ); }
function esc_attr__( $text, $domain ) { return esc_html( $text ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_create_nonce( $action ) { return 'dummy-reveal-nonce'; }
function nocache_headers() { $GLOBALS['no_cache'] = true; }
function check_ajax_referer( $action ) { if ( empty( $GLOBALS['valid_ajax_nonce'] ) ) { throw new RuntimeException( 'invalid nonce' ); } $GLOBALS['ajax_nonce_action'] = $action; }
function wp_send_json_success( $data ) { $GLOBALS['ajax_response'] = array( 'success' => true, 'data' => $data ); }
function wp_send_json_error( $data, $status ) { $GLOBALS['ajax_response'] = array( 'success' => false, 'status' => $status ); }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="dummy-nonce" />'; }
function checked( $value, $expected = true ) { if ( $value == $expected ) { echo 'checked'; } }
function disabled( $value, $expected = true ) { if ( $value == $expected ) { echo 'disabled'; } }
class WP_User { public $user_email = 'tester@example.com'; }
function wp_get_current_user() { return new WP_User(); }
require $root . '/includes/class-simply-send-admin.php';
function render_settings() { ob_start(); Simply_Send_Admin::instance()->render_page(); return ob_get_clean(); }
$_GET = $_POST = array();
$GLOBALS['options'][Simply_Send_Settings::OPTION_KEY] = array_merge( $original_settings, array( 'region' => 'ca', 'from_name' => '<img src=x onerror=alert(1)>', 'account_id' => 'summary-account' ) );
$html = render_settings();
check( false !== strpos( $html, 'Your site email, connected.' ) && false === strpos( $html, 'id="simply-send-setup"' ), 'configured site defaults to overview' );
check( false !== strpos( $html, 'Canada' ) && false !== strpos( $html, 'Central' ) && false !== strpos( $html, 'SU••••••••••••T' ), 'overview shows saved regional workspace details' );
check( false === strpos( $html, 'summary-account' ) && false !== strpos( $html, 'Account ID masked' ), 'overview masks account ID without embedding the full value' );
check( false !== strpos( $html, '&lt;img src=x onerror=alert(1)&gt;' ) && false === strpos( $html, '<img src=x' ), 'sender details escaped in overview' );
check( false === strpos( $html, 'test-api-key' ) && false === strpos( $html, $original_settings['api_key'] ), 'overview never exposes plaintext or encrypted key' );
check( 1 === substr_count( $html, 'name="simply_send_test"' ) && false !== strpos( $html, 'name="_wpnonce"' ), 'overview contains one nonce-protected test form' );
check( false !== strpos( $html, 'view=setup' ), 'overview links back to setup' );
$_GET['view'] = 'setup';
$html = render_settings();
check( false !== strpos( $html, 'id="simply-send-setup"' ) && false !== strpos( $html, 'Back to overview' ), 'edit link opens step view with return action' );
check( false !== strpos( $html, 'action="/wp-admin/options-general.php?page=simply-invent-labs-simply-send" id="simply-send-setup"' ), 'saving setup posts to default overview route' );
$_GET = array();
$_POST = array( 'simply_send_save' => 1, 'region' => 'invalid' );
Simply_Send_Admin::instance()->handle_actions();
check( false !== strpos( render_settings(), 'id="simply-send-setup"' ), 'failed save keeps editable setup visible' );
check( 'simply_send_save_settings' === $GLOBALS['checked_nonce'], 'save verifies settings nonce' );
$_POST = array( 'simply_send_save' => 1, 'region' => 'ca', 'enabled' => 1, 'api_key' => '', 'account_id' => 'summary-account', 'from_email' => 'sender@example.com', 'from_name' => '', 'force_from' => 1 );
Simply_Send_Admin::instance()->handle_actions();
$_POST = array();
$html = render_settings();
check( false !== strpos( $html, 'Your site email, connected.' ) && false !== strpos( $html, 'Test Site' ), 'successful save returns overview with site-name fallback' );
$GLOBALS['options'][Simply_Send_Settings::OPTION_KEY]['enabled'] = false;
check( false !== strpos( render_settings(), 'id="simply-send-setup"' ), 'disabled delivery returns to setup' );
$GLOBALS['can_manage'] = false;
check( '' === render_settings(), 'unauthorized users cannot view connection summary' );
$GLOBALS['ajax_response'] = null;
Simply_Send_Admin::instance()->reveal_account_id();
check( 403 === $GLOBALS['ajax_response']['status'] && ! isset( $GLOBALS['ajax_response']['data'] ), 'account reveal denies non-administrators' );
$GLOBALS['can_manage'] = true;
$GLOBALS['ajax_response'] = null;
try { Simply_Send_Admin::instance()->reveal_account_id(); } catch ( RuntimeException $error ) { }
check( null === $GLOBALS['ajax_response'], 'account reveal requires a valid nonce' );
$GLOBALS['valid_ajax_nonce'] = true;
Simply_Send_Admin::instance()->reveal_account_id();
check( true === $GLOBALS['ajax_response']['success'] && array( 'accountId' => 'SUMMARY-ACCOUNT' ) === $GLOBALS['ajax_response']['data'], 'authorized reveal returns uppercase account ID only' );
check( ! empty( $GLOBALS['no_cache'] ) && 'simply_send_reveal_account_id' === $GLOBALS['ajax_nonce_action'], 'account reveal uses no-cache headers and dedicated nonce' );
echo "All admin summary checks passed.\n";
