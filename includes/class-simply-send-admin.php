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

	/** @var bool Keep the setup form visible if saving fails. */
	private $save_failed = false;

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
		add_action( 'wp_ajax_simply_send_reveal_account_id', array( $this, 'reveal_account_id' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SIMPLY_SEND_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}


	/**
	 * Reveal only the account identifier to an authorized administrator.
	 */
	public function reveal_account_id() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Administrator access is required.', 'simply-invent-labs-simply-send' ) ), 403 );
			return;
		}
		check_ajax_referer( 'simply_send_reveal_account_id' );
		nocache_headers();
		$settings = Simply_Send_Settings::get();
		wp_send_json_success( array( 'accountId' => $settings['account_id'] ) );
	}

	/**
	 * Settings link on Plugins screen.
	 *
	 * @param array<int, string> $links Existing links.
	 * @return array<int, string>
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=simply-invent-labs-simply-send' );
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'simply-invent-labs-simply-send' ) )
		);
		return $links;
	}

	/**
	 * Register Settings → Simply Send.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Simply Invent Labs — Simply Send', 'simply-invent-labs-simply-send' ),
			__( 'Simply Send', 'simply-invent-labs-simply-send' ),
			'manage_options',
			'simply-invent-labs-simply-send',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin CSS on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_simply-invent-labs-simply-send' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'simply-send-admin',
			SIMPLY_SEND_PLUGIN_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			SIMPLY_SEND_VERSION . '.' . substr( hash_file( 'sha256', SIMPLY_SEND_PLUGIN_DIR . 'assets/css/admin.css' ), 0, 8 )
		);
		wp_enqueue_script(
			'simply-send-admin',
			SIMPLY_SEND_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			SIMPLY_SEND_VERSION . '.' . substr( hash_file( 'sha256', SIMPLY_SEND_PLUGIN_DIR . 'assets/js/admin.js' ), 0, 8 ),
			true
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
			$this->save_failed = false === $saved;
			if ( false !== $saved ) {
				add_settings_error( 'simply_send', 'simply_send_saved', __( 'Settings saved.', 'simply-invent-labs-simply-send' ), 'updated' );
			}
		}

		if ( isset( $_POST['simply_send_test'] ) ) {
			check_admin_referer( 'simply_send_test_email' );
			$this->send_test_email();
		}

		// WordPress options-head.php renders settings notices before our page.
		if ( isset( $_GET['page'] ) && 'simply-invent-labs-simply-send' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects where to display a notice.
			$settings = Simply_Send_Settings::get();
			if ( ! empty( $settings['api_key'] ) && '' === Simply_Send_Settings::get_api_key( $settings ) ) {
				add_settings_error( 'simply_send', 'simply_send_key_unavailable', __( 'The stored API key cannot be decrypted securely. Re-enter your transactional API key and save before enabling email delivery.', 'simply-invent-labs-simply-send' ), 'error' );
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
				__( 'Enable Simply Send and fill in API key, Account ID, and From email before sending a test.', 'simply-invent-labs-simply-send' ),
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
			add_settings_error( 'simply_send', 'simply_send_bad_to', __( 'Enter a valid test recipient email.', 'simply-invent-labs-simply-send' ), 'error' );
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[Simply Send] Test email from %s', 'simply-invent-labs-simply-send' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = __( "This is a test email sent through the Simply Send WordPress plugin (API).\n\nIf you received this message, your configuration is working.", 'simply-invent-labs-simply-send' );

		$sent = wp_mail( $to, $subject, $body );
		if ( $sent ) {
			add_settings_error(
				'simply_send',
				'simply_send_test_ok',
				sprintf(
					/* translators: %s: recipient email */
					__( 'Test email sent to %s. Check your inbox (and Simply Send dashboard).', 'simply-invent-labs-simply-send' ),
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
						__( 'Test email failed: %s', 'simply-invent-labs-simply-send' ),
						esc_html( $err )
					)
					: __( 'Test email failed. Confirm your transactional API key, Account ID, and From address, then try again.', 'simply-invent-labs-simply-send' ),
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

		$settings       = Simply_Send_Settings::get();
		$user           = wp_get_current_user();
		$test_to        = $user instanceof WP_User ? $user->user_email : '';
		$has_key        = '' !== Simply_Send_Settings::get_api_key( $settings );
		$has_account    = $has_key && ! empty( $settings['account_id'] );
		$has_sender     = $has_account && is_email( $settings['from_email'] );
		$ready          = Simply_Send_Settings::is_ready( $settings );
		$current_step   = ! $has_account ? 2 : ( ! $has_sender ? 3 : 4 );
		$regions        = Simply_Send_Settings::regions();
		$region         = is_string( $settings['region'] ) && isset( $regions[ $settings['region'] ] ) ? $regions[ $settings['region'] ] : null;
		$portal         = $region ? $region['portal'] : '';
		$settings_url   = admin_url( 'options-general.php?page=simply-invent-labs-simply-send' );
		$edit_setup     = $this->save_failed || ( isset( $_GET['view'] ) && is_string( $_GET['view'] ) && 'setup' === sanitize_text_field( wp_unslash( $_GET['view'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
		if ( ! $region ) {
			$current_step = 1;
		}
		?>
		<div class="wrap simply-send-wrap">
			<header class="ss-header">
				<div class="ss-brand"><img src="<?php echo esc_url( SIMPLY_SEND_PLUGIN_URL . 'assets/images/simply-send-logo.png' ); ?>" alt="" width="48" height="48" /><span><?php echo esc_html__( 'Simply Send', 'simply-invent-labs-simply-send' ); ?><small class="ss-publisher"><?php echo esc_html__( 'by Simply Invent Labs', 'simply-invent-labs-simply-send' ); ?></small></span></div>
				<a class="ss-help" href="https://www.simplysend.dev/docs/guide/integrations/wordpress-plugin" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-book" aria-hidden="true"></span><span><?php echo esc_html__( 'Setup guide', 'simply-invent-labs-simply-send' ); ?></span><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
			</header>
			<?php if ( $ready && ! $edit_setup ) : ?>
				<?php $this->render_summary( $settings, $region, $test_to, $settings_url ); ?>
			<?php else : ?>
			<?php if ( $ready ) : ?><div class="ss-actions"><a class="ss-button ss-button-secondary" href="<?php echo esc_url( $settings_url ); ?>"><span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span><span><?php echo esc_html__( 'Back to overview', 'simply-invent-labs-simply-send' ); ?></span></a></div><?php endif; ?>
			<div class="ss-intro">
				<div><p class="ss-eyebrow"><span><?php echo esc_html__( 'WORDPRESS INTEGRATION', 'simply-invent-labs-simply-send' ); ?></span></p><h1><?php echo esc_html__( 'Give your site email a reliable route.', 'simply-invent-labs-simply-send' ); ?></h1><p><span><?php echo esc_html__( 'Connect your account, choose your sender, and send your first test email.', 'simply-invent-labs-simply-send' ); ?></span></p></div>
				<span class="ss-status <?php echo $ready ? 'ss-status-ready' : ''; ?>"><span class="ss-status-dot" aria-hidden="true"></span><span><?php echo $ready ? esc_html__( 'Sending enabled', 'simply-invent-labs-simply-send' ) : esc_html__( 'Setup in progress', 'simply-invent-labs-simply-send' ); ?></span></span>
			</div>
			<nav class="ss-progress" aria-label="<?php echo esc_attr__( 'Setup progress', 'simply-invent-labs-simply-send' ); ?>">
				<a href="#ss-region"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span><span><?php echo esc_html__( 'Region', 'simply-invent-labs-simply-send' ); ?></span></a><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				<a href="#ss-account"><span class="dashicons <?php echo $has_account ? 'dashicons-yes-alt' : 'dashicons-admin-network'; ?>" aria-hidden="true"></span><span><?php echo esc_html__( 'Account', 'simply-invent-labs-simply-send' ); ?></span></a><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				<a href="#ss-sender"><span class="dashicons <?php echo $has_sender ? 'dashicons-yes-alt' : 'dashicons-email-alt'; ?>" aria-hidden="true"></span><span><?php echo esc_html__( 'Sender', 'simply-invent-labs-simply-send' ); ?></span></a><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				<a href="#ss-delivery"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span><span><?php echo esc_html__( 'Delivery test', 'simply-invent-labs-simply-send' ); ?></span></a>
			</nav>
			<div class="ss-workflow">
				<form method="post" action="<?php echo esc_url( $settings_url ); ?>" id="simply-send-setup" data-continue-label="<?php echo esc_attr__( 'Continue', 'simply-invent-labs-simply-send' ); ?>" data-account-summary="<?php echo esc_attr__( 'API credentials entered', 'simply-invent-labs-simply-send' ); ?>" data-delivery-summary="<?php echo esc_attr__( 'Sending enabled', 'simply-invent-labs-simply-send' ); ?>">
					<?php wp_nonce_field( 'simply_send_save_settings' ); ?>
					<section id="ss-region" class="ss-step <?php echo $region ? 'ss-step-complete' : ''; ?>">
						<span class="ss-step-number" aria-hidden="true"><?php echo $region ? '&#10003;' : '1'; ?></span>
						<div class="ss-step-heading"><div><h2><?php echo esc_html__( 'Choose your workspace region', 'simply-invent-labs-simply-send' ); ?></h2><p><span><?php echo esc_html__( 'Select the region where your Simply Send workspace is located.', 'simply-invent-labs-simply-send' ); ?></span></p></div></div>
						<fieldset class="ss-region-field" id="simply_send_region" data-saved-region="<?php echo esc_attr( $settings['region'] ); ?>" aria-describedby="ss-region-help">
							<legend><?php echo esc_html__( 'Workspace region', 'simply-invent-labs-simply-send' ); ?></legend>
							<div class="ss-region-cards">
								<?php foreach ( $regions as $id => $config ) : ?>
									<label class="ss-region-choice">
										<input type="radio" name="region" value="<?php echo esc_attr( $id ); ?>" data-portal="<?php echo esc_url( $config['portal'] ); ?>" data-api-url="<?php echo esc_url( $config['api_url'] ); ?>" <?php checked( $settings['region'], $id ); ?> required />
										<span class="ss-region-card"><span class="ss-region-flag" aria-hidden="true"><?php echo esc_html( $config['flag'] ); ?></span><span class="ss-region-country"><?php echo esc_html( $config['country'] ); ?></span><span class="ss-region-location"><?php echo esc_html( $config['location'] ); ?></span><span class="ss-region-host"><?php echo esc_html( str_replace( '.email', '.dev', wp_parse_url( $config['portal'], PHP_URL_HOST ) ) ); ?></span><span class="ss-region-check" aria-hidden="true"></span></span>
									</label>
								<?php endforeach; ?>
							</div>
							<p id="ss-region-help"><span><?php echo esc_html__( 'Your API key, Account ID, and verified sender must belong to this region. Changing regions requires entering the selected region’s credentials.', 'simply-invent-labs-simply-send' ); ?></span></p>
						</fieldset>
						<p id="ss-region-unsaved" class="ss-region-notice" role="status" hidden><span><?php echo esc_html__( 'Region changed. Enter the credentials and sender for this region, then save your setup before sending a test. Site email continues using the saved setup until you save.', 'simply-invent-labs-simply-send' ); ?></span></p>
					</section>
					<section id="ss-account" class="ss-step <?php echo $has_account ? 'ss-step-complete' : ''; ?>" <?php if ( 2 === $current_step ) { echo 'aria-current="step"'; } ?>>
						<span class="ss-step-number" aria-hidden="true"><?php echo $has_account ? '&#10003;' : '2'; ?></span>
						<div class="ss-step-heading"><div><h2><?php echo esc_html__( 'Connect your Simply Send account', 'simply-invent-labs-simply-send' ); ?></h2><p><span><?php echo esc_html__( 'Use a transactional API key and the Account ID from your dashboard.', 'simply-invent-labs-simply-send' ); ?></span></p></div><?php if ( $has_account ) : ?><span class="ss-step-badge"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span><?php echo esc_html__( 'Credentials saved', 'simply-invent-labs-simply-send' ); ?></span></span><?php endif; ?></div>
						<div class="ss-actions"><a class="ss-button ss-button-secondary" data-region-link="/api-keys" href="<?php echo esc_url( $portal . '/api-keys' ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span><?php echo esc_html__( 'Create an API key', 'simply-invent-labs-simply-send' ); ?></span><span class="dashicons dashicons-external" aria-hidden="true"></span></a><span class="ss-action-note"><?php echo esc_html__( 'Already have one? Enter it below.', 'simply-invent-labs-simply-send' ); ?></span></div>
						<div class="ss-fields">
							<div class="ss-field"><label for="simply_send_api_key"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span><span><?php echo esc_html__( 'Transactional API key', 'simply-invent-labs-simply-send' ); ?></span></label><div class="ss-key-control"><input type="password" id="simply_send_api_key" name="api_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_key ? '••••••••••••••••' : __( 'Paste your transactional API key', 'simply-invent-labs-simply-send' ) ); ?>" aria-describedby="ss-key-help" <?php if ( ! $has_key ) { echo 'required'; } ?> /><button type="button" class="ss-button ss-button-secondary ss-key-toggle" aria-controls="simply_send_api_key" aria-pressed="false" aria-label="<?php echo esc_attr__( 'Show API key', 'simply-invent-labs-simply-send' ); ?>" data-show="<?php echo esc_attr__( 'Show', 'simply-invent-labs-simply-send' ); ?>" data-hide="<?php echo esc_attr__( 'Hide', 'simply-invent-labs-simply-send' ); ?>" data-show-label="<?php echo esc_attr__( 'Show API key', 'simply-invent-labs-simply-send' ); ?>" data-hide-label="<?php echo esc_attr__( 'Hide API key', 'simply-invent-labs-simply-send' ); ?>" hidden><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="ss-key-toggle-text"><?php echo esc_html__( 'Show', 'simply-invent-labs-simply-send' ); ?></span></button></div><p id="ss-key-help"><span><?php echo $has_key ? esc_html__( 'Your key is stored securely. Leave blank to keep it, or paste a replacement.', 'simply-invent-labs-simply-send' ) : esc_html__( 'Stored with encryption. SMTP usernames and passwords do not work here.', 'simply-invent-labs-simply-send' ); ?></span></p></div>
							<div class="ss-field"><label for="simply_send_account_id"><span class="dashicons dashicons-id" aria-hidden="true"></span><span><?php echo esc_html__( 'Account ID', 'simply-invent-labs-simply-send' ); ?></span></label><input type="text" id="simply_send_account_id" name="account_id" value="<?php echo esc_attr( $settings['account_id'] ); ?>" autocomplete="off" placeholder="<?php echo esc_attr__( 'Paste your Account ID', 'simply-invent-labs-simply-send' ); ?>" required aria-describedby="ss-account-help" /><p id="ss-account-help"><span><a data-region-link="/account" href="<?php echo esc_url( $portal . '/account' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Find your Account ID', 'simply-invent-labs-simply-send' ); ?></a><?php echo esc_html__( ' in the Simply Send dashboard.', 'simply-invent-labs-simply-send' ); ?></span></p></div>
						</div>
					</section>
					<section id="ss-sender" class="ss-step <?php echo $has_sender ? 'ss-step-complete' : ''; ?>" <?php if ( 3 === $current_step ) { echo 'aria-current="step"'; } ?>>
						<span class="ss-step-number" aria-hidden="true"><?php echo $has_sender ? '&#10003;' : '3'; ?></span>
						<div class="ss-step-heading"><div><h2><?php echo esc_html__( 'Configure your sender', 'simply-invent-labs-simply-send' ); ?></h2><p><span><?php echo esc_html__( 'Choose the name and verified email address your recipients will see.', 'simply-invent-labs-simply-send' ); ?></span></p></div><?php if ( $has_sender ) : ?><span class="ss-step-badge"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span><?php echo esc_html__( 'Sender saved', 'simply-invent-labs-simply-send' ); ?></span></span><?php endif; ?></div>
						<div class="ss-fields">
							<div class="ss-field"><label for="simply_send_from_email"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span><span><?php echo esc_html__( 'From email', 'simply-invent-labs-simply-send' ); ?></span></label><input type="email" id="simply_send_from_email" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>" placeholder="hello@yourdomain.com" required aria-describedby="ss-from-help" /><p id="ss-from-help"><span><?php echo esc_html__( 'Use an address on a verified transactional domain.', 'simply-invent-labs-simply-send' ); ?></span></p></div>
							<div class="ss-field"><label for="simply_send_from_name"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span><?php echo esc_html__( 'Sender Name', 'simply-invent-labs-simply-send' ); ?></span><span class="ss-optional"><?php echo esc_html__( 'Optional', 'simply-invent-labs-simply-send' ); ?></span></label><input type="text" id="simply_send_from_name" name="from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" /></div>
						</div>
						<label class="ss-checkbox"><input type="checkbox" name="force_from" value="1" <?php checked( ! empty( $settings['force_from'] ) ); ?> /><span><strong><?php echo esc_html__( 'Use this sender for site emails', 'simply-invent-labs-simply-send' ); ?></strong><span><?php echo esc_html__( 'Override sender names and addresses set by other plugins.', 'simply-invent-labs-simply-send' ); ?></span></span></label>
					</section>
					<section id="ss-delivery" class="ss-step <?php echo $ready ? 'ss-step-complete' : ''; ?>" <?php if ( 4 === $current_step ) { echo 'aria-current="step"'; } ?>>
						<span class="ss-step-number" aria-hidden="true"><?php echo $ready ? '&#10003;' : '4'; ?></span>
						<div class="ss-step-heading"><div><h2><?php echo esc_html__( 'Turn on delivery and test it', 'simply-invent-labs-simply-send' ); ?></h2><p><span><?php echo esc_html__( 'Save your setup, then send a test to an inbox you can check.', 'simply-invent-labs-simply-send' ); ?></span></p></div></div>
						<label class="ss-checkbox ss-delivery-toggle"><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /><span><strong><?php echo esc_html__( 'Send site email through Simply Send', 'simply-invent-labs-simply-send' ); ?></strong><span><?php echo esc_html__( 'Send message content, recipients, and attachments to Simply Send for delivery. Disable other mail-routing plugins first.', 'simply-invent-labs-simply-send' ); ?></span></span></label>
						<div class="ss-actions"><button type="submit" name="simply_send_save" value="1" class="ss-button ss-button-primary"><span class="dashicons dashicons-saved" aria-hidden="true"></span><span><?php echo $ready ? esc_html__( 'Save changes', 'simply-invent-labs-simply-send' ) : esc_html__( 'Complete setup', 'simply-invent-labs-simply-send' ); ?></span></button><span class="ss-action-note"><?php echo esc_html__( 'Sending uses your Simply Send plan.', 'simply-invent-labs-simply-send' ); ?></span></div>
					</section>
				</form>
				<div class="ss-test-panel">
					<div class="ss-test-title"><span class="dashicons dashicons-email" aria-hidden="true"></span><div><h3><?php echo esc_html__( 'Send your first test email', 'simply-invent-labs-simply-send' ); ?></h3><p><span><?php echo $ready ? esc_html__( 'Confirm receipt in your inbox and check email activity in Simply Send.', 'simply-invent-labs-simply-send' ) : esc_html__( 'Complete your setup, enable delivery, and save before sending a test.', 'simply-invent-labs-simply-send' ); ?></span></p></div></div>
					<form method="post" action="" class="ss-test-form">
						<?php wp_nonce_field( 'simply_send_test_email' ); ?>
						<div class="ss-field"><label for="simply_send_test_email"><span><?php echo esc_html__( 'Recipient', 'simply-invent-labs-simply-send' ); ?></span></label><input type="email" id="simply_send_test_email" name="test_email" value="<?php echo esc_attr( $test_to ); ?>" required /></div>
						<button type="submit" name="simply_send_test" value="1" class="ss-button ss-button-secondary" <?php disabled( ! $ready ); ?>><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span><span><?php echo esc_html__( 'Send test email', 'simply-invent-labs-simply-send' ); ?></span></button>
					</form>
				</div>
			</div>
			<?php endif; ?>
			<footer class="ss-footer"><span class="dashicons dashicons-lock" aria-hidden="true"></span><span><?php echo esc_html__( 'Encrypted API key storage. Secure HTTPS delivery.', 'simply-invent-labs-simply-send' ); ?></span><details><summary><?php echo esc_html__( 'Connection details', 'simply-invent-labs-simply-send' ); ?></summary><code id="simply_send_api_url"><?php echo esc_html( Simply_Send_Settings::api_url( $settings ) ); ?></code></details></footer>
		</div>
		<?php
	}
	/**
	 * Render the saved setup overview without exposing the stored API key.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 * @param array<string, string> $region Selected region.
	 * @param string $test_to Default test recipient.
	 * @param string $settings_url Settings page URL.
	 */
	private function render_summary( $settings, $region, $test_to, $settings_url ) {
		$account_id = (string) $settings['account_id'];
		$masked_account_id = strlen( $account_id ) > 3 ? substr( $account_id, 0, 2 ) . str_repeat( '•', strlen( $account_id ) - 3 ) . substr( $account_id, -1 ) : str_repeat( '•', strlen( $account_id ) );
		?>
		<div class="ss-intro ss-summary-heading">
			<div><p class="ss-eyebrow"><span><?php echo esc_html__( 'YOUR EMAIL CONNECTION', 'simply-invent-labs-simply-send' ); ?></span></p><h1><?php echo esc_html__( 'Your site email, connected.', 'simply-invent-labs-simply-send' ); ?></h1><p><span><?php echo esc_html__( 'Review your saved setup or send a test email anytime.', 'simply-invent-labs-simply-send' ); ?></span></p></div>
			<a class="ss-button ss-button-secondary" href="<?php echo esc_url( $settings_url . '&view=setup' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span><?php echo esc_html__( 'Edit setup', 'simply-invent-labs-simply-send' ); ?></span></a>
		</div>
		<div class="ss-summary-panel">
			<div class="ss-summary-status"><span class="ss-connected-dot" aria-hidden="true"></span><strong><?php echo esc_html__( 'Site email sending enabled', 'simply-invent-labs-simply-send' ); ?></strong></div>
			<dl class="ss-summary-grid">
				<div><dt><?php echo esc_html__( 'Workspace region', 'simply-invent-labs-simply-send' ); ?></dt><dd><span class="ss-summary-flag" aria-hidden="true"><?php echo esc_html( $region['flag'] ); ?></span><?php echo esc_html( $region['country'] ); ?><span class="ss-summary-detail"><?php echo esc_html( $region['location'] ); ?></span></dd></div>
				<div><dt><?php echo esc_html__( 'API credentials configured', 'simply-invent-labs-simply-send' ); ?></dt><dd><span class="ss-summary-yes"><?php echo esc_html__( 'Yes', 'simply-invent-labs-simply-send' ); ?></span><span class="ss-summary-detail"><?php echo esc_html__( 'Transactional API key and Account ID saved', 'simply-invent-labs-simply-send' ); ?></span></dd></div>
				<div><dt><?php echo esc_html__( 'From email', 'simply-invent-labs-simply-send' ); ?></dt><dd><?php echo esc_html( $settings['from_email'] ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Sender name', 'simply-invent-labs-simply-send' ); ?></dt><dd><?php echo esc_html( ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' ) ); ?></dd></div>
				<div><dt><?php echo esc_html__( 'Account ID', 'simply-invent-labs-simply-send' ); ?></dt><dd class="ss-masked-account"><span id="ss-account-value"><?php echo esc_html( $masked_account_id ); ?></span><button type="button" class="ss-account-toggle" aria-controls="ss-account-value" aria-pressed="false" aria-label="<?php echo esc_attr__( 'Show Account ID', 'simply-invent-labs-simply-send' ); ?>" data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'simply_send_reveal_account_id' ) ); ?>" data-show="<?php echo esc_attr__( 'Show', 'simply-invent-labs-simply-send' ); ?>" data-hide="<?php echo esc_attr__( 'Hide', 'simply-invent-labs-simply-send' ); ?>" data-show-label="<?php echo esc_attr__( 'Show Account ID', 'simply-invent-labs-simply-send' ); ?>" data-hide-label="<?php echo esc_attr__( 'Hide Account ID', 'simply-invent-labs-simply-send' ); ?>" data-error="<?php echo esc_attr__( 'Unable to reveal the Account ID. Refresh the page and try again.', 'simply-invent-labs-simply-send' ); ?>" hidden><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="ss-account-toggle-text"><?php echo esc_html__( 'Show', 'simply-invent-labs-simply-send' ); ?></span></button><span class="screen-reader-text"><?php echo esc_html__( 'Account ID masked', 'simply-invent-labs-simply-send' ); ?></span><span class="ss-account-error" role="status"></span></dd></div>
				<div><dt><?php echo esc_html__( 'Use this sender for site emails', 'simply-invent-labs-simply-send' ); ?></dt><dd><?php echo ! empty( $settings['force_from'] ) ? esc_html__( 'Yes — override sender details from other plugins', 'simply-invent-labs-simply-send' ) : esc_html__( 'No — other plugins may supply sender details', 'simply-invent-labs-simply-send' ); ?></dd></div>
			</dl>
			<p class="ss-summary-note"><span><?php echo esc_html__( 'These details show your saved configuration. Send a test and check your inbox to confirm delivery.', 'simply-invent-labs-simply-send' ); ?></span></p>
		</div>
		<div class="ss-test-panel ss-summary-test">
			<div class="ss-test-title"><span class="dashicons dashicons-email" aria-hidden="true"></span><div><h2><?php echo esc_html__( 'Send a test email', 'simply-invent-labs-simply-send' ); ?></h2><p><span><?php echo esc_html__( 'Send a test to an inbox you can check, then review email activity in your selected region.', 'simply-invent-labs-simply-send' ); ?></span></p></div></div>
			<form method="post" action="<?php echo esc_url( $settings_url ); ?>" class="ss-test-form">
				<?php wp_nonce_field( 'simply_send_test_email' ); ?>
				<div class="ss-field"><label for="simply_send_test_email"><span><?php echo esc_html__( 'Recipient', 'simply-invent-labs-simply-send' ); ?></span></label><input type="email" id="simply_send_test_email" name="test_email" value="<?php echo esc_attr( $test_to ); ?>" required /></div>
				<button type="submit" name="simply_send_test" value="1" class="ss-button ss-button-primary"><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span><span><?php echo esc_html__( 'Send test email', 'simply-invent-labs-simply-send' ); ?></span></button>
			</form>
		</div>
		<?php
	}

}
