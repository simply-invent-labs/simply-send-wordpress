<?php
/**
 * Admin settings UI for Simply Send.
 *
 * @package Simply_Send
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simply_Send_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Simply_Send_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Simply_Send_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SIMPLY_SEND_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Settings link on Plugins screen.
	 *
	 * @param array<int, string> $links Existing links.
	 * @return array<int, string>
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=simply-send' );
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'simply-send' ) )
		);
		return $links;
	}

	/**
	 * Register Settings → Simply Send.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Simply Send', 'simply-send' ),
			__( 'Simply Send', 'simply-send' ),
			'manage_options',
			'simply-send',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin CSS on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_simply-send' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'simply-send-admin',
			SIMPLY_SEND_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SIMPLY_SEND_VERSION
		);
	}

	/**
	 * Handle save + test email posts.
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['simply_send_save'] ) ) {
			check_admin_referer( 'simply_send_save_settings' );
			$saved = Simply_Send_Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( false !== $saved ) {
				add_settings_error( 'simply_send', 'simply_send_saved', __( 'Settings saved.', 'simply-send' ), 'updated' );
			}
		}

		if ( isset( $_POST['simply_send_test'] ) ) {
			check_admin_referer( 'simply_send_test_email' );
			$this->send_test_email();
		}

		// WordPress options-head.php renders settings notices before our page.
		if ( isset( $_GET['page'] ) && 'simply-send' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects where to display a notice.
			$settings = Simply_Send_Settings::get();
			if ( ! empty( $settings['api_key'] ) && '' === Simply_Send_Settings::get_api_key( $settings ) ) {
				add_settings_error( 'simply_send', 'simply_send_key_unavailable', __( 'The stored API key cannot be decrypted securely. Re-enter your transactional API key and save before enabling email delivery.', 'simply-send' ), 'error' );
			}
		}
	}

	/**
	 * Send a test message via the API (through wp_mail interception).
	 */
	private function send_test_email() {
		$settings = Simply_Send_Settings::get();
		if ( ! Simply_Send_Settings::is_ready( $settings ) ) {
			add_settings_error(
				'simply_send',
				'simply_send_not_ready',
				__( 'Enable Simply Send and fill in API key, Account ID, and From email before sending a test.', 'simply-send' ),
				'error'
			);
			return;
		}

		$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_email( $to ) ) {
			$user = wp_get_current_user();
			$to   = $user instanceof WP_User ? $user->user_email : '';
		}

		if ( ! is_email( $to ) ) {
			add_settings_error( 'simply_send', 'simply_send_bad_to', __( 'Enter a valid test recipient email.', 'simply-send' ), 'error' );
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[Simply Send] Test email from %s', 'simply-send' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = __( "This is a test email sent through the Simply Send WordPress plugin (API).\n\nIf you received this message, your configuration is working.", 'simply-send' );

		$sent = wp_mail( $to, $subject, $body );
		if ( $sent ) {
			add_settings_error(
				'simply_send',
				'simply_send_test_ok',
				sprintf(
					/* translators: %s: recipient email */
					__( 'Test email sent to %s. Check your inbox (and Simply Send dashboard).', 'simply-send' ),
					esc_html( $to )
				),
				'updated'
			);
		} else {
			$err = Simply_Send_Mailer::instance()->get_last_error();
			add_settings_error(
				'simply_send',
				'simply_send_test_fail',
				$err
					? sprintf(
						/* translators: %s: error message */
						__( 'Test email failed: %s', 'simply-send' ),
						esc_html( $err )
					)
					: __( 'Test email failed. Confirm your transactional API key, Account ID, and From address, then try again.', 'simply-send' ),
				'error'
			);
		}
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Simply_Send_Settings::get();
		$user     = wp_get_current_user();
		$test_to  = $user instanceof WP_User ? $user->user_email : '';
		?>
		<div class="wrap simply-send-wrap">
			<h1><?php echo esc_html__( 'Simply Send', 'simply-send' ); ?></h1>
			<p class="description">
				<?php
				echo esc_html__(
					'Route all WordPress email through the Simply Send transactional API. Create a transactional API key in your Simply Send account, then paste it below with your Account ID.',
					'simply-send'
				);
				?>
			</p>
			<p>
				<a class="button button-secondary" href="https://app.simplysend.email/api-keys" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'Open API Keys', 'simply-send' ); ?>
				</a>
				<a class="button button-secondary" href="https://app.simplysend.email/account" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'Account ID', 'simply-send' ); ?>
				</a>
				<a class="button button-link" href="https://www.simplysend.email/docs/guide/integrations/wordpress-plugin" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'Setup guide', 'simply-send' ); ?>
				</a>
			</p>


			<form method="post" action="">
				<?php wp_nonce_field( 'simply_send_save_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Enable Simply Send', 'simply-send' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
								<?php echo esc_html__( 'Send WordPress email and attachments to Simply Send for delivery', 'simply-send' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="simply_send_api_key"><?php echo esc_html__( 'Transactional API Key', 'simply-send' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="simply_send_api_key" name="api_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $settings['api_key'] ) ? '••••••••' : '' ); ?>" />
							<p class="description"><?php echo esc_html__( 'Create a transactional API key in Simply Send. Leave blank to keep the current key.', 'simply-send' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="simply_send_account_id"><?php echo esc_html__( 'Account ID', 'simply-send' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="simply_send_account_id" name="account_id" value="<?php echo esc_attr( $settings['account_id'] ); ?>" autocomplete="off" />
							<p class="description"><?php echo esc_html__( 'Found on your Simply Send Account page (sent as X-Id).', 'simply-send' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="simply_send_from_email"><?php echo esc_html__( 'From Email', 'simply-send' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="simply_send_from_email" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Must be an address on a verified transactional domain in Simply Send.', 'simply-send' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="simply_send_from_name"><?php echo esc_html__( 'From Name', 'simply-send' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="simply_send_from_name" name="from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Force From Address', 'simply-send' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="force_from" value="1" <?php checked( ! empty( $settings['force_from'] ) ); ?> />
								<?php echo esc_html__( 'Override other plugins that set a different From address', 'simply-send' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="simply_send_api_url"><?php echo esc_html__( 'API URL', 'simply-send' ); ?></label></th>
						<td>
							<code id="simply_send_api_url"><?php echo esc_html( Simply_Send_Settings::API_URL ); ?></code>
							<p class="description"><?php echo esc_html__( 'Default: https://tapi.simplysend.email/send', 'simply-send' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'simply-send' ), 'primary', 'simply_send_save' ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Send a test email', 'simply-send' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'simply_send_test_email' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="simply_send_test_email"><?php echo esc_html__( 'Recipient', 'simply-send' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="simply_send_test_email" name="test_email" value="<?php echo esc_attr( $test_to ); ?>" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Send Test Email', 'simply-send' ), 'secondary', 'simply_send_test', false ); ?>
			</form>
		</div>
		<?php
	}
}
