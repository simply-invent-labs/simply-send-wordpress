<?php
/**
 * Plugin Name:       Simply Invent Labs — Simply Send
 * Plugin URI:        https://www.simplysend.email/integrations/wordpress
 * Description:       Send WordPress email through the Simply Send transactional API — contact forms, notifications, and plugin mail from your verified domain.
 * Version:           1.0.8
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Simply Invent Labs
 * Author URI:        https://simplyinventlabs.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simply-invent-labs-simply-send
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLY_SEND_VERSION', '1.0.8' );
define( 'SIMPLY_SEND_PLUGIN_FILE', __FILE__ );
define( 'SIMPLY_SEND_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLY_SEND_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLY_SEND_PLUGIN_DIR . 'includes/class-simply-send-settings.php';
require_once SIMPLY_SEND_PLUGIN_DIR . 'includes/class-simply-send-mailer.php';
require_once SIMPLY_SEND_PLUGIN_DIR . 'includes/class-simply-send-admin.php';

/**
 * Bootstrap the plugin.
 */
function simply_send_bootstrap() {
	Simply_Send_Mailer::instance()->init();

	if ( is_admin() ) {
		Simply_Send_Admin::instance()->init();
	}
}
add_action( 'plugins_loaded', 'simply_send_bootstrap' );

/**
 * Activation: seed default options if missing.
 */
function simply_send_activate() {
	$defaults = Simply_Send_Settings::defaults();
	$existing = get_option( Simply_Send_Settings::OPTION_KEY, null );
	if ( null === $existing ) {
		add_option( Simply_Send_Settings::OPTION_KEY, $defaults, '', false );
	}
}
register_activation_hook( __FILE__, 'simply_send_activate' );
