<?php
/**
 * Reviewed, fixed feature catalogue. Never append third-party IDs at runtime.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
/** Fixed catalogue shared by collection, the UI and strict serialization. */
class FooGallery_Usage_Registry {
	const SCHEMA_VERSION  = 2;
	const CATALOG_VERSION = 3;

	/**
	 * Return the four approved measurement states.
	 *
	 * @return array
	 */
	public static function states() {
		return array( 'enabled', 'disabled', 'unavailable', 'unknown' ); }

	/**
	 * Describe one reviewed measurement for the local trust interface.
	 *
	 * @param string $label Translated label.
	 * @param string $scope Measurement scope.
	 * @param string $owner Core, PRO or add-on owner.
	 * @param bool   $summary Whether this rolls up detailed features.
	 * @return array
	 */
	private static function item( $label, $scope, $owner, $summary = false ) {
		$description = __( 'Whether this feature is configured.', 'foogallery' );
		if ( 'site_module' === $scope ) {
			$description = __( 'Whether this module is active on the Features page.', 'foogallery' ); }
		if ( 'local_action_observation' === $scope ) {
			$description = __( 'Whether this tool was used successfully in the last 30 days after opting in.', 'foogallery' ); }
		return array(
			'label'       => $label,
			'scope'       => $scope,
			'description' => $description,
			'owner'       => $owner,
			'summary'     => $summary,
		);
	}

	/**
	 * Map each advertised promotion to its canonical measurement ID or IDs.
	 *
	 * @return array
	 */
	public static function promo_map() {
		return array(
			'gallery_templates'     => array( 'layout.polaroid', 'layout.grid', 'layout.slider', 'layout.spotlight' ),
			'mobile_settings'       => 'gallery.mobile_settings',
			'hover_effects'         => 'gallery.hover_effects',
			'image_filter_effects'  => 'gallery.thumbnail_effects',
			'video'                 => 'gallery.video',
			'filtering'             => array( 'gallery.filtering.simple', 'gallery.filtering.multilevel' ),
			'search'                => 'gallery.filtering.search',
			'pagination'            => 'gallery.pagination.enabled',
			'dynamic_galleries'     => array( 'datasource.media_tags', 'datasource.media_categories', 'datasource.folders', 'datasource.lightroom', 'datasource.post_query', 'datasource.rml', 'datasource.infinite_uploads' ),
			'custom_captions'       => 'gallery.custom_captions.enabled',
			'exif'                  => 'gallery.exif',
			'imagegallery_schema'   => 'gallery.imagegallery_schema',
			'bulk_copy'             => 'bulk_copy_30d',
			'product_datasource'    => 'datasource.woocommerce',
			'master_product'        => 'gallery.ecommerce.master_product',
			'product_gallery'       => 'layout.product',
			'protection'            => 'gallery.protection.enabled',
			'colors'                => 'gallery.colors.enabled',
			'lightbox_product_info' => 'gallery.ecommerce.lightbox_product_info',
			'cta_buttons'           => 'gallery.cta.enabled',
			'sales_ribbons'         => 'gallery.ecommerce.sales_ribbons',
			'gallery_blueprints'    => 'gallery.blueprints.enabled',
			'ecommerce'             => 'gallery.ecommerce.enabled',
		);
	}

	/**
	 * Map all reviewed datasource types to their canonical measurements.
	 *
	 * @return array
	 */
	public static function datasource_map() {
		return array(
			'media_library'    => 'datasource.media_library',
			'media_tags'       => 'datasource.media_tags',
			'media_categories' => 'datasource.media_categories',
			'folders'          => 'datasource.folders',
			'lightroom'        => 'datasource.lightroom',
			'post_query'       => 'datasource.post_query',
			'rml'              => 'datasource.rml',
			'infinite_uploads' => 'datasource.infinite_uploads',
			'woocommerce'      => 'datasource.woocommerce',
		);
	}

	/**
	 * Return fixed, translated metadata without evaluating site configuration.
	 *
	 * @return array
	 */
	public static function catalog() {
		$modules  = array(
			'albums'                        => self::item( __( 'Albums', 'foogallery' ), 'site_module', 'core', false ),
			'foogallery-migrate'            => self::item( __( 'Migration', 'foogallery' ), 'site_module', 'core', false ),
			'foogallery-import-export'      => self::item( __( 'Import and Export', 'foogallery' ), 'site_module', 'core', false ),
			'foogallery-media-audit'        => self::item( __( 'Media Audit', 'foogallery' ), 'site_module', 'core', false ),
			'foogallery-bulk-copy'          => self::item( __( 'Bulk Copy', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-whitelabelling'     => self::item( __( 'White Labeling', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-exif'               => self::item( __( 'EXIF', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-filtering'          => self::item( __( 'Filtering', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-gallery-blueprints' => self::item( __( 'Gallery Blueprints', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-paging'             => self::item( __( 'Advanced Pagination', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-protection'         => self::item( __( 'Protection', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-video'              => self::item( __( 'Video', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-woocommerce'        => self::item( __( 'WooCommerce', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-colors'             => self::item( __( 'Colors', 'foogallery' ), 'site_module', 'pro', false ),
			'foogallery-user-uploads'       => self::item( __( 'User Uploads', 'foogallery' ), 'site_module', 'addon', false ),
			'foogallery-social'             => self::item( __( 'Social', 'foogallery' ), 'site_module', 'addon', false ),
			'foogallery-proofing'           => self::item( __( 'Client Proofing', 'foogallery' ), 'site_module', 'addon', false ),
		);
		$features = array(
			'gallery.mobile_settings'              => self::item( __( 'Mobile-Specific Gallery Settings', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.hover_effects'                => self::item( __( 'Non-default Hover Presets', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.thumbnail_effects'         => self::item( __( 'Thumbnail Effects', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.video'                        => self::item( __( 'Video Galleries', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.filtering.search'                       => self::item( __( 'Gallery Search', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.pagination.enabled'                   => self::item( __( 'Advanced Pagination', 'foogallery' ), 'published_gallery_configuration', 'pro', true ),
			'gallery.custom_captions.enabled'              => self::item( __( 'Custom Captions', 'foogallery' ), 'published_gallery_configuration', 'pro', true ),
			'gallery.exif'                         => self::item( __( 'EXIF Metadata', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.imagegallery_schema'          => self::item( __( 'ImageGallery SEO Schema', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.woocommerce'           => self::item( __( 'WooCommerce Product Datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.ecommerce.master_product'               => self::item( __( 'Sell Images With A Master Product', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.protection.enabled'                   => self::item( __( 'Watermarking &amp; Protection', 'foogallery' ), 'published_gallery_configuration', 'pro', true ),
			'gallery.colors.enabled'                       => self::item( __( 'Color Extraction &amp; Sorting', 'foogallery' ), 'published_gallery_configuration', 'pro', true ),
			'gallery.ecommerce.lightbox_product_info'        => self::item( __( 'Lightbox Product Info', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.cta.enabled'                  => self::item( __( 'CTA Buttons', 'foogallery' ), 'published_gallery_configuration', 'pro', true ),
			'gallery.ecommerce.sales_ribbons'                => self::item( __( 'Sales Ribbons', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.blueprints.enabled'           => self::item( __( 'Gallery Blueprints', 'foogallery' ), 'published_gallery_configuration', 'pro', true ),
			'gallery.ecommerce.enabled'                    => self::item( __( 'Ecommerce', 'foogallery' ), 'published_gallery_configuration', 'pro', true ),
			'layout.default'                   => self::item( __( 'Default layout', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'layout.masonry'                   => self::item( __( 'Masonry layout', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'layout.justified'                 => self::item( __( 'Justified layout', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'layout.carousel'                  => self::item( __( 'Carousel layout', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'layout.image_viewer'              => self::item( __( 'Image Viewer layout', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'layout.simple_portfolio'          => self::item( __( 'Simple Portfolio layout', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'layout.thumbnail'                 => self::item( __( 'Thumbnail layout', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'layout.polaroid'                  => self::item( __( 'Polaroid layout', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'layout.grid'                      => self::item( __( 'Grid layout', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'layout.slider'                    => self::item( __( 'Slider layout', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'layout.spotlight'                 => self::item( __( 'Spotlight layout', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'layout.product'                   => self::item( __( 'Product layout', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.pagination.dots'                  => self::item( __( 'Dots paging', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'gallery.pagination.numbered'              => self::item( __( 'Numbered pagination', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.pagination.infinite'              => self::item( __( 'Infinite scrolling', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.pagination.load_more'             => self::item( __( 'Load more pagination', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'settings.lazy_loading.standard'            => self::item( __( 'Lazy loading', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'settings.lazy_loading.seo'                 => self::item( __( 'SEO lazy loading', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'gallery.custom_css'               => self::item( __( 'Custom CSS', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'gallery.filtering.simple'         => self::item( __( 'Simple filtering', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.filtering.multilevel'     => self::item( __( 'Multilevel filtering', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.custom_captions.gallery'  => self::item( __( 'Custom gallery captions', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.custom_captions.lightbox' => self::item( __( 'Custom lightbox captions', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.media_tags'             => self::item( __( 'Tags datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.media_categories'       => self::item( __( 'Categories datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.folders'           => self::item( __( 'Server-folder datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.lightroom'        => self::item( __( 'Lightroom datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.post_query'            => self::item( __( 'Post-query datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.rml'              => self::item( __( 'Real Media Library datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'datasource.infinite_uploads' => self::item( __( 'Infinite Uploads datasource', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.protection.right_click'   => self::item( __( 'Right-click protection', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.protection.watermark'                => self::item( __( 'Watermark', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.protection.thumbnail_watermark'      => self::item( __( 'Thumbnail watermark', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.colors.extracted'          => self::item( __( 'Extracted color metadata', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.colors.sorting'            => self::item( __( 'Color sorting', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.colors.background'         => self::item( __( 'Gallery background color', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.colors.lightbox'           => self::item( __( 'Lightbox color configuration', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.cta.custom'               => self::item( __( 'Custom CTA', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.cta.download'             => self::item( __( 'Download CTA', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.blueprints.definition'                => self::item( __( 'Blueprint definition', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'gallery.blueprints.use'            => self::item( __( 'Blueprint use', 'foogallery' ), 'published_gallery_configuration', 'pro', false ),
			'addon.user_uploads_form'          => self::item( __( 'User Upload form', 'foogallery' ), 'published_gallery_configuration', 'addon', false ),
			'addon.user_uploads_video'         => self::item( __( 'User Upload video', 'foogallery' ), 'published_gallery_configuration', 'addon', false ),
			'addon.social.enabled'                     => self::item( __( 'Social configuration', 'foogallery' ), 'published_gallery_configuration', 'addon', false ),
			'addon.social.likes'               => self::item( __( 'Social likes', 'foogallery' ), 'published_gallery_configuration', 'addon', false ),
			'addon.social.comments'            => self::item( __( 'Social comments', 'foogallery' ), 'published_gallery_configuration', 'addon', false ),
			'addon.social.sharing'             => self::item( __( 'Social sharing', 'foogallery' ), 'published_gallery_configuration', 'addon', false ),
			'addon.proofing_sessions'          => self::item( __( 'Client Proofing sessions', 'foogallery' ), 'addon_configuration', 'addon', false ),
			'album.default'                    => self::item( __( 'Responsive album', 'foogallery' ), 'published_album_configuration', 'core', false ),
			'album.stack'                      => self::item( __( 'Stack album', 'foogallery' ), 'published_album_configuration', 'core', false ),
			'lightbox.foogallery'              => self::item( __( 'FooGallery lightbox', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'lightbox.foobox'                  => self::item( __( 'FooBox lightbox', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
			'lightbox.other'                   => self::item( __( 'Other lightbox', 'foogallery' ), 'published_gallery_configuration', 'core', false ),
		);
		$features['gallery.loading.customized'] = self::item( __( 'Non-default loading animation', 'foogallery' ), 'published_gallery_configuration', 'core' );
		$features['gallery.ecommerce.cta_buttons'] = self::item( __( 'Product CTA buttons', 'foogallery' ), 'published_gallery_configuration', 'pro' );
		$features['datasource.media_library'] = self::item( __( 'Media Library datasource', 'foogallery' ), 'published_gallery_configuration', 'core' );
		$features['settings.custom_css'] = self::item( __( 'Custom CSS', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.custom_css']['description'] = __( 'Whether global custom CSS is configured.', 'foogallery' );
		$features['settings.custom_js'] = self::item( __( 'Custom JavaScript', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.custom_js']['description'] = __( 'Whether global custom JavaScript is configured.', 'foogallery' );
		$features['settings.gallery_creator_role'] = self::item( __( 'Gallery Creator Role', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.gallery_creator_role']['description'] = __( 'Whether the gallery creator role differs from the default Administrator role.', 'foogallery' );
		$features['settings.advanced_attachment_modal'] = self::item( __( 'Advanced Attachment Modal', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.advanced_attachment_modal']['description'] = __( 'Whether the advanced attachment modal is enabled.', 'foogallery' );
		$features['settings.override_gallery_shortcode'] = self::item( __( 'Override Gallery Shortcode', 'foogallery' ), 'site_configuration', 'pro' );
		$features['settings.override_gallery_shortcode']['description'] = __( 'Whether the WordPress gallery shortcode override is enabled.', 'foogallery' );
		$features['settings.thumb_engine'] = self::item( __( 'Thumbnail Engine', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.thumb_engine']['description'] = __( 'Whether a supported non-default thumbnail engine is selected.', 'foogallery' );
		$features['settings.use_original_thumbs'] = self::item( __( 'Use Original Thumbnails', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.use_original_thumbs']['description'] = __( 'Whether using original thumbnails is enabled.', 'foogallery' );
		$features['settings.enqueue_polyfills'] = self::item( __( 'Enqueue Polyfills', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.enqueue_polyfills']['description'] = __( 'Whether browser compatibility polyfills are enabled.', 'foogallery' );
		$features['settings.force_https'] = self::item( __( 'Force HTTPS', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.force_https']['description'] = __( 'Whether gallery assets are forced to use HTTPS.', 'foogallery' );
		$features['settings.disable_media_category_sidebar'] = self::item( __( 'Disable Media Category Modal Sidebar', 'foogallery' ), 'site_configuration', 'pro' );
		$features['settings.disable_media_category_sidebar']['description'] = __( 'Whether the media category modal sidebar is disabled.', 'foogallery' );
		$features['settings.album_creator_role'] = self::item( __( 'Album Creator Role', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.album_creator_role']['description'] = __( 'Whether albums use a separate creator role instead of the gallery creator role.', 'foogallery' );
		$features['settings.enable_gallery_descriptions'] = self::item( __( 'Enable Gallery Descriptions', 'foogallery' ), 'site_configuration', 'core' );
		$features['settings.enable_gallery_descriptions']['description'] = __( 'Whether gallery descriptions are enabled in albums.', 'foogallery' );
		$features['settings.enable_imagegallery_schema'] = self::item( __( 'ImageGallery Schema', 'foogallery' ), 'site_configuration', 'pro' );
		$features['settings.enable_imagegallery_schema']['description'] = __( 'Whether ImageGallery schema is enabled globally.', 'foogallery' );
		foreach ( self::datasource_map() as $id ) {
			$features[ $id ]['description'] = __( 'Enabled when a published gallery uses this datasource.', 'foogallery' );
		}
		$features['gallery.exif']['description'] = __( 'Whether a published gallery enables EXIF in a supported panel or FooGallery lightbox.', 'foogallery' );
		$features['gallery.imagegallery_schema']['description'] = __( 'Whether a published, password-free gallery with local images has SEO schema enabled.', 'foogallery' );
		$features['gallery.ecommerce.master_product']['description'] = __( 'Whether a published gallery uses a WooCommerce master product.', 'foogallery' );
		$features['gallery.hover_effects']['description'] = __( 'Whether a published gallery uses a hover preset other than Default.', 'foogallery' );
		$features['gallery.thumbnail_effects']['description'] = __( 'Whether a published gallery uses a thumbnail effect.', 'foogallery' );
		$features['gallery.loading.customized']['description'] = __( 'Whether a published gallery uses a loading animation other than Fade In or None.', 'foogallery' );
		$features['gallery.mobile_settings']['description'] = __( 'Whether a published gallery has mobile settings that differ from its desktop settings.', 'foogallery' );
		foreach ( $features as $id => $feature ) {
			if ( 0 === strpos( $id, 'layout.' ) ) {
				$features[ $id ]['description'] = __( 'Enabled when a published gallery uses this layout.', 'foogallery' );
			}
		}
		$actions = array(
			'bulk_copy_30d' => self::item( __( 'Bulk Copy in the last 30 days', 'foogallery' ), 'local_action_observation', 'pro' ),
			'import_30d' => self::item( __( 'Import in the last 30 days', 'foogallery' ), 'local_action_observation', 'core' ),
			'export_30d' => self::item( __( 'Export in the last 30 days', 'foogallery' ), 'local_action_observation', 'core' ),
			'migration_30d' => self::item( __( 'Migration in the last 30 days', 'foogallery' ), 'local_action_observation', 'core' ),
			'media_audit_30d' => self::item( __( 'Media Audit in the last 30 days', 'foogallery' ), 'local_action_observation', 'core' ),
		);
		return array(
			'modules'  => $modules,
			'features' => $features,
			'actions'  => $actions,
		);
	}

	/**
	 * Check membership without permitting external catalogue extension.
	 *
	 * @param string $group Catalogue group.
	 * @param string $id Measurement ID.
	 * @return bool
	 */
	public static function has( $group, $id ) {
		$catalog = self::catalog();
		return isset( $catalog[ $group ][ $id ] ); }
}
