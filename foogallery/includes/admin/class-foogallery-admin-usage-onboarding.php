<?php
/**
 * Offer independent feature-reporting consent during Freemius onboarding.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps the reporting decision local, outside Freemius account permissions. */
class FooGallery_Admin_Usage_Onboarding {
	/** Register supported SDK hooks without changing the bundled SDK. */
	public function __construct() {
		add_action( 'init', array( $this, 'override_i18n' ) );
		foogallery_fs()->add_filter( 'connect_message_on_update', array( $this, 'format_skip_message' ) );
		foogallery_fs()->add_action( 'connect/after_message', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** Override SDK messages after WordPress is ready to load translations. */
	public function override_i18n() {
		foogallery_fs()->override_i18n(
			array(
				/* translators: %s: module type (plugin, theme, or add-on). */
				'connect-message'           => __( 'Opt in to get email notifications for security & feature updates, educational content, and occasional offers, and to share basic WordPress environment info & FooGallery feature usage. This will help us make the %s more compatible with your site and better at doing what you need it to.', 'foogallery' ),
				'connect-message_on-update' => __( 'Opt in to get email notifications for security & feature updates, educational content, and occasional offers, and to share basic WordPress environment info & FooGallery feature usage.', 'foogallery' ),
			)
		);
	}

	/**
	 * Put the SDK's skip reassurance on its own paragraph.
	 *
	 * @param string $message Formatted SDK opt-in message.
	 * @return string
	 */
	public function format_skip_message( $message ) {
		$skip_message = sprintf(
			/* translators: %1$s: plugin name in bold. */
			esc_html( fs_text_inline( 'If you skip this, that\'s okay! %1$s will still work just fine.', 'connect-message_on-update_skip', 'foogallery' ) ),
			'<b>' . esc_html( foogallery_fs()->get_plugin_name() ) . '</b>'
		);
		return str_replace( ' ' . $skip_message, '<br><br>' . $skip_message, $message );
	}

	/** Load native controls on the SDK connect screen and the explicit opt-in page. */
	public function enqueue_assets() {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || ! self::is_connect_screen() ) {
			return;
		}
		wp_enqueue_style( 'foogallery-usage-onboarding', FOOGALLERY_URL . 'css/foogallery-usage-onboarding.css', array( 'wp-components' ), FOOGALLERY_VERSION );
		wp_enqueue_script( 'foogallery-usage-onboarding', FOOGALLERY_URL . 'js/foogallery-usage-onboarding.js', array( 'jquery', 'wp-element', 'wp-components', 'wp-i18n' ), FOOGALLERY_VERSION, true );
		wp_localize_script(
			'foogallery-usage-onboarding',
			'FooGalleryUsageOnboarding',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( FooGallery_Admin_Usage_Settings::NONCE_ACTION ),
				'consentVersion' => 2,
			)
		);
		wp_set_script_translations( 'foogallery-usage-onboarding', 'foogallery', FOOGALLERY_PATH . 'languages' );
	}

	/**
	 * Identify screens where the SDK renders the connect flow.
	 *
	 * @return bool
	 */
	public static function is_connect_screen() {
		// Read-only routing check; consent changes use the nonce-protected usage endpoint.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return self::is_explicit_optin_screen() || 'foogallery-optin' === $page || ( foogallery_fs()->is_activation_mode() && foogallery_fs()->is_activation_page() );
	}

	/**
	 * Recognize the explicit SDK preview route, including after a saved decision.
	 *
	 * @return bool
	 */
	public static function is_explicit_optin_screen() {
		// Read-only display override; this does not grant or reset consent.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plugin = isset( $_GET['freemius_plugin'] ) && is_string( $_GET['freemius_plugin'] ) ? sanitize_key( wp_unslash( $_GET['freemius_plugin'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen = isset( $_GET['freemius_screen'] ) && is_string( $_GET['freemius_screen'] ) ? sanitize_key( wp_unslash( $_GET['freemius_screen'] ) ) : '';
		return 'foogallery' === $plugin && 'optin' === $screen;
	}

	/**
	 * Offer a new decision or an explicit preview on a single site's connection screen.
	 *
	 * @param array $activation_state SDK screen context.
	 * @return bool
	 */
	public function should_offer( $activation_state ) {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || ! is_array( $activation_state ) || ! empty( $activation_state['is_network_level_activation'] ) || ! empty( $activation_state['is_pending_activation'] ) ) {
			return false;
		}
		$status = FooGallery_Usage::instance()->status();
		return $status['available'] && empty( $status['gate'] ) && ( self::is_explicit_optin_screen() || ( ! $status['consent'] && ! $status['dismissed'] ) );
	}

	/**
	 * Render the disclosure for JavaScript to place in the SDK permissions list.
	 *
	 * @param array $activation_state SDK screen context.
	 */
	public function render( $activation_state ) {
		if ( ! $this->should_offer( $activation_state ) ) {
			return;
		}
		$status = FooGallery_Usage::instance()->status();
		?>
		<div id="foogallery-usage-onboarding" hidden class="fs-permission-description" data-consented="<?php echo $status['consent'] ? 'true' : 'false'; ?>">
			<span><strong><?php esc_html_e( 'Share FooGallery Features (optional)', 'foogallery' ); ?></strong></span>
			<p><?php esc_html_e( 'Share usage reports of FooGallery features and settings to help improve FooGallery.', 'foogallery' ); ?> <a href="<?php echo esc_url( foogallery_admin_settings_url() . '#improve' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Review what is sent and why', 'foogallery' ); ?></a>.</p>
			<div id="foogallery-usage-onboarding-choice"></div>
		</div>
		<?php
	}
}
