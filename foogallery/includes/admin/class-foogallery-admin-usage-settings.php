<?php
/**
 * Private administrator controls for FooGallery Improve.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Native Settings-tab controls for voluntary feature reporting. */
class FooGallery_Admin_Usage_Settings {
	const NONCE_ACTION = 'foogallery_usage_admin';

	/** Register local admin hooks. */
	public function __construct() {
		add_filter( 'foogallery_admin_settings', array( $this, 'add_settings_tab' ), PHP_INT_MAX );
		add_action( 'foogallery_admin_settings_custom_type_render_setting', array( $this, 'render_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_invitation' ) );
		add_action( 'wp_ajax_foogallery_usage', array( $this, 'ajax_handle' ) );
		add_action( 'admin_post_foogallery_usage_download', array( $this, 'download' ) );
	}

	/**
	 * Add a custom display, with no field in the ordinary settings option.
	 *
	 * @param array $settings Existing settings definitions.
	 * @return array
	 */
	public function add_settings_tab( $settings ) {
		// Append after extension tabs, including White Labeling, even on repeated filtering.
		unset( $settings['tabs']['improve'] );
		/* translators: %s: White-labelled plugin name. */
		$settings['tabs']['improve'] = sprintf( __( 'Improve %s', 'foogallery' ), foogallery_plugin_name() );
		foreach ( $settings['settings'] as $setting ) {
			if ( isset( $setting['id'] ) && 'foogallery_usage_improve' === $setting['id'] ) {
				return $settings;
			}
		}
		$settings['settings'][] = array(
			'id'      => 'foogallery_usage_improve',
			'title'   => '',
			'type'    => 'foogallery_usage',
			'tab'     => 'improve',
			'section' => __( 'Help improve FooGallery', 'foogallery' ),
		);
		return $settings;
	}

	/**
	 * Render only the Improve custom setting.
	 *
	 * @param array $setting Custom setting definition.
	 */
	public function render_setting( $setting ) {
		if ( ! isset( $setting['type'] ) || 'foogallery_usage' !== $setting['type'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div id="foogallery-usage" class="foogallery-usage">
			<p><?php esc_html_e( 'Loading Improve controls…', 'foogallery' ); ?></p>
			<noscript><?php esc_html_e( 'Improve needs JavaScript for local previews and consent controls. No sharing is enabled by opening this screen.', 'foogallery' ); ?></noscript>
		</div>
		<?php
	}

	/**
	 * Match plugin-owned screens, including a relocated white-label menu.
	 *
	 * @return bool
	 */
	public function is_owned_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || is_network_admin() ) {
			return false;
		}
		if ( in_array( $screen->post_type, array( 'foogallery', 'foogallery-album' ), true ) && in_array( $screen->base, array( 'edit', 'post', 'edit-tags', 'term' ), true ) ) {
			return true;
		}
		$pages = array( 'foogallery-settings', 'foogallery-features', 'foogallery-help', 'foogallery-systeminfo', 'foogallery-pricing', 'foogallery-addons', 'foogallery-media-audit' );
		foreach ( $pages as $page ) {
			$suffix = '_page_' . $page;
			if ( substr( $screen->id, -strlen( $suffix ) ) === $suffix || 'toplevel_page_' . $page === $screen->id ) {
				return true;
			}
		}
		return false;
	}

	/** Enqueue only WordPress-provided UI dependencies and local assets. */
	public function enqueue_assets() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->is_owned_screen() ) {
			return;
		}
		wp_enqueue_style( 'foogallery-usage', FOOGALLERY_URL . 'css/foogallery-usage.css', array( 'wp-components' ), FOOGALLERY_VERSION );
		wp_enqueue_script( 'foogallery-usage', FOOGALLERY_URL . 'js/foogallery-usage.js', array( 'jquery', 'wp-element', 'wp-components', 'wp-i18n' ), FOOGALLERY_VERSION, true );
		wp_localize_script(
			'foogallery-usage',
			'FooGalleryUsageConfig',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
				'consentVersion' => 2,
				'pluginName' => foogallery_plugin_name(),
				'downloadUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=foogallery_usage_download' ), self::NONCE_ACTION ),
			)
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'foogallery-usage', 'foogallery', FOOGALLERY_PATH . 'languages' );
		}
	}

	/** Display the one-time invitation. JavaScript hides it on the Improve hash. */
	public function render_invitation() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->is_owned_screen() ) {
			return;
		}
		if ( class_exists( 'FooGallery_Admin_Usage_Onboarding' ) && FooGallery_Admin_Usage_Onboarding::is_connect_screen() ) {
			return;
		}
		$status = FooGallery_Usage::instance()->status();
		if ( ! empty( $status['dismissed'] ) || empty( $status['available'] ) ) {
			return;
		}
		$url = foogallery_admin_settings_url() . '#improve';
		?>
		<div class="notice notice-info is-dismissible foogallery-usage-invitation" hidden>
			<p><strong><?php
			/* translators: %s: White-labelled plugin name. */
			printf( esc_html__( 'Help us improve %s', 'foogallery' ), esc_html( foogallery_plugin_name() ) );
			?></strong> :
			<?php
			/* translators: %s: White-labelled plugin name. */
			printf( esc_html__( 'please share what features of %s you use, so we know what to improve. Sharing is optional. Thank you!', 'foogallery' ), esc_html( foogallery_plugin_name() ) );
			?>
			<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Review and help us improve', 'foogallery' ); ?></a></p>
		</div>
		<?php
	}

	/** Explicit private cache headers, including WordPress versions before 6.3. */
	private function private_headers() {
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	/** Validate authorization and strictly dispatch a local operation. */
	public function ajax_handle() {
		$this->private_headers();
		$nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'foogallery' ) ), 403 );
		}
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$allowed   = array( 'status', 'last_report', 'preview_start', 'preview_step', 'enable', 'pause', 'delete', 'send', 'dismiss', 'migrate', 'independent', 'reveal' );
		if ( ! in_array( $operation, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid operation.', 'foogallery' ) ), 400 );
		}
		$consent_version = isset( $_POST['consent_version'] ) && is_scalar( $_POST['consent_version'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['consent_version'] ) ) : '';
		if ( 'enable' === $operation && '2' !== $consent_version ) {
			wp_send_json_error( array( 'message' => __( 'Reload Improve settings and review the feature sharing consent before enabling sharing.', 'foogallery' ) ), 400 );
		}
		$result = FooGallery_Usage::instance()->handle( $operation );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}
		if ( ! empty( $result['preview_body'] ) ) {
			$result['preview_body_sha256'] = hash( 'sha256', $result['preview_body'] );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Resolve a download from the same stored bytes displayed by Improve.
	 *
	 * @param array  $status Redacted engine status.
	 * @param string $source Explicit preview, outbox or acknowledged packet.
	 * @param string $type Body or request details.
	 * @param string $expected_hash Hash displayed to the administrator.
	 * @return string|WP_Error
	 */
	public function download_body( $status, $source, $type, $expected_hash = '' ) {
		if ( ! in_array( $source, array( 'preview', 'outbox', 'acknowledged' ), true ) || ! in_array( $type, array( 'body', 'request' ), true ) ) {
			return new WP_Error( 'invalid_download', __( 'Invalid download.', 'foogallery' ) );
		}
		$packet = 'preview' === $source ? array( 'body' => $status['preview_body'] ) : $status[ $source ];
		if ( ! is_array( $packet ) || empty( $packet['body'] ) ) {
			return new WP_Error( 'missing_packet', __( 'This report is no longer available. Refresh Improve.', 'foogallery' ) );
		}
		$body = $packet['body'];
		$hash = hash( 'sha256', $body );
		if ( '' !== $expected_hash && ! hash_equals( $hash, $expected_hash ) ) {
			return new WP_Error( 'changed_packet', __( 'The report changed. Refresh Improve before downloading it.', 'foogallery' ) );
		}
		if ( 'body' === $type ) {
			return $body;
		}
		$accepted = isset( $packet['receipt']['accepted_user_agent'] ) ? $packet['receipt']['accepted_user_agent'] : null;
		return wp_json_encode(
			array(
				'source'              => $source,
				'method'              => 'POST',
				'destination'         => $status['endpoint'],
				'headers'             => array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer [redacted]',
				),
				'expected_user_agent' => isset( $packet['expected_user_agent'] ) ? $packet['expected_user_agent'] : $status['expected_user_agent'],
				'accepted_user_agent' => $accepted,
				'body_sha256'         => $hash,
				'body'                => $body,
				'note'                => __( 'User-Agent is supplied by WordPress. The expected value can be changed by HTTP filters or hosting proxies; the receipt records the server-accepted value. Credentials are masked. A preview has not been sent.', 'foogallery' ),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}

	/** Authenticated source-specific download. */
	public function download() {
		$this->private_headers();
		$nonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Permission denied.', 'foogallery' ), '', array( 'response' => 403 ) );
		}
		$type   = isset( $_GET['type'] ) && is_string( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$source = isset( $_GET['source'] ) && is_string( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$hash   = isset( $_GET['hash'] ) && is_string( $_GET['hash'] ) ? sanitize_text_field( wp_unslash( $_GET['hash'] ) ) : '';
		$result = $this->download_body( FooGallery_Usage::instance()->status( true ), $source, $type, $hash );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 409 ) );
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="foogallery-usage.json"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated stored JSON bytes must remain exact; this is an attachment with nosniff.
		echo $result;
		exit;
	}
}
