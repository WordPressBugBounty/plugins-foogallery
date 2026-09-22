<?php
/**
 * Media audit configuration, rule descriptors, storage and FooGallery accessors.
 *
 * Ported from the standalone POC v0.10.1 (ruleset r3.8).
 * See docs/media-audit/poc-review.md for known issues intentionally retained.
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Thresholds. Every one of these is a judgement call, not a measured value —
// see the assumption register (B1-B11). The exception is del01_size_exponent,
// which was measured over 45 real JPEG re-encodes.
// ---------------------------------------------------------------------------

function foogallery_media_audit_config() {
	return apply_filters(
		'foogallery_media_audit_config',
		array(
			'del01_intermediate_slack'      => 1.3,
			'del01_min_waste_bytes'         => 40960,
			'del01_size_exponent'           => 1.4,      // measured: fitted 1.31, range 1.20-1.63.
												// 2.0 (pixel area) overstates waste ~15%.
			'above_fold_items'              => 12,
			'src01_dimension_floor'         => 2500,
			'src01_bytes_floor'             => 1048576,
			'src07_min_bytes'               => 262144,
			'src07_density'                 => array( 'JPEG' => 0.75, 'PNG' => 2.0, 'WebP' => 0.75, 'AVIF' => 0.75 ),
			'a11y04_dupe_alt_min'           => 3,
			// When a gallery-scoped rule fires on almost every gallery, per-gallery rows stop
			// helping: there is nothing left to prioritise. Report it once, site-wide, instead.
			'a11y01_sitewide_share'         => 0.80,
			'a11y01_sitewide_min_galleries' => 4,
			'a11y05_alt_max_len'            => 125,
			'cfg01_lazy_item_count'         => 20,
			'cfg01_lazy_weight_bytes'       => 786432,
			'cfg02_paging_item_count'       => 100,
			'noise_population_share'        => 0.60,
			'max_attachments'               => 20000,    // hard cap; support sites can be huge
		// HTTP reachability (FG-INT-02). Every request is a real network round trip on
		// someone else's server, so the budget is small and shared across the whole scan.
			'int02_sample_per_gallery'      => 2,
			// The first r3.5 run covered 53 of 133 galleries: 40 requests is simply too few for
			// a site with a lot of galleries, and the binding constraint should be TIME, not an
			// arbitrary count. 200 requests is enough for one image per gallery on almost any
			// real site; the wall clock is what actually protects a slow server, because 200
			// requests × a 5s timeout is over 16 minutes in the worst case and a count cap
			// cannot see that coming.
			'int02_max_requests'            => 200,    // ceiling, not a target
			'int02_max_seconds'             => 20,     // the real guard — stop when this is reached
			'int02_max_missing_checks'      => 20,     // separate budget: verifying missing files over HTTP
			'int02_timeout'                 => 5,
			// Ask FooGallery to resolve dynamic galleries (post_query, woocommerce, taxonomies).
			// Set false to skip it — that is the only way to guarantee the scan writes nothing
			// at all, because two of those datasources populate a 24h transient when they run.
			'resolve_dynamic_datasources'   => true,
		)
	);
}

/** Count Unicode code points for alt-text length checks.
 *
 * @param string $text Text to measure.
 * @return int
 */
function foogallery_media_audit_text_length( $text ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $text, 'UTF-8' );
	}
	$count = preg_match_all( '/./us', $text, $matches );
	return false === $count ? strlen( $text ) : $count;
}

// ---------------------------------------------------------------------------
// Rule descriptors. A function with a static array, deliberately — see the
// spec's note on ambient variable scope.
// ---------------------------------------------------------------------------

function foogallery_media_audit_rule_meta( $rule ) {
	if ( 'FG-SRC-07' === $rule ) {
		return array(
			'group' => 'source',
			'unit'  => 'images',
			'label' => __( 'Image may benefit from compression', 'foogallery' ),
			'fix'   => __( 'This image’s file size is high for its dimensions. Try compressing a copy and compare the quality before replacing it. Detailed images, transparency, and lossless originals can legitimately need larger files.', 'foogallery' ) . ' ' . __( 'This suggestion is based on source file size and dimensions. It does not measure visual quality or the image delivered to visitors.', 'foogallery' ),
		);
	}
	static $map = array(
		// FG-DEL-01 RETIRED in 0.6.0 — its premise was wrong. FooGallery generates its own
		// cached thumbnails at the configured size, so WordPress's sizes[] says nothing about
		// what is served. It produced 46 of 57 findings on the first real run, all false.
		'FG-DEL-02'  => array(
			'group' => 'delivery',
			'unit'  => 'pixels',
			'label' => 'Thumbnail upscaled from a smaller image',
			'fix'   => 'Upload a larger version of this image, or reduce the gallery\'s thumbnail size so it isn\'t being stretched.',
		),
		'FG-SRC-01'  => array(
			'group' => 'source',
			'unit'  => 'bytes',
			'label' => 'Large source image (storage observation)',
			'fix'   => 'If these are for viewing rather than downloading, resizing them before upload — or with a bulk optimisation plugin — reduces source storage; this does not measure visitor downloads.',
		),
		'FG-SRC-04'  => array(
			'group' => 'source',
			'unit'  => 'images',
			'label' => 'Image source uses JPEG or PNG',
			'fix'   => 'Convert or replace the Media Library source with a WebP or AVIF version.',
		),
		'FG-SRC-05'  => array(
			'group' => 'source',
			'unit'  => 'none',
			'label' => 'Image file missing from the server',
			'fix'   => 'Re-upload the image, or remove it from the gallery. Missing files usually come from a migration or a partial restore.',
		),
		'FG-INT-01'  => array(
			'group' => 'integrity',
			'unit'  => 'images',
			// The label appears in the rules table and in "checks passed", so it has to
			// carry the same claim as the detail text. "No longer exist" asserts a deletion
			// the audit cannot verify — see the 0.9.2 note.
			'label' => 'Gallery points at images that cannot be found',
			// One rule, two causes, so the fix text is chosen per finding in
			// emit_aggregates() rather than taken from here. This is the fallback.
			'fix'   => 'These images cannot be found in the media library, so they render as blank slots. Check where the gallery gets its images from and remove or replace the missing ones.',
		),
		'FG-INT-02'  => array(
			'group' => 'integrity',
			'unit'  => 'images',
			'label' => 'Image is in the library but will not load',
			'fix'   => 'Open the image URL in a browser to see the actual error. A 403 is usually file permissions or hotlink protection; a 404 means the file is not where WordPress thinks it is. Either way visitors see a broken image even though the Media Library looks completely normal.',
		),
		'FG-A11Y-01' => array(
			'group' => 'a11y',
			'unit'  => 'images',
			'label' => 'Images with no alt text',
			'fix'   => 'Add alt text in the Media Library — a short sentence describing what the image shows. It only needs writing once per image, however many galleries use it.',
		),
		'FG-A11Y-02' => array(
			'group' => 'a11y',
			'unit'  => 'none',
			'label' => 'Alt text is a camera or screenshot filename',
			'fix'   => 'Replace it with a description of what is in the image. A camera filename tells a screen reader user nothing.',
		),
		'FG-A11Y-03' => array(
			'group' => 'a11y',
			'unit'  => 'none',
			'label' => 'Placeholder alt text',
			'fix'   => 'Replace the placeholder with a real description of the image.',
		),
		'FG-A11Y-04' => array(
			'group' => 'a11y',
			'unit'  => 'images_in_cluster',
			'label' => 'Same alt text repeated across images',
			'fix'   => 'Give each image its own alt text. Identical descriptions are read out the same way, so a screen reader user cannot tell the images apart.',
		),
		'FG-A11Y-05' => array(
			'group' => 'a11y',
			'unit'  => 'characters',
			'label' => 'Very long alt text',
			'fix'   => 'Keep the alt text to 125 characters or fewer. Move any extra detail into the caption, which is where longer text belongs.',
		),
		'FG-CFG-01'  => array(
			'group' => 'config',
			'unit'  => 'bytes_above_fold',
			'label' => 'Review lazy loading configuration',
			'fix'   => 'Turn lazy loading on for this gallery (Gallery settings → Advanced), so images load as the visitor scrolls instead of all at once.',
		),
		'FG-CFG-02'  => array(
			'group' => 'config',
			'unit'  => 'items',
			'label' => 'Very long gallery with no paging',
			'fix'   => 'Turn on paging or \'load more\' in the gallery settings so visitors get the first screen quickly.',
		),
	);
	return isset( $map[ $rule ] ) ? $map[ $rule ] : array();
}

/**
 * Build the tracked documentation URL for a registered audit rule.
 *
 * @param string $rule Stable audit rule code.
 * @return string Direct findings-reference URL, or an empty string for an unknown rule.
 */
function foogallery_media_audit_rule_reference_url( $rule ) {
	if ( ! in_array( $rule, FooGallery_Media_Audit_Report::rules(), true ) ) {
		return '';
	}

	$anchor  = strtolower( $rule );
	$content = str_replace( '-', '_', $anchor );
	$url     = add_query_arg(
		array(
			'utm_source'   => 'foogallery',
			'utm_medium'   => 'wordpress_plugin',
			'utm_campaign' => 'media_audit',
			'utm_id'       => 'fg_media_audit',
			'utm_content'  => $content,
		),
		FOOGALLERY_MEDIA_AUDIT_FINDINGS_REFERENCE_URL
	);

	return $url . '#' . $anchor;
}

/**
 * Return documentation actions for every registered audit rule.
 *
 * @return array Rule-code-to-URL map.
 */
function foogallery_media_audit_help_actions() {
	$actions = array();
	foreach ( FooGallery_Media_Audit_Report::rules() as $rule ) {
		$actions[ $rule ] = foogallery_media_audit_rule_reference_url( $rule );
	}

	/**
	 * Filters Media Audit documentation actions.
	 *
	 * @param array $actions Rule-code-to-URL map.
	 */
	return apply_filters( 'foogallery_media_audit_help_actions', $actions );
}

function foogallery_media_audit_cleanup() {
	if ( ! FooGallery_Media_Audit_Report::lock( 30 ) ) {
		return false; }
	try {
		FooGallery_Jobs::cancel( 'foogallery_media_audit_step', 'foogallery-media-audit' );
		delete_option( 'foogallery_media_audit_job' );
		delete_option( FooGallery_Media_Audit_Report::OPTION );
		return true;
	} finally {
		FooGallery_Media_Audit_Report::unlock(); }
}

// ---------------------------------------------------------------------------
// FooGallery accessors.
// ---------------------------------------------------------------------------

function foogallery_media_audit_keys() {
	return array(
		'attachments' => FOOGALLERY_META_ATTACHMENTS,
		'template'    => FOOGALLERY_META_TEMPLATE,
		'settings'    => FOOGALLERY_META_SETTINGS,
		'datasource'  => FOOGALLERY_META_DATASOURCE,
		'ds_value'    => FOOGALLERY_META_DATASOURCE_VALUE,
		'retina'      => FOOGALLERY_META_RETINA,
	);
}

function foogallery_media_audit_fg_setting( $key, $default = '' ) {
	return foogallery_get_setting( $key, $default );
}

/**
 * Effective retina multiplier for a gallery.
 *
 * Verified: per-gallery meta foogallery_retina overrides the global
 * default_retina_support; both are arrays keyed 2x/3x/4x whose values are
 * 'true' when checked. The highest enabled density is what the gallery needs.
 */
function foogallery_media_audit_gallery_dpr( $gallery_id ) {
	$keys   = foogallery_media_audit_keys();
	$retina = get_post_meta( $gallery_id, $keys['retina'], true );
	if ( empty( $retina ) || ! is_array( $retina ) ) {
		$retina = foogallery_media_audit_fg_setting( 'default_retina_support', array() );
	}
	$dpr = 1;
	if ( is_array( $retina ) ) {
		foreach ( $retina as $k => $v ) {
			if ( 'true' === (string) $v || true === $v || '1' === (string) $v ) {
				if ( preg_match( '/([234])/', (string) $k, $m ) ) {
					$dpr = max( $dpr, (int) $m[1] );
				}
			}
		}
	}
	return $dpr;
}

/**
 * Does this template have a fixed thumbnail-size concept, and what is it?
 *
 * Each template registers 'thumbnail_dimensions' => bool. When false the
 * template has no fixed thumbnail size and FG-DEL-01 must not run — this
 * replaces the "template allow-list" the spec called for, using FooGallery's
 * own declaration instead of a hand-maintained list.
 *
 * Returns array( width, height, applicable ).
 */
function foogallery_media_audit_thumb_dimensions( $gallery_id, $template, $settings ) {
	$out = array(
		'width'      => 0,
		'height'     => 0,
		'applicable' => false,
	);
	if ( empty( $template ) ) {
		return $out;
	}

	if ( function_exists( 'foogallery_get_gallery_template' ) ) {
		$info = foogallery_get_gallery_template( $template );
		if ( is_array( $info ) && array_key_exists( 'thumbnail_dimensions', $info )
			&& true !== $info['thumbnail_dimensions'] ) {
			return $out;  // template declares it has no thumbnail-size concept
		}
	}

	$dim = isset( $settings[ $template . '_thumbnail_dimensions' ] )
		? $settings[ $template . '_thumbnail_dimensions' ] : null;
	if ( is_array( $dim ) ) {
		$out['width']      = isset( $dim['width'] ) ? (int) $dim['width'] : 0;
		$out['height']     = isset( $dim['height'] ) ? (int) $dim['height'] : 0;
		$out['applicable'] = $out['width'] > 0;
		return $out;
	}

	// Masonry-style: width only, variable height (verified in
	// class-masonry-gallery-template.php::get_thumbnail_dimensions).
	foreach ( array( $template . '_thumbnail_width', $template . '_thumb_width' ) as $k ) {
		if ( ! empty( $settings[ $k ] ) ) {
			$out['width']      = (int) $settings[ $k ];
			$out['height']     = 0;
			$out['applicable'] = $out['width'] > 0;
			return $out;
		}
	}
	return $out;
}

/**
 * Is lazy loading OFF for this gallery?
 *
 * VERIFIED, AND THE OPPOSITE OF WHAT THE STANDALONE PROBE ASSUMED.
 * class-foogallery-lazyload.php:
 *   $enabled = foogallery_gallery_template_setting( 'lazyload', '' ) === '';
 * so an EMPTY value means lazy loading is ENABLED. Only the literal string
 * 'disabled' turns it off. The earlier probe treated empty as "off" and
 * therefore reported enabled galleries as disabled.
 *
 * Three things have to line up:
 *   - the template must declare lazyload_support => true, otherwise lazy
 *     loading never applies and its absence is not a defect;
 *   - the per-gallery setting {template}_lazyload must not be 'disabled';
 *   - the global setting disable_lazy_loading must not be 'on'.
 */
function foogallery_media_audit_lazy_state( $template, $settings ) {
	$supported = true;
	if ( function_exists( 'foogallery_get_gallery_template' ) && $template ) {
		$info = foogallery_get_gallery_template( $template );
		if ( is_array( $info ) ) {
			$supported = isset( $info['lazyload_support'] ) && true === $info['lazyload_support'];
		}
	}
	if ( ! $supported ) {
		return array(
			'off'    => false,
			'reason' => 'template does not support lazy loading',
		);
	}
	if ( 'on' === foogallery_media_audit_fg_setting( 'disable_lazy_loading', '' ) ) {
		return array(
			'off'    => true,
			'reason' => 'disabled site-wide in FooGallery settings',
		);
	}
	$per = isset( $settings[ $template . '_lazyload' ] ) ? (string) $settings[ $template . '_lazyload' ] : '';
	if ( 'disabled' === $per ) {
		return array(
			'off'    => true,
			'reason' => 'disabled on this gallery',
		);
	}
	return array(
		'off'    => false,
		'reason' => 'enabled',
	);
}

/** Resolve a taxonomy-driven datasource. The stored value names its own taxonomy. */
function foogallery_media_audit_resolve_datasource( $ds_value ) {
	$decoded = is_string( $ds_value ) ? json_decode( $ds_value, true ) : $ds_value;
	if ( ! is_array( $decoded ) || empty( $decoded['taxonomy'] ) ) {
		return array(
			'ids' => array(),
			'raw' => $ds_value,
		);
	}
	$tax   = $decoded['taxonomy'];
	$terms = isset( $decoded['value'] ) ? (array) $decoded['value'] : array();
	if ( empty( $terms ) || ! taxonomy_exists( $tax ) ) {
		return array(
			'ids' => array(),
			'raw' => $ds_value,
		);
	}
	$op      = ( isset( $decoded['selection_mode'] ) && 'AND' === strtoupper( $decoded['selection_mode'] ) ) ? 'AND' : 'IN';
	$numeric = array_filter( $terms, 'is_numeric' );
	$ids     = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- The configured taxonomy is the datasource contract.
			'tax_query'      => array(
				array(
							'taxonomy' => $tax,
							'field'    => empty( $numeric ) ? 'slug' : 'term_id',
							'terms'    => empty( $numeric ) ? array_values( $terms ) : array_map( 'intval', $numeric ),
							'operator' => $op,
				),
			),
		)
	);
	return array(
		'ids' => array_map( 'intval', (array) $ids ),
		'raw' => $ds_value,
	);
}

/**
 * Ask FooGallery itself what is in a gallery.
 *
 * This replaces guesswork for every dynamic datasource. FooGallery::attachments() runs the
 * registered `foogallery_datasource_{name}_attachments` filter — the same call the front end
 * makes to render the gallery — so whatever it returns IS what a visitor sees. That covers
 * post_query, woocommerce, media_tags, media_categories, realmedialibrary, lightroom and
 * infinite_uploads without this plugin knowing anything about how any of them work.
 *
 * Verified in the FooGallery source on 2026-08-14:
 *   - includes/class-foogallery.php:372 attachments() → apply_datasource_filter('attachments')
 *   - includes/class-foogallery.php:585 item_attachment_ids() → the 'attachment_ids' filter,
 *     which post_query, woocommerce and the taxonomy base implement but media_library and
 *     folders do NOT. attachments() is the call every datasource answers, so use that.
 *   - pro/includes/class-foogallery-pro-datasource-folders.php:343 sets ID = 0, because a
 *     server-folder image is a file, not an attachment. Filtering on ID > 0 excludes those
 *     correctly rather than treating array offsets as post IDs.
 *   - No datasource makes an outbound HTTP call in its attachments handler (checked all
 *     nine), so resolving does not phone anybody's API.
 *
 * ONE SIDE EFFECT, and it must be disclosed: post_query and woocommerce cache their result
 * in a 24-hour transient, so resolving a gallery that is not already cached WRITES that
 * transient. It is FooGallery's own cache with its own expiry, and it is exactly what a
 * single front-end page view would create — but it is still a write, so the "writes
 * nothing" claim is now qualified rather than absolute. Set the
 * `foogallery_media_audit_config` key `resolve_dynamic_datasources` to false to skip this entirely.
 *
 * @param int    $gid    Gallery post ID.
 * @param string $source Stored datasource name, when already known.
 * @return array|null null when FooGallery cannot be asked.
 */
function foogallery_media_audit_resolve_via_foogallery( $gid, $source = '' ) {
	if ( ! class_exists( 'FooGallery' ) || ! method_exists( 'FooGallery', 'get_by_id' ) ) {
		return null;
	}
	$gallery = FooGallery::get_by_id( (int) $gid );
	if ( ! $gallery ) {
		return null;
	}

	$source = $source ? $source : $gallery->datasource_name;
	if ( ! is_scalar( $source ) ) {
		return null;
	}
	$source = sanitize_key( (string) $source );
	if ( '' === $source ) {
		return null;
	}
	$hook   = 'foogallery_datasource_' . $source . '_attachments';
	if ( false === has_filter( $hook ) ) {
		// Recover legacy hyphenated names using the same alias rule as the gallery editor.
		$recovered = str_replace( '-', '_', $source );
		if ( $recovered === $source || false === has_filter( 'foogallery_datasource_' . $recovered . '_attachments' ) ) {
			return null;
		}
		$gallery->datasource_name = $recovered;
		$source                   = $recovered;
	}

	$items = $gallery->attachments();
	if ( ! is_array( $items ) ) {
		return null;
	}

	$ids      = array();
	$entries  = array();
	$rendered = array();
	foreach ( $items as $item ) {
		if ( ! is_object( $item ) ) {
			return null;
		}
		$id        = isset( $item->ID ) ? (int) $item->ID : 0;
		$metadata  = array(
			'alt'          => isset( $item->alt ) ? trim( (string) $item->alt ) : '',
			'title'        => isset( $item->title ) ? trim( (string) $item->title ) : '',
			'has_metadata' => ! isset( $item->has_metadata ) || (bool) $item->has_metadata,
		);
		$entries[] = array(
			'id'       => $id,
			'external' => $id < 1,
			'alt'      => $metadata['alt'],
		);
		if ( $id < 1 ) {
			continue;
		}
		$ids[] = $id;
		// Capture what FooGallery will actually PUT IN THE MARKUP, which is not always
		// what the attachment's own meta says. woocommerce and post_query both overwrite
		// alt with the product/post title and set has_metadata = false:
		// pro/includes/woocommerce/class-foogallery-pro-datasource-products.php:343
		// pro/includes/class-foogallery-pro-datasource-post-query.php:231
		// Auditing `_wp_attachment_image_alt` for those galleries measures a field the
		// visitor never receives, and would report "no alt text" on an image that renders
		// with perfectly good alt text.
		$rendered[ $id ] = $metadata;
	}

	return array(
		'ids'        => array_values( array_unique( $ids ) ),
		'entries'    => $entries,
		'rendered'   => $rendered,
		'items'      => count( $items ),
		'datasource' => $source,
		'is_dynamic' => method_exists( $gallery, 'is_dynamic' ) ? (bool) $gallery->is_dynamic() : true,
	);
}

/**
 * Page through the items returned by a gallery's registered datasource.
 *
 * The datasource is resolved once per request and then sliced into the scanner's bounded
 * page contract. Items without a WordPress attachment ID (for example server-folder files)
 * are counted as external items so gallery-level configuration checks still use the real
 * rendered item count without treating those files as broken Media Library references.
 *
 * @param array|null $page     Page supplied by an earlier provider.
 * @param int        $gallery  Gallery post ID.
 * @param string     $source   Datasource name.
 * @param int        $offset   Zero-based item offset.
 * @param int        $limit    Maximum items to return.
 * @param float      $deadline Current worker deadline.
 * @return array|null Bounded datasource page, or the earlier value when unsupported.
 */
function foogallery_media_audit_builtin_source_page( $page, $gallery, $source, $offset, $limit, $deadline ) {
	if ( null !== $page || $limit < 1 || $offset < 0 ) {
		return $page;
	}

	$config = foogallery_media_audit_config();
	if ( empty( $config['resolve_dynamic_datasources'] ) ) {
		return $page;
	}

	static $resolved = array();
	$key             = get_current_blog_id() . ':' . (int) $gallery . ':' . sanitize_key( $source );
	if ( ! array_key_exists( $key, $resolved ) ) {
		$resolved[ $key ] = foogallery_media_audit_resolve_via_foogallery( $gallery, $source );
	}

	$result = $resolved[ $key ];
	if ( ! is_array( $result ) || ! isset( $result['entries'] ) || ! is_array( $result['entries'] ) ) {
		return $page;
	}

	$entries  = array_slice( $result['entries'], $offset, $limit );
	$ids      = array();
	$alt      = array();
	$external = 0;
	foreach ( $entries as $entry ) {
		if ( ! empty( $entry['external'] ) ) {
			++$external;
			continue;
		}
		$id         = (int) $entry['id'];
		$ids[]      = $id;
		$alt[ $id ] = isset( $entry['alt'] ) ? (string) $entry['alt'] : '';
	}

	$next = $offset + count( $entries );
	return array(
		'ids'      => $ids,
		'alt'      => $alt,
		'external' => $external,
		'offset'   => $next,
		'complete' => $next >= count( $result['entries'] ),
	);
}
add_filter( 'foogallery_media_audit_source_page', 'foogallery_media_audit_builtin_source_page', 10, 6 );
