<?php
/**
 * Local configuration collection using bounded keyset and attachment cursors.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
/** Collect only configuration evidence from the reviewed catalogue. */
class FooGallery_Usage_Collector {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Bounded projection queries avoid hydrating content and stale object caches.
	const LIMIT       = 100;
	const MAX_SECONDS = 5;
	/**
	 * Registered template definitions for this request.
	 *
	 * @var array
	 */
	private static $definitions = array();

	/**
	 * Start a local scan without allocating an identity.
	 *
	 * @return array
	 */
	public static function start() {
		self::$definitions = array();
		$catalog           = FooGallery_Usage_Registry::catalog();
		return array(
			'complete'           => false,
			'phase'              => 'galleries',
			'cursor'             => 0,
			'attachment_gallery' => 0,
			'attachment_offset'  => 0,
			'attachment_video'   => false,
			'attachment_image'   => false,
			'attachment_color'   => false,
			'attachment_unknown' => false,
			'data'               => array(
				'collection_day' => gmdate( 'Y-m-d' ),
				'environment_type' => FooGallery_Usage::environment_type(),
				'plugin'         => array(
					'version' => self::version(),
					'edition' => self::edition(),
					'trial'   => self::trial(),
				),
				'inventory'      => array(
					'galleries' => '0',
					'albums'    => '0',
				),
				'modules'        => self::initial( $catalog['modules'] ),
				'features'       => self::initial( $catalog['features'] ),
				'actions'        => self::initial( $catalog['actions'] ),
			),
			'initialized'        => false,
		);
	}

	/**
	 * Advance one scan by at most the shared object and time budget.
	 *
	 * @param array $scan Persisted local scan.
	 * @return array|WP_Error
	 */
	public static function step( $scan ) {
		if ( ! is_array( $scan ) || ! isset( $scan['data'], $scan['complete'] ) ) {
			return new WP_Error( 'invalid_scan', __( 'The local scan is invalid. Start another collection.', 'foogallery' ) ); }
		if ( $scan['complete'] ) {
			return $scan; }
		global $wpdb;
		$started = microtime( true );
		$objects = 0;
		$data    =& $scan['data'];
		if ( ! $scan['initialized'] ) {
			self::initialize( $data );
			$scan['initialized'] = true; }
		while ( $objects < self::LIMIT && microtime( true ) - $started < self::MAX_SECONDS ) {
			if ( empty( $scan['attachment_gallery'] ) ) {
				$type = 'albums' === $scan['phase'] ? 'foogallery-album' : FOOGALLERY_CPT_GALLERY;
				$id   = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish' AND ID>%d ORDER BY ID ASC LIMIT 1", $type, $scan['cursor'] ) );
				if ( $wpdb->last_error ) {
					return new WP_Error( 'collection_database', __( 'The local collection could not read the database.', 'foogallery' ) ); }
				if ( ! $id ) {
					if ( 'galleries' === $scan['phase'] ) {
						$scan['phase']  = 'albums';
						$scan['cursor'] = 0;
						continue; }
					FooGallery_Usage_Addon_Adapter::site( $data['modules'], $data['features'] );
					self::summaries( $data['features'] );
					$scan['complete']                  = true;
					break;
				}
				++$objects;
				if ( 'albums' === $scan['phase'] ) {
					$template = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1", $id, 'foogallery_album_template' ) );
					if ( in_array( $template, array( 'default', 'stack' ), true ) ) {
						self::on( $data['features'], 'album.' . $template ); } elseif ( $template ) {
						self::unknown( $data['features'], array( 'album.default', 'album.stack' ) ); }
						$scan['cursor'] = (int) $id;
						continue;
				}
				$scan['attachment_gallery'] = (int) $id;
			}
			$page = self::attachment_page( $scan['attachment_gallery'], $scan['attachment_offset'], self::LIMIT - $objects );
			if ( is_wp_error( $page ) ) {
				return $page; }
			if ( ! empty( $page['ids'] ) ) {
				self::attachment_evidence( $page['ids'], $scan );
				$objects += count( $page['ids'] ); }
			$scan['attachment_offset']  = $page['offset'];
			$scan['attachment_unknown'] = $scan['attachment_unknown'] || $page['unknown'];
			if ( ! $page['complete'] ) {
				break; }
			$record                        = self::record( $scan['attachment_gallery'] );
			$record['has_video']           = $scan['attachment_video'];
			$record['has_image']           = $scan['attachment_image'];
			$record['has_extracted_color'] = $scan['attachment_color'];
			$record['attachment_unknown']  = $scan['attachment_unknown'];
			if ( 'on' === foogallery_get_setting( 'enable_imagegallery_schema', '' ) && 'unavailable' !== $data['features']['gallery.imagegallery_schema'] ) {
				// Project eligibility only: never load a gallery password or render schema.
				$public = $wpdb->get_var( $wpdb->prepare( "SELECT (post_status='publish' AND post_password='') FROM {$wpdb->posts} WHERE ID=%d", $scan['attachment_gallery'] ) );
				$record['schema_public'] = $wpdb->last_error || null === $public ? null : (bool) $public;
			}
			self::evaluate_record( $record, $data['features'] );
			$scan['cursor']             = $scan['attachment_gallery'];
			$scan['attachment_gallery'] = 0;
			$scan['attachment_offset']  = 0;
			$scan['attachment_video']   = false;
			$scan['attachment_image']   = false;
			$scan['attachment_color']   = false;
			$scan['attachment_unknown'] = false;
		}
		$scan['last_batch_objects'] = $objects;
		return $scan;
	}

	/**
	 * Read a bounded chunk of the serialized attachment ID list.
	 *
	 * @param int $id Gallery ID, retained locally.
	 * @param int $offset Serialized byte cursor.
	 * @param int $limit Remaining object allowance.
	 * @return array|WP_Error
	 */
	private static function attachment_page( $id, $offset, $limit ) {
		global $wpdb;
		$result = array(
			'ids'      => array(),
			'offset'   => $offset,
			'complete' => false,
			'unknown'  => false,
		);
		if ( $limit < 1 ) {
			return $result; }
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT SUBSTRING(meta_value,%d,8192) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1", $offset + 1, $id, FOOGALLERY_META_ATTACHMENTS ) );
		if ( $wpdb->last_error ) {
			return new WP_Error( 'collection_database', __( 'Attachment collection could not read the database.', 'foogallery' ) ); }
		if ( null === $raw || '' === $raw ) {
			$result['complete'] = true;
			return $result; }
		$position = 0;
		if ( 0 === $offset ) {
			if ( ! preg_match( '/\Aa:\d+:\{/', $raw, $header ) ) {
				$result['unknown']  = true;
				$result['complete'] = true;
				return $result; }
			$position = strlen( $header[0] );
		}
		$raw_length = strlen( $raw );
		$parsed     = 0;
		while ( $parsed < $limit && $position < $raw_length ) {
			if ( '}' === $raw[ $position ] ) {
				$result['complete'] = true;
				++$position;
				break; }
			if ( ! preg_match( '/\Gi:\d+;(?:i:(\d+);|s:(\d+):"(\d*)";)/', $raw, $match, 0, $position ) ) {
				// A split token is retried in the next bounded chunk. Non-list data is unmeasured.
				if ( strlen( $raw ) < 8192 || 0 === $position ) {
					$result['unknown']  = true;
					$result['complete'] = true; }
				break;
			}
			if ( isset( $match[2] ) && (int) $match[2] !== strlen( $match[3] ) ) {
				$result['unknown']  = true;
				$result['complete'] = true;
				break;
			}
			$value = isset( $match[2] ) ? $match[3] : $match[1];
			if ( '' !== $value ) {
				$result['ids'][] = absint( $value );
			}
			++$parsed;
			$position += strlen( $match[0] );
		}
		$result['offset'] = $offset + $position;
		return $result;
	}

	/**
	 * Accumulate MIME and reviewed metadata presence without loading content.
	 *
	 * @param array $ids Local attachment IDs.
	 * @param array $scan Scan evidence to update.
	 * @return void
	 */
	private static function attachment_evidence( $ids, &$scan ) {
		global $wpdb;
		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- IN placeholders are generated solely from fixed %d/%s tokens; values are passed separately.
		$posts     = $wpdb->get_results( $wpdb->prepare( "SELECT ID,post_mime_type FROM {$wpdb->posts} WHERE post_type='attachment' AND ID IN ($placeholders)", $ids ), ARRAY_A );
		$video_ids = array();
		$images    = array();
		foreach ( $posts as $post ) {
			if ( 0 === strpos( $post['post_mime_type'], 'image/' ) ) {
				$images[ $post['ID'] ] = true; }
			if ( 0 === strpos( $post['post_mime_type'], 'video/' ) ) {
				$video_ids[ $post['ID'] ] = true; }
		}
		$video_key  = defined( 'FOOGALLERY_VIDEO_POST_META' ) ? FOOGALLERY_VIDEO_POST_META : '_foogallery_video_data';
		$keys       = array( $video_key, '_foovideo_video_data', '_foogallery_override_type', '_foogallery_color_rgb', '_foogallery_color_hue', '_foogallery_color_sat', '_foogallery_color_light', '_foogallery_color_index', '_foogallery_color_pallete' );
		$keyholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- IN placeholders are generated solely from fixed %d/%s tokens; values are passed separately.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id,meta_key,CASE WHEN meta_key='_foogallery_override_type' THEN LEFT(meta_value,20) ELSE (LENGTH(meta_value)>0) END AS evidence FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders) AND meta_key IN ($keyholders)", array_merge( $ids, $keys ) ), ARRAY_A );
		foreach ( $rows as $row ) {
			if ( 0 === strpos( $row['meta_key'], '_foogallery_color_' ) && $row['evidence'] ) {
				$scan['attachment_color'] = true; }
			if ( ( in_array( $row['meta_key'], array( $video_key, '_foovideo_video_data' ), true ) && $row['evidence'] ) || ( '_foogallery_override_type' === $row['meta_key'] && 'video' === $row['evidence'] ) ) {
				$video_ids[ $row['post_id'] ] = true; }
		}
		$scan['attachment_video'] = $scan['attachment_video'] || ! empty( $video_ids );
		$scan['attachment_image'] = $scan['attachment_image'] || ! empty( array_diff_key( $images, $video_ids ) );
		if ( $wpdb->last_error || count( $posts ) < count( array_unique( $ids ) ) ) {
			$scan['attachment_unknown'] = true; }
	}

	/**
	 * Read bounded, reviewed gallery metadata directly for the local scan.
	 *
	 * @param int $id Local gallery ID.
	 * @return array
	 */
	private static function metadata( $id ) {
		global $wpdb;
		$keys    = array( FOOGALLERY_META_SETTINGS, FOOGALLERY_META_TEMPLATE, FOOGALLERY_META_DATASOURCE, FOOGALLERY_META_SORT, FOOGALLERY_META_CUSTOM_CSS, FOOGALLERY_META_BLUEPRINT_ENABLED, FOOGALLERY_META_BLUEPRINT_SET );
		$holders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- IN placeholders are generated solely from fixed %d/%s tokens; values are passed separately.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key,CASE WHEN meta_key=%s THEN (LENGTH(meta_value)>0) ELSE LEFT(meta_value,65537) END AS value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key IN ($holders)", array_merge( array( FOOGALLERY_META_CUSTOM_CSS, $id ), $keys ) ), ARRAY_A );
		$meta = array();
		foreach ( $rows as $row ) {
			$meta[ $row['meta_key'] ] = $row['value']; }
		return $meta;
	}

	/**
	 * Resolve the effective gallery settings, including a bounded blueprint chain.
	 *
	 * @param int $id Local gallery ID.
	 * @return array
	 */
	private static function record( $id ) {
		$own       = self::metadata( $id );
		$meta      = $own;
		$seen      = array( $id => true );
		$unknown   = false;
		$blueprint = 0;
		// Blueprint runtime replaces template/settings/sort/CSS while keeping original attachments.
		if ( self::runtime_provider( 'FooGallery_Pro_Gallery_Blueprints' ) ) {
			for ( $depth = 0; $depth < 10; ++$depth ) {
				$parent = isset( $meta[ FOOGALLERY_META_BLUEPRINT_SET ] ) ? absint( $meta[ FOOGALLERY_META_BLUEPRINT_SET ] ) : 0;
				if ( ! $parent ) {
					break; }
				if ( isset( $seen[ $parent ] ) || 'foogallery' !== get_post_type( $parent ) || in_array( get_post_status( $parent ), array( 'trash', 'auto-draft', false ), true ) ) {
					$unknown = true;
					break; }
				$seen[ $parent ] = true;
				$blueprint       = $parent;
				$meta            = self::metadata( $parent );
				if ( 9 === $depth ) {
					$unknown = true; }
			}
		}
		$template = isset( $meta[ FOOGALLERY_META_TEMPLATE ] ) ? $meta[ FOOGALLERY_META_TEMPLATE ] : '';
		$raw      = isset( $meta[ FOOGALLERY_META_SETTINGS ] ) ? $meta[ FOOGALLERY_META_SETTINGS ] : '';
		$settings = array();
		if ( strlen( $raw ) > 65536 ) {
			$unknown = true; } elseif ( '' !== $raw ) {
			// Settings are arrays only; never instantiate serialized classes.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Read existing WordPress serialized metadata with class instantiation disabled; malformed values become unknown.
			$settings = @unserialize( $raw, array( 'allowed_classes' => false ) );
			if ( ! is_array( $settings ) ) {
				$settings = array();
				$unknown  = true; }
			}
			$definition = self::definition( $template );
			if ( empty( $settings ) && is_array( $definition ) ) {
				$settings = foogallery_build_default_settings_for_gallery_template( $template ); }
			$settings['usage_blueprint_definition'] = isset( $own[ FOOGALLERY_META_BLUEPRINT_ENABLED ] ) ? $own[ FOOGALLERY_META_BLUEPRINT_ENABLED ] : '';
			$settings['usage_blueprint_id']         = $blueprint;
			$source                                 = isset( $own[ FOOGALLERY_META_DATASOURCE ] ) && '' !== $own[ FOOGALLERY_META_DATASOURCE ] ? $own[ FOOGALLERY_META_DATASOURCE ] : foogallery_default_datasource();
			return array(
				'id'         => $id,
				'template'   => $template,
				'datasource' => $source,
				'settings'   => $settings,
				'custom_css' => ! empty( $meta[ FOOGALLERY_META_CUSTOM_CSS ] ),
				'sorting'    => isset( $meta[ FOOGALLERY_META_SORT ] ) ? $meta[ FOOGALLERY_META_SORT ] : '',
				'unknown'    => $unknown,
			);
	}

	/**
	 * Measure coarse inventory and current runtime availability.
	 *
	 * @param array $data Measurement to initialize.
	 * @return void
	 */
	private static function initialize( &$data ) {
		$gallery                        = wp_count_posts( FOOGALLERY_CPT_GALLERY );
		$album                          = wp_count_posts( 'foogallery-album' );
		$data['inventory']['galleries'] = self::bucket( isset( $gallery->publish ) ? (int) $gallery->publish : 0 );
		$data['inventory']['albums']    = self::bucket( isset( $album->publish ) ? (int) $album->publish : 0 );
		self::modules( $data['modules'] );
		self::availability( $data['modules'], $data['features'], $data['actions'] );
		self::global_settings( $data['features'] );
	}

	/**
	 * Measure reviewed global settings independently of gallery inventory.
	 *
	 * @param array $states Availability-gated feature states.
	 * @return void
	 */
	private static function global_settings( &$states ) {
		$toggles = array(
			'advanced_attachment_modal'       => 'on',
			'override_gallery_shortcode'      => false,
			'use_original_thumbs'            => false,
			'enqueue_polyfills'              => false,
			'force_https'                    => false,
			'disable_media_category_sidebar' => false,
			'enable_gallery_descriptions'    => false,
			'enable_imagegallery_schema'     => '',
		);
		foreach ( $toggles as $key => $default ) {
			$id = 'settings.' . $key;
			if ( 'disabled' !== $states[ $id ] ) {
				continue;
			}
			$value = foogallery_get_setting( $key, $default );
			if ( null !== $value && ! is_scalar( $value ) ) {
				$states[ $id ] = 'unknown';
				continue;
			}
			// The polyfill loader uses truthiness; the other controls use literal "on".
			self::yes( $states, $id, 'enqueue_polyfills' === $key ? (bool) $value : 'on' === $value );
		}
		foreach ( array( 'custom_css', 'custom_js' ) as $key ) {
			$value = foogallery_get_setting( $key, '' );
			$id    = 'settings.' . $key;
			if ( is_string( $value ) ) {
				self::yes( $states, $id, '' !== trim( $value ) );
			} elseif ( false !== $value && null !== $value ) {
				$states[ $id ] = 'unknown';
			}
		}
		foreach ( array( 'gallery_creator_role' => 'administrator', 'album_creator_role' => 'inherit' ) as $key => $default ) {
			$id = 'settings.' . $key;
			if ( 'disabled' !== $states[ $id ] ) {
				continue;
			}
			$value = 'gallery_creator_role' === $key ? foogallery_setting_gallery_creator_role() : foogallery_get_setting( $key, $default );
			if ( $default === $value ) {
				continue;
			}
			$states[ $id ] = is_string( $value ) && null !== get_role( $value ) ? 'enabled' : 'unknown';
		}
		$engine  = foogallery_get_setting( 'thumb_engine', 'default' );
		$engines = foogallery_thumb_available_engines();
		if ( ! is_string( $engine ) || ! is_array( $engines ) ) {
			$states['settings.thumb_engine'] = 'unknown';
		} elseif ( 'default' !== $engine && isset( $engines[ $engine ] ) ) {
			// Mirror selection/fallback without constructing an engine or generating images.
			$class = isset( $engines[ $engine ]['class'] ) ? $engines[ $engine ]['class'] : null;
			$states['settings.thumb_engine'] = is_string( $class ) && class_exists( $class ) ? 'enabled' : 'unknown';
		}
	}
	/**
	 * Check that a provider registered callbacks, beyond merely loading its class.
	 *
	 * @param string $provider_class Reviewed provider class.
	 * @param string $method Optional runtime callback method.
	 * @return bool
	 */
	public static function runtime_provider( $provider_class, $method = '' ) {
		global $wp_filter;
		foreach ( $wp_filter as $hook ) {
			$callbacks = is_object( $hook ) && isset( $hook->callbacks ) ? $hook->callbacks : array();
			foreach ( $callbacks as $priority ) {
				foreach ( $priority as $callback ) {
					$fn = $callback['function'];
					if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) && ( '' === $method || $method === $fn[1] ) && ( ( is_object( $fn[0] ) && is_a( $fn[0], $provider_class ) ) || $fn[0] === $provider_class ) ) {
						return true; }
				}
			}
		}
		return false;
	}
	/**
	 * Cache registered template definitions within the current request.
	 *
	 * @param string $template Reviewed template slug.
	 * @return array|false
	 */
	private static function definition( $template ) {
		if ( ! array_key_exists( $template, self::$definitions ) ) {
			self::$definitions[ $template ] = foogallery_get_gallery_template( $template ); }
		return self::$definitions[ $template ];
	}
	/**
	 * Project only reviewed module IDs from the existing Features-page inventory.
	 *
	 * @param array $states Module states to update.
	 * @return void
	 */
	private static function modules( &$states ) {
		$api  = new FooGallery_Extensions_API();
		$real = array();
		foreach ( $api->get_all_for_view() as $row ) {
			if ( ! is_array( $row ) || empty( $row['slug'] ) ) {
				continue; }
			$slug = in_array( $row['slug'], array( 'foogallery-whitelabeling', 'foogallery-whitelabelling-addon' ), true ) ? 'foogallery-whitelabelling' : $row['slug'];
			if ( ! isset( $states[ $slug ] ) ) {
				continue; }
			if ( ! array_key_exists( 'is_active', $row ) ) {
				if ( ! isset( $real[ $slug ] ) ) {
					$states[ $slug ] = 'unavailable';
				} continue; }
			if ( isset( $real[ $slug ] ) && 'enabled' === $states[ $slug ] ) {
				continue;
			}
			$real[ $slug ]   = true;
			$states[ $slug ] = ! empty( $row['has_errors'] ) ? 'unknown' : ( $row['is_active'] ? 'enabled' : 'disabled' );
		}
		foreach ( $states as $id => $value ) {
			if ( 'unknown' === $value && ! isset( $real[ $id ] ) ) {
				$states[ $id ] = 'unavailable'; }
		}
		FooGallery_Usage_Addon_Adapter::modules( $states );
	}
	/**
	 * Gate configured-feature measurements on their actual runtime dependencies.
	 *
	 * @param array $modules Module inventory.
	 * @param array $states Feature states to initialize.
	 * @param array $actions Action states to initialize.
	 * @return void
	 */
	private static function availability( $modules, &$states, &$actions ) {
		foreach ( $states as $id => $unused ) {
			$states[ $id ] = 'disabled'; }
		$groups = array(
			'FooGallery_Pro_Hover_Presets'       => array( 'gallery.hover_effects' ),
			'FooGallery_Pro_Instagram_Filters'   => array( 'gallery.thumbnail_effects' ),
			'FooGallery_Pro_Advanced_Captions'   => array( 'gallery.custom_captions.gallery', 'gallery.custom_captions.lightbox' ),
			'FooGallery_Pro_ImageGallery_Schema' => array( 'gallery.imagegallery_schema', 'settings.enable_imagegallery_schema' ),
			'FooGallery_Pro_Gallery_Shortcode_Override' => array( 'settings.override_gallery_shortcode' ),
			'FooGallery_Pro_Media_Folders'       => array( 'settings.disable_media_category_sidebar' ),
			'FooGallery_Pro_Buttons'             => array( 'gallery.cta.custom', 'gallery.cta.download' ),
		);
		foreach ( $groups as $provider => $ids ) {
			if ( ! self::runtime_provider( $provider ) ) {
				foreach ( $ids as $id ) {
					$states[ $id ] = 'unavailable'; }
			}
		}
		$module_groups = array(
			'albums'                        => array( 'album.default', 'album.stack', 'settings.album_creator_role', 'settings.enable_gallery_descriptions' ),
			'foogallery-filtering'          => array( 'gallery.filtering.simple', 'gallery.filtering.multilevel', 'gallery.filtering.search', 'settings.disable_media_category_sidebar' ),
			'foogallery-paging'             => array( 'gallery.pagination.numbered', 'gallery.pagination.infinite', 'gallery.pagination.load_more' ),
			'foogallery-video'              => array( 'gallery.video' ),
			'foogallery-exif'               => array( 'gallery.exif' ),
			'foogallery-protection'         => array( 'gallery.protection.right_click', 'gallery.protection.watermark', 'gallery.protection.thumbnail_watermark' ),
			'foogallery-colors'             => array( 'gallery.colors.extracted', 'gallery.colors.sorting', 'gallery.colors.background', 'gallery.colors.lightbox' ),
			'foogallery-gallery-blueprints' => array( 'gallery.blueprints.definition', 'gallery.blueprints.use' ),
			'foogallery-user-uploads'       => array( 'addon.user_uploads_form', 'addon.user_uploads_video' ),
			'foogallery-social'             => array( 'addon.social.enabled', 'addon.social.likes', 'addon.social.comments', 'addon.social.sharing' ),
			'foogallery-proofing'           => array( 'addon.proofing_sessions' ),
			'foogallery-woocommerce'        => array( 'datasource.woocommerce', 'gallery.ecommerce.master_product', 'layout.product', 'gallery.ecommerce.lightbox_product_info', 'gallery.ecommerce.cta_buttons' ),
		);
		foreach ( $module_groups as $module => $ids ) {
			$state = isset( $modules[ $module ] ) ? $modules[ $module ] : 'unavailable';
			if ( 'foogallery-woocommerce' === $module && ! function_exists( 'wc_get_product' ) ) {
				$state = 'unavailable'; }
			if ( 'enabled' !== $state ) {
				foreach ( $ids as $id ) {
					$states[ $id ] = 'unknown' === $state ? 'unknown' : 'unavailable'; }
			}
		}
		foreach ( self::layout_map() as $template => $id ) {
			if ( ! self::definition( $template ) ) {
				$states[ $id ] = 'unavailable'; }
		}
		foreach ( FooGallery_Usage_Registry::datasource_map() as $source => $id ) {
			// Editor visibility can be restricted independently of the rendering provider.
			if ( false === has_filter( 'foogallery_datasource_' . $source . '_attachments' ) ) {
				$states[ $id ] = 'unavailable'; }
		}
		if ( ! foogallery_mobile_settings_is_entitled() ) {
			$states['gallery.mobile_settings'] = 'unavailable';
		}
		// Ribbons can be supplied independently by PRO or WooCommerce.
		$ribbons = array(
			'custom' => self::runtime_provider( 'FooGallery_Pro_Ribbons' ) ? 'disabled' : 'unavailable',
			'commerce' => $states['datasource.woocommerce'],
			'combined' => 'unknown',
		);
		self::rollup( $ribbons, 'combined', array( 'custom', 'commerce' ) );
		$states['gallery.ecommerce.sales_ribbons'] = $ribbons['combined'];
		foreach (
			array(
				'bulk_copy_30d'   => 'foogallery-bulk-copy',
				'import_30d'      => 'foogallery-import-export',
				'export_30d'      => 'foogallery-import-export',
				'migration_30d'   => 'foogallery-migrate',
				'media_audit_30d' => 'foogallery-media-audit',
			) as $id => $module
		) {
			$actions[ $id ] = isset( $modules[ $module ] ) && 'enabled' === $modules[ $module ] ? 'unknown' : 'unavailable';
		}
	}

	/**
	 * Project one published gallery without rendering or fetching datasource content.
	 *
	 * @param array $r Local effective gallery configuration.
	 * @param array $states Feature states to update.
	 * @return void
	 */
	public static function evaluate_record( $r, &$states ) {
		self::schema( $r, $states );
		$t          = isset( $r['template'] ) ? $r['template'] : '';
		$x          = isset( $r['settings'] ) && is_array( $r['settings'] ) ? $r['settings'] : array();
		$definition = self::definition( $t );
		$layouts    = self::layout_map();
		if ( ! empty( $r['unknown'] ) ) {
			$affected = array();
			foreach ( FooGallery_Usage_Registry::catalog()['features'] as $id => $field ) {
				if ( 'published_gallery_configuration' === $field['scope'] ) {
					$affected[] = $id;
				}
			} self::unknown( $states, $affected );
			return; }
		$source = isset( $r['datasource'] ) && is_string( $r['datasource'] ) && '' !== $r['datasource'] ? $r['datasource'] : foogallery_default_datasource();
		$sources = FooGallery_Usage_Registry::datasource_map();
		if ( false === strpos( $t, '_promo' ) && isset( $sources[ $source ] ) ) {
			// Datasource selection also applies to third-party gallery layouts.
			self::on( $states, $sources[ $source ] );
		}
		if ( isset( $layouts[ $t ] ) ) {
			self::on( $states, $layouts[ $t ] ); } elseif ( $t && false === strpos( $t, '_promo' ) ) {
			self::unknown( $states, array_values( $layouts ) ); }
			self::yes( $states, 'gallery.custom_css', ! empty( $r['custom_css'] ) );
			$blueprint = isset( $x['usage_blueprint_id'] ) ? absint( $x['usage_blueprint_id'] ) : 0;
			self::yes( $states, 'gallery.blueprints.definition', isset( $x['usage_blueprint_definition'] ) && 'enabled' === $x['usage_blueprint_definition'] );
			self::yes( $states, 'gallery.blueprints.use', $blueprint > 0 && 'foogallery' === get_post_type( $blueprint ) && ! in_array( get_post_status( $blueprint ), array( 'trash', 'auto-draft', false ), true ) );
			if ( ! isset( $layouts[ $t ] ) ) {
				return; }
			$v   = function ( $key, $fallback = '' ) use ( $x, $t ) {
				return self::value( $x, $t, $key, $fallback );
			};
		$support = function ( $key ) use ( $definition ) {
			return is_array( $definition ) && ! empty( $definition[ $key ] );
		};
		if ( $support( 'paging_support' ) ) {
			$paging = $v( 'paging_type' );
			foreach ( array(
				'dots'       => 'gallery.pagination.dots',
				'pagination' => 'gallery.pagination.numbered',
				'infinite'   => 'gallery.pagination.infinite',
				'loadMore'   => 'gallery.pagination.load_more',
			) as $mode => $id ) {
				self::yes( $states, $id, $paging === $mode ); }
			if ( '' !== $paging && ! in_array( $paging, array( 'dots', 'pagination', 'infinite', 'loadMore' ), true ) ) {
				self::unknown( $states, array( 'gallery.pagination.dots', 'gallery.pagination.numbered', 'gallery.pagination.infinite', 'gallery.pagination.load_more' ) ); }
		}
		if ( $support( 'filtering_support' ) ) {
			$filtering = $v( 'filtering_type' );
			self::yes( $states, 'gallery.filtering.simple', '' !== $filtering && 'multi' !== $filtering );
			self::yes( $states, 'gallery.filtering.multilevel', 'multi' === $filtering );
			self::yes( $states, 'gallery.filtering.search', '' !== $filtering && '' !== $v( 'filtering_search' ) );
		}
		$lightbox = $v( 'lightbox', foogallery_get_setting( 'lightbox', 'foogallery' ) );
		$link     = $v( 'thumbnail_link', 'image' );
		if ( in_array( $link, array( 'image', 'custom' ), true ) ) {
			$boxes = array(
				'foogallery' => 'lightbox.foogallery',
				'foobox'     => 'lightbox.foobox',
				'none'       => null,
				''           => null,
			);
			if ( array_key_exists( $lightbox, $boxes ) ) {
				if ( null !== $boxes[ $lightbox ] ) {
					self::on( $states, $boxes[ $lightbox ] );
				}
			} else {
				self::on( $states, 'lightbox.other' ); }
		}
		$gallery_lazy = $v( 'lazyload' );
		$disable_lazy = foogallery_get_setting( 'disable_lazy_loading', '' );
		if ( $support( 'lazyload_support' ) && '' === $gallery_lazy && 'on' !== $disable_lazy ) {
			$lazy_mode = foogallery_get_setting( 'lazy_loading_mode', 'seo' );
			self::on( $states, 'legacy' === $lazy_mode ? 'settings.lazy_loading.standard' : 'settings.lazy_loading.seo' ); }
		$caption = $v( 'captions_type' );
		self::yes( $states, 'gallery.custom_captions.gallery', 'custom' === $caption && '' !== trim( $v( 'caption_custom_template' ) ) );
		self::yes( $states, 'gallery.custom_captions.lightbox', 'custom' === $v( 'lightbox_caption_override' ) && '' !== trim( $v( 'lightbox_caption_custom_template' ) ) && 'foogallery' === $lightbox );
		self::yes( $states, 'gallery.exif', 'yes' === $v( 'exif_view_status' ) && ( $support( 'panel_support' ) || 'foogallery' === $lightbox ) );
		$product_id = absint( $v( 'ecommerce_master_product_id' ) );
		$product    = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		$master     = is_object( $product );
		self::yes( $states, 'gallery.ecommerce.master_product', $master );
		$products  = 'woocommerce' === $source || $master;
		$watermark = 'yes' === $v( 'protection_watermarking' );
		self::yes( $states, 'gallery.protection.right_click', 'yes' === $v( 'protection_no_right_click' ) );
		self::yes( $states, 'gallery.protection.watermark', $watermark );
		self::yes( $states, 'gallery.protection.thumbnail_watermark', $watermark && 'yes' === $v( 'protection_watermark_thumbnails' ) );
		self::yes( $states, 'gallery.colors.extracted', ! empty( $r['has_extracted_color'] ) );
		self::yes( $states, 'gallery.colors.sorting', isset( $r['sorting'] ) && in_array( $r['sorting'], array( 'color', 'color-desc' ), true ) );
		self::yes( $states, 'gallery.colors.background', 'disabled' !== $v( 'color_apply_bg', 'disabled' ) );
		self::yes( $states, 'gallery.colors.lightbox', 'foogallery' === $lightbox && 'disabled' !== $v( 'color_lightbox_bg', 'disabled' ) );
		$info = $products && 'foogallery' === $lightbox && in_array( $v( 'ecommerce_lightbox_product_information', 'none' ), array( 'left', 'right', 'top', 'bottom' ), true );
		self::yes( $states, 'gallery.ecommerce.lightbox_product_info', $info );
		$show_buttons = 'hidden' !== $v( 'buttons_hide' );
		self::yes( $states, 'gallery.cta.custom', $show_buttons && '' !== $v( 'add_custom_button' ) );
		self::yes( $states, 'gallery.cta.download', $show_buttons && '' !== $v( 'add_download_button' ) );
		foreach ( array(
			'add_to_cart'  => 'gallery.ecommerce.cta_buttons',
			'variable'     => 'gallery.ecommerce.cta_buttons',
			'view_product' => 'gallery.ecommerce.cta_buttons',
		) as $key => $id ) {
			self::yes( $states, $id, $products && $show_buttons && 'shown' === $v( 'ecommerce_button_' . $key ) ); }
		$show_ribbons = 'hidden' !== $v( 'ribbons_hide' );
		foreach ( array(
			'sale'       => 'gallery.ecommerce.sales_ribbons',
			'featured'   => 'gallery.ecommerce.sales_ribbons',
			'outofstock' => 'gallery.ecommerce.sales_ribbons',
			'backorder'  => 'gallery.ecommerce.sales_ribbons',
		) as $key => $id ) {
			self::yes( $states, $id, $products && 'unavailable' !== $states['datasource.woocommerce'] && $show_ribbons && self::meaningful( $v( 'ecommerce_' . $key . '_ribbon_type' ) ) ); }
		self::yes( $states, 'gallery.ecommerce.sales_ribbons', $show_ribbons && '' !== $v( 'add_class_ribbon' ) && self::runtime_provider( 'FooGallery_Pro_Ribbons' ) );
		self::mobile( $x, $t, $states );
		$presets = array( 'sadie', 'layla', 'oscar', 'sarah', 'goliath', 'jazz', 'lily', 'ming', 'selena', 'steve', 'zoe' );
		foreach ( $presets as $preset ) {
			if ( 'preset' === $v( 'hover_effect_type' ) && 'fg-preset fg-' . $preset === $v( 'hover_effect_preset' ) ) {
				self::on( $states, 'gallery.hover_effects' ); }
		}
		$effects = array( '1977', 'amaro', 'brannan', 'clarendon', 'earlybird', 'lofi', 'poprocket', 'reyes', 'toaster', 'walden', 'xpro2', 'xtreme' );
		foreach ( $effects as $effect ) {
			if ( 'fg-filter-' . $effect === $v( 'instagram' ) ) {
				self::on( $states, 'gallery.thumbnail_effects' ); }
		}
		// Fade In is the default; None is off, not use of another animation.
		$loaded = $v( 'loaded_effect', 'fg-loaded-fade-in' );
		self::yes( $states, 'gallery.loading.customized', in_array( $loaded, array(
			'fg-loaded-slide-up', 'fg-loaded-slide-down', 'fg-loaded-slide-left', 'fg-loaded-slide-right',
			'fg-loaded-scale-up', 'fg-loaded-scale-down', 'fg-loaded-swing-down', 'fg-loaded-drop',
			'fg-loaded-fly', 'fg-loaded-flip',
		), true ) );
		if ( 'disabled' !== $v( 'video_enabled' ) ) {
			self::yes( $states, 'gallery.video', ! empty( $r['has_video'] ) );
			if ( ! empty( $r['attachment_unknown'] ) || ! in_array( $source, array( '', 'media_library' ), true ) ) {
				self::unknown( $states, array( 'gallery.video', 'gallery.colors.extracted' ) ); }
		}
		FooGallery_Usage_Addon_Adapter::gallery( $r, $states );
	}

	/**
	 * Measure explicit paid mobile overrides against their desktop values.
	 *
	 * @param array  $settings Local saved settings.
	 * @param string $template Active template slug.
	 * @param array  $states Feature states to update.
	 * @return void
	 */
	private static function mobile( $settings, $template, &$states ) {
		if ( ! foogallery_mobile_settings_is_entitled() ) {
			return; }
		$definition = self::definition( $template );
		if ( ! $definition ) {
			return; }
		foreach ( foogallery_get_fields_for_template( $definition ) as $field ) {
			if ( foogallery_mobile_field_is_free( $field, $template ) ) {
				continue; }
			$mobile = foogallery_get_mobile_field_for_template_field( $field, null, $template );
			if ( ! is_array( $mobile ) || empty( $mobile['id'] ) ) {
				continue; }
			$key   = $template . '_' . $mobile['id'];
			$alias = isset( $mobile['alias'] ) ? $template . '_' . $mobile['alias'] : '';
			if ( ! array_key_exists( $key, $settings ) && ( ! $alias || ! array_key_exists( $alias, $settings ) ) ) {
				continue; }
			$value = array_key_exists( $key, $settings ) ? $settings[ $key ] : $settings[ $alias ];
			if ( null === $value || '' === $value ) {
				continue; }
			$desktop = self::value( $settings, $template, $field['id'], isset( $field['default'] ) ? $field['default'] : '' );
			if ( foogallery_mobile_setting_values_match( $desktop, $value ) ) {
				continue; }
			self::on( $states, 'gallery.mobile_settings' );
		}
	}
	/**
	 * Measure schema configuration on eligible published galleries without rendering.
	 *
	 * @param array $record Local gallery and attachment evidence.
	 * @param array $states Feature states to update.
	 * @return void
	 */
	private static function schema( $record, &$states ) {
		if ( 'on' !== foogallery_get_setting( 'enable_imagegallery_schema', '' ) ) {
			return;
		}
		if ( ! isset( $record['schema_public'] ) ) {
			self::unknown( $states, array( 'gallery.imagegallery_schema' ) );
			return;
		}
		if ( ! $record['schema_public'] ) {
			return;
		}
		$source = isset( $record['datasource'] ) ? $record['datasource'] : 'media_library';
		if ( ! in_array( $source, array( '', 'media_library' ), true ) ) {
			// Dynamic contents cannot be established without fetching their datasource.
			self::unknown( $states, array( 'gallery.imagegallery_schema' ) );
		} elseif ( ! empty( $record['has_image'] ) ) {
			self::on( $states, 'gallery.imagegallery_schema' );
		} elseif ( ! empty( $record['attachment_unknown'] ) ) {
			self::unknown( $states, array( 'gallery.imagegallery_schema' ) );
		}
	}
	/**
	 * Derive advertised promotion summaries from detailed measurements.
	 *
	 * @param array $states Feature states to summarize.
	 * @return void
	 */
	public static function finalize_features( &$states ) {
		self::summaries( $states ); }
	/**
	 * Map reviewed runtime layouts to stable reporting IDs.
	 *
	 * @return array
	 */
	private static function layout_map() {
		return array(
			'default'          => 'layout.default',
			'masonry'          => 'layout.masonry',
			'justified'        => 'layout.justified',
			'carousel'         => 'layout.carousel',
			'image-viewer'     => 'layout.image_viewer',
			'simple_portfolio' => 'layout.simple_portfolio',
			'thumbnail'        => 'layout.thumbnail',
			'polaroid_new'     => 'layout.polaroid',
			'foogridpro'       => 'layout.grid',
			'slider'           => 'layout.slider',
			'spotlight'        => 'layout.spotlight',
			'product'          => 'layout.product',
		); }
	/**
	 * Read only a scalar from the active template namespace.
	 *
	 * @param array  $settings Local settings.
	 * @param string $template Active template slug.
	 * @param string $key Reviewed field ID.
	 * @param string $fallback Runtime default.
	 * @return string
	 */
	private static function value( $settings, $template, $key, $fallback = '' ) {
		$key = $template . '_' . $key;
		return array_key_exists( $key, $settings ) && null !== $settings[ $key ] && is_scalar( $settings[ $key ] ) ? (string) $settings[ $key ] : $fallback; }
	/**
	 * Test whether a reviewed mode represents an enabled configuration.
	 *
	 * @param string $value Reviewed mode.
	 * @return bool
	 */
	private static function meaningful( $value ) {
		return '' !== $value && ! in_array( $value, array( 'disabled', 'none', 'off', 'no', '0' ), true ); }
	/**
	 * Accumulate positive evidence without overriding unavailable dependencies.
	 *
	 * @param array  $states Feature states.
	 * @param string $id Reviewed feature ID.
	 * @param bool   $enabled Whether positive evidence exists.
	 * @return void
	 */
	private static function yes( &$states, $id, $enabled ) {
		if ( $enabled ) {
			self::on( $states, $id ); } }
	/**
	 * Keep positive evidence found in any published gallery.
	 *
	 * @param array  $states Feature states.
	 * @param string $id Reviewed feature ID.
	 * @return void
	 */
	private static function on( &$states, $id ) {
		if ( isset( $states[ $id ] ) && 'unavailable' !== $states[ $id ] ) {
			$states[ $id ] = 'enabled'; } }
	/**
	 * Mark ambiguous evidence without erasing known use or unavailable dependencies.
	 *
	 * @param array $states Feature states.
	 * @param array $ids Affected reviewed IDs.
	 * @return void
	 */
	private static function unknown( &$states, $ids ) {
		foreach ( $ids as $id ) {
			if ( isset( $states[ $id ] ) && ! in_array( $states[ $id ], array( 'enabled', 'unavailable' ), true ) ) {
				$states[ $id ] = 'unknown'; }
		} }
	/**
	 * Create unmeasured states for the fixed catalogue.
	 *
	 * @param array $items Reviewed catalogue entries.
	 * @return array
	 */
	private static function initial( $items ) {
		return array_fill_keys( array_keys( $items ), 'unknown' ); }
	/**
	 * Reduce a published object count to an approved coarse bucket.
	 *
	 * @param int $n Local count.
	 * @return string
	 */
	private static function bucket( $n ) {
		if ( $n < 1 ) {
			return '0';
		} if ( 1 === $n ) {
			return '1';
		} if ( $n <= 5 ) {
			return '2-5';
		} if ( $n <= 20 ) {
			return '6-20';
		} return $n <= 100 ? '21-100' : '101+'; }
	/**
	 * Return a bounded plugin release version.
	 *
	 * @return string
	 */
	private static function version() {
		$version = FOOGALLERY_VERSION;
		return preg_match( '/^\d+\.\d+\.\d+(?:\.\d+)?$/D', $version ) ? $version : 'development'; }
	/**
	 * Return the coarse current entitlement without license details.
	 *
	 * @return string
	 */
	private static function edition() {
		if ( ! foogallery_is_pro() ) {
			return 'free';
		} foreach ( array(
			'commerce'   => 'commerce',
			'pro'        => 'expert',
			'prostarter' => 'starter',
		) as $plan => $edition ) {
			if ( foogallery_fs()->is_plan_or_trial( $plan ) ) {
				return $edition;
			}
		} return 'unknown'; }
	/**
	 * Return the current trial lifecycle state without trial dates or identifiers.
	 *
	 * @return string
	 */
	private static function trial() {
		$freemius = foogallery_fs();
		if ( $freemius->is_trial() ) {
			return 'active';
		}
		return $freemius->is_trial_utilized() ? 'used' : 'none';
	}
	/**
	 * Roll distinct feature measurements into aggregate feature summaries.
	 *
	 * @param array $s Feature states.
	 * @return void
	 */
	private static function summaries( &$s ) {
		$map = array(
			'gallery.pagination.enabled'      => array( 'gallery.pagination.numbered', 'gallery.pagination.infinite', 'gallery.pagination.load_more' ),
			'gallery.custom_captions.enabled' => array( 'gallery.custom_captions.gallery', 'gallery.custom_captions.lightbox' ),
			'gallery.protection.enabled'      => array( 'gallery.protection.right_click', 'gallery.protection.watermark', 'gallery.protection.thumbnail_watermark' ),
			'gallery.colors.enabled'          => array( 'gallery.colors.extracted', 'gallery.colors.sorting', 'gallery.colors.background', 'gallery.colors.lightbox' ),
			'gallery.cta.enabled'             => array( 'gallery.cta.custom', 'gallery.cta.download', 'gallery.ecommerce.cta_buttons' ),
			'gallery.blueprints.enabled'      => array( 'gallery.blueprints.definition', 'gallery.blueprints.use' ),
			'gallery.ecommerce.enabled'       => array( 'datasource.woocommerce', 'layout.product', 'gallery.ecommerce.master_product', 'gallery.ecommerce.lightbox_product_info', 'gallery.ecommerce.cta_buttons' ),
		);
		foreach ( $map as $key => $children ) {
			self::rollup( $s, $key, $children );
		}
	}
	/**
	 * Derive a summary while preserving uncertainty and unavailable dependencies.
	 *
	 * @param array  $s Feature states.
	 * @param string $id Summary ID.
	 * @param array  $children Detailed feature IDs.
	 * @return void
	 */
	private static function rollup( &$s, $id, $children ) {
		if ( ! isset( $s[ $id ] ) ) {
			return;
		}$unknown  = false;
		$available = false;
		foreach ( $children as $child ) {
			if ( ! isset( $s[ $child ] ) ) {
				continue;
			}if ( 'enabled' === $s[ $child ] ) {
				$s[ $id ] = 'enabled';
				return;
			}if ( 'unknown' === $s[ $child ] ) {
				$unknown = true;
			}if ( 'unavailable' !== $s[ $child ] ) {
				$available = true;
			}
		}$s[ $id ] = $unknown ? 'unknown' : ( $available ? 'disabled' : 'unavailable' );}
}
