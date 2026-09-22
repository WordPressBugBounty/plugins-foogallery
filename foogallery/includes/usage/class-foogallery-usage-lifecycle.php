<?php
/**
 * Integrate Improve cleanup with WordPress and the existing SDK lifecycle.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps reporting cleanup independent of SDK consent. */
class FooGallery_Usage_Lifecycle {
	/** Register a native cleanup callback after the SDK registers its own. */
	public static function register_uninstall() {
		register_uninstall_hook( FOOGALLERY_FILE, array( __CLASS__, 'uninstall' ) );
	}

	/**
	 * Pause jobs, then preserve the SDK's existing uninstall behavior.
	 *
	 * @param bool $network_wide Whether the plugin is being deactivated network-wide.
	 */
	public static function deactivate( $network_wide = false ) {
		FooGallery_Usage::deactivate( $network_wide );
		self::register_uninstall();
	}

	/**
	 * Called by WordPress's registered uninstall hook, with the real hook context.
	 *
	 * No reporting request is made during cleanup. Freemius retains its existing
	 * uninstall behavior, including its own consent and active-edition checks.
	 */
	public static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( class_exists( 'Freemius' ) && is_callable( array( 'Freemius', '_uninstall_plugin_hook' ) ) ) {
			Freemius::_uninstall_plugin_hook();
		}
		if ( ! is_multisite() ) {
			if ( ! self::has_active_edition() ) {
				FooGallery_Usage::uninstall( true );
			}
			return;
		}
		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'number' => 100,
					'offset' => $offset,
					'fields' => 'ids',
				)
			);
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				try {
					if ( ! self::has_active_edition() ) {
						FooGallery_Usage::uninstall( true );
					}
				} finally {
					restore_current_blog();
				}
			}
			$count   = count( $sites );
			$offset += $count;
		} while ( 100 === $count );
	}

	/**
	 * An active edition owns shared per-site reporting state, even if renamed.
	 *
	 * During native uninstall FOOGALLERY_FILE can belong to the surviving edition,
	 * so it must not be excluded when evaluating this guard.
	 *
	 * @return bool
	 */
	public static function has_active_edition() {
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $plugins as $plugin ) {
			if ( 'foogallery.php' === basename( $plugin ) ) {
				return true;
			}
		}
		return false;
	}

	/** The existing full-data reset is already authorized by its admin handler. */
	public static function full_reset() {
		FooGallery_Usage::uninstall();
	}
}
