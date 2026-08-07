<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FooGallery default extensions common functions
 */

/**
 * Enqueue the core FooGallery stylesheet used by all default templates
 */
function foogallery_enqueue_core_gallery_template_style() {
	$filename = foogallery_is_debug() ? '' : '.min';
	$css = apply_filters( 'foogallery_core_gallery_style', FOOGALLERY_DEFAULT_TEMPLATES_EXTENSION_SHARED_URL . 'css/foogallery' . $filename . '.css' );
	foogallery_enqueue_style( 'foogallery-core', $css, array(), FOOGALLERY_VERSION );

	$feature_deps = apply_filters( 'foogallery_feature_style_deps', array( 'foogallery-core' ) );

	if ( foogallery_get_setting( 'custom_css', '' ) !== '' ) {
		$custom_assets = get_option( FOOGALLERY_OPTION_CUSTOM_ASSETS );
		if ( is_array( $custom_assets ) && array_key_exists( 'style', $custom_assets ) ) {
			foogallery_enqueue_style( 'foogallery-custom', $custom_assets['style'], $feature_deps, FOOGALLERY_VERSION );
		}
	}
}

/**
 * Enqueue the core FooGallery script used by all default templates
 *
 * @param string[] $deps
 */
function foogallery_enqueue_core_gallery_template_script( $deps = null ) {
	FooGallery_Delayed_Runtime_Loader::instance()->enqueue_core_gallery_template_script( $deps );
}

/**
 * @return void
 *
 */
function foogallery_enqueue_polyfills() {
    $suffix = foogallery_is_debug() ? '' : '.min';
    $src    = apply_filters( 'foogallery_polyfills_src', FOOGALLERY_DEFAULT_TEMPLATES_EXTENSION_SHARED_URL . 'js/foogallery.polyfills' . $suffix . '.js', $suffix );

	//resolve the asset URL to a fingerprinted version if available.
	$src = foogallery_resolve_asset_url( $src );

    wp_enqueue_script( 'foogallery-polyfills', $src, array(), FOOGALLERY_VERSION );
}
