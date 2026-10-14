<?php
/**
 * Uninstall Simply Send.
 *
 * @package Simply_Send
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'simply_send_settings' );
