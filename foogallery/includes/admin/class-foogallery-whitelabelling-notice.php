<?php
/**
 * Administrator notice for the bundled white-label compatibility transition.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FooGallery_Whitelabelling_Notice' ) ) {
	/**
	 * Shows grandfathered administrators how to obtain the standalone add-on.
	 */
	class FooGallery_Whitelabelling_Notice {
		const ADDON_SLUG  = 'foogallery-whitelabelling-addon';
		const SNOOZE_META = 'foogallery_whitelabelling_notice_snoozed_until';

		/**
		 * Register notice and snooze handlers.
		 */
		public function __construct() {
			add_action( 'admin_notices', array( $this, 'display_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_notice_script' ) );
			add_action( 'wp_ajax_foogallery_whitelabelling_notice_snooze', array( $this, 'ajax_snooze' ) );
		}

		/**
		 * Determine whether the transition notice belongs on this request.
		 *
		 * @return bool
		 */
		public function should_display() {
			if ( ! current_user_can( 'manage_options' ) || ! FooGallery_Whitelabelling_Compatibility::is_grandfathered() ) {
				return false;
			}

			if ( $this->standalone_runtime_is_active() ) {
				return false;
			}

			global $foogallery_extensions;
			if (
				! is_array( $foogallery_extensions ) ||
				empty( $foogallery_extensions[ FooGallery_Whitelabelling_Compatibility::SLUG ] ) ||
				! $foogallery_extensions[ FooGallery_Whitelabelling_Compatibility::SLUG ] instanceof FooGallery_Pro_Whitelabelling_Extension
			) {
				return false;
			}

			$snoozed_until = get_user_meta( get_current_user_id(), self::SNOOZE_META, true );
			if ( is_numeric( $snoozed_until ) && (int) $snoozed_until > time() ) {
				return false;
			}

			$screen = get_current_screen();
			if ( ! $screen ) {
				return false;
			}

			if ( in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
				return true;
			}

			return false !== strpos( $screen->id, 'foogallery' ) || in_array( $screen->post_type, array( FOOGALLERY_CPT_GALLERY, FOOGALLERY_CPT_ALBUM ), true );
		}

		/**
		 * Enqueue the notice snooze behavior before admin-head scripts print.
		 */
		public function enqueue_notice_script() {
			if ( ! $this->should_display() ) {
				return;
			}

			wp_enqueue_script( 'jquery' );
			$script = 'jQuery(function($){$("#foogallery-whitelabelling-snooze").on("click",function(){var button=$(this);$.post(' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',{action:"foogallery_whitelabelling_notice_snooze",_wpnonce:' . wp_json_encode( wp_create_nonce( 'foogallery_whitelabelling_notice_snooze' ) ) . '}).done(function(response){if(response.success){button.closest(".notice").remove();}});});});';
			wp_add_inline_script( 'jquery', $script );
		}

		/**
		 * Render the migration notice.
		 */
		public function display_notice() {
			if ( ! $this->should_display() ) {
				return;
			}
			?>
			<div class="notice notice-info foogallery-whitelabelling-transition-notice">
				<p><strong><?php esc_html_e( 'FooGallery White Labeling is now a standalone add-on.', 'foogallery' ); ?></strong></p>
				<p><?php esc_html_e( 'The add-on is now available free in your Freemius account. Your current white-label settings are retained, so you can move to the add-on without reconfiguring them.', 'foogallery' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( 'https://users.freemius.com' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get the free add-on', 'foogallery' ); ?></a>
					<button type="button" class="button" id="foogallery-whitelabelling-snooze"><?php esc_html_e( 'Remind me in 30 days', 'foogallery' ); ?></button>
				</p>
			</div>
			<?php
		}

		/**
		 * Snooze the notice for the current administrator for 30 days.
		 */
		public function ajax_snooze() {
			if ( ! check_ajax_referer( 'foogallery_whitelabelling_notice_snooze', false, false ) ) {
				wp_send_json_error(
					array(
						'status'  => 403,
						'message' => __( 'Invalid security token.', 'foogallery' ),
					),
					403
				);
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error(
					array(
						'status'  => 403,
						'message' => __( 'Insufficient permissions.', 'foogallery' ),
					),
					403
				);
			}

			update_user_meta( get_current_user_id(), self::SNOOZE_META, time() + ( 30 * DAY_IN_SECONDS ) );
			wp_send_json_success();
		}

		/**
		 * Whether the standalone add-on completed its runtime bootstrap.
		 *
		 * @return bool
		 */
		private function standalone_runtime_is_active() {
			if ( function_exists( 'foogallery_whitelabel_runtime_is_bootstrapped' ) && true === foogallery_whitelabel_runtime_is_bootstrapped() ) {
				return true;
			}

			global $foogallery_extensions;
			if ( ! is_array( $foogallery_extensions ) ) {
				return false;
			}

			foreach ( $foogallery_extensions as $extension ) {
				if ( is_object( $extension ) && is_a( $extension, 'FooPlugins\\FooGallery\\WhiteLabel\\Extension' ) ) {
					return true;
				}
			}

			return false;
		}
	}
}
