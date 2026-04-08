<?php
/**
 * FooGallery Command Palette Integration
 *
 * Registers FooGallery galleries in the WordPress Command Palette
 * (Ctrl+K / Cmd+K), introduced in WordPress 6.3 via @wordpress/commands.
 *
 * Adds:
 *  - A dynamic loader that searches galleries by title as the user types.
 *  - A static "Add New Gallery" command always present in the palette.
 */

if ( ! class_exists( 'FooGallery_Command_Palette' ) ) {

	class FooGallery_Command_Palette {

		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Enqueue the command-palette script on all wp-admin pages.
		 * Bails out gracefully on WordPress versions prior to 6.3 that do not
		 * ship the @wordpress/commands package.
		 */
		public function enqueue_assets() {
			// @wordpress/commands was introduced in WordPress 6.3.
			// If the script handle is not registered we are on an older version.
			if ( ! wp_script_is( 'wp-commands', 'registered' ) ) {
				return;
			}

			wp_enqueue_script(
				'foogallery-command-palette',
				FOOGALLERY_URL . 'js/foogallery-command-palette.js',
				array( 'wp-commands', 'wp-data', 'wp-element', 'wp-i18n' ),
				FOOGALLERY_VERSION,
				true
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'foogallery-command-palette', 'foogallery' );
			}

			wp_add_inline_script(
				'foogallery-command-palette',
				'window.FOOGALLERY_COMMAND_PALETTE = ' . wp_json_encode( $this->get_js_config() ) . ';',
				'before'
			);
		}

		/**
		 * Returns the configuration object passed to the JavaScript layer.
		 *
		 * @return array
		 */
		private function get_js_config() {
			$post_type_object = get_post_type_object( FOOGALLERY_CPT_GALLERY );

			$edit_url = '';
			if ( $post_type_object && ! empty( $post_type_object->_edit_link ) ) {
				// _edit_link contains a printf-style %d placeholder for the post ID.
				$edit_url = admin_url( $post_type_object->_edit_link . '&action=edit' );
			}

			return array(
				'editGalleryUrl'   => $edit_url,
				'addNewGalleryUrl' => admin_url( 'post-new.php?post_type=' . FOOGALLERY_CPT_GALLERY ),
				'galleryName'      => foogallery_plugin_name(),
			);
		}
	}
}
