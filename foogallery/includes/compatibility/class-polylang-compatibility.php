<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Polylang Compatibility Class
 *
 * Credit : @Chrystl from wordpress.org - https://wordpress.org/support/topic/polylang-conflict-with-foo-gallery
 * Date: 30/08/2015
 *
 * @package FooGallery
 */

if ( ! class_exists( 'FooGallery_Polylang_Compatibility' ) ) {

	/**
	 * Adds FooGallery compatibility behavior for Polylang.
	 */
	class FooGallery_Polylang_Compatibility {

		/**
		 * FooGallery_Polylang_Compatibility constructor.
		 */
		public function __construct() {

			if ( class_exists( 'Polylang' ) ) {

				add_filter( 'pll_get_post_types', array( $this, 'add_foogallery_cpt' ), 10, 2 );
				add_filter( 'pll_copy_post_metas', array( $this, 'ignore_foogallery_meta' ), 10, 2 );
				add_filter( 'foogallery_attachment_get_posts_args', array( $this, 'include_all_languages_for_gallery_attachments' ), 10, 2 );

				// Whitelist the Polylang metabox.
				add_filter( 'foogallery_metabox_sanity_foogallery', array( $this, 'add_pll_metaboxes' ) );

				add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			}
		}


		/**
		 * Adds Foogallery post type to Polylang settings as 'public' is set to false
		 *
		 * @param array $post_types Post types registered with Polylang.
		 * @param bool  $settings   Whether the list is for Polylang settings.
		 *
		 * @return mixed
		 */
		public function add_foogallery_cpt( $post_types, $settings ) {
			if ( $settings ) {
				$post_types['foogallery'] = 'foogallery';
			}

			return $post_types;
		}

		/**
		 * Adds/whitelists polylang metabox 'ml_box' as Foogallery blocks it by default
		 *
		 * @param array $metabox_ids Allowed metabox IDs.
		 *
		 * @return array
		 */
		public function add_pll_metaboxes( $metabox_ids ) {
			$metabox_ids[] = 'ml_box';
			return $metabox_ids;
		}

		/**
		 * Unsets the copy and synchronization of the fooggallery post meta.
		 * A better solution will be to rewritte a copy function to get the translation
		 *
		 * @param array $metas Meta keys Polylang may copy or synchronize.
		 * @param bool  $sync  Whether Polylang is synchronizing metadata.
		 *
		 * @return mixed
		 */
		public function ignore_foogallery_meta( $metas, $sync ) {

			$key = array_search( FOOGALLERY_META_SETTINGS, $metas, true );
			if ( false !== $key ) {
				unset( $metas[ $key ] );
			}

			$key = array_search( FOOGALLERY_META_ATTACHMENTS, $metas, true );
			if ( false !== $key ) {
				unset( $metas[ $key ] );
			}

			$key = array_search( FOOGALLERY_META_CUSTOM_CSS, $metas, true );
			if ( false !== $key ) {
				unset( $metas[ $key ] );
			}

			$key = array_search( FOOGALLERY_META_TEMPLATE, $metas, true );
			if ( false !== $key ) {
				unset( $metas[ $key ] );
			}

			$key = array_search( FOOGALLERY_META_SORT, $metas, true );
			if ( false !== $key ) {
				unset( $metas[ $key ] );
			}

			return $metas;
		}

		/**
		 * Ensure explicit gallery attachment queries are not filtered to the current Polylang language.
		 *
		 * When Polylang's Media module is enabled, attachment queries are filtered by the current language.
		 * FooGallery stores explicit attachment IDs on the gallery, so those IDs should be loaded regardless
		 * of the language currently being rendered.
		 *
		 * @param array      $query_args Attachment query args.
		 * @param FooGallery $foogallery  Gallery being rendered.
		 *
		 * @return array
		 */
		public function include_all_languages_for_gallery_attachments( $query_args, $foogallery ) {
			if ( isset( $query_args['post__in'] ) && ! empty( $query_args['post__in'] ) ) {
				$query_args['lang'] = 'all';
			}

			return $query_args;
		}

		/**
		 * Add an admin notice to FooGallery pages when Polylang setting media_support is set
		 */
		public function admin_notice() {
			if ( FOOGALLERY_CPT_GALLERY === foo_current_screen_post_type() ) {

				$options = get_option( 'polylang' );

				if ( is_array( $options ) && array_key_exists( 'media_support', $options ) && 1 === intval( $options['media_support'] ) ) {
					?>
				<div class="notice error">
					<p>
						<strong><?php esc_html_e( 'FooGallery + Polylang Alert : ', 'foogallery' ); ?></strong>
						<?php esc_html_e( 'We noticed that you have Polylang installed and you have chosen to activate languages and translations for media.', 'foogallery' ); ?><br />
						<?php esc_html_e( 'This may cause empty galleries on translated pages! To disable this feature, please visit Languages -> Settings.', 'foogallery' ); ?>
					</p>
				</div>
					<?php
				}
			}
		}
	}
}
