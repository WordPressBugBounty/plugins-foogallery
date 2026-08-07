<?php

/**
 * Template loader for FooGallery Albums
 *
 * @package FooGallery
 * @author  Brad vincent
 */
class FooGallery_Album_Template_Loader {

	/**
	 * Active render depth shared by loader instances.
	 *
	 * The shortcode creates a new loader for each render, so this must be static
	 * to distinguish a re-entrant render from a later top-level render.
	 *
	 * @var int
	 */
	private static $render_depth = 0;

	/**
	 * Locates and renders the album based on the template
	 * Will look in the following locations
	 *  wp-content/themes/{child-theme}/foogallery/album-{template}.php
	 *    wp-content/themes/{theme}/foogallery/album-{template}.php
	 *  wp-content/plugins/foogallery/templates/album-{template}.php
	 *
	 * @param $args array       Arguments passed in from the shortcode
	 */
	public function render_template( $args ) {
		$global_names     = array(
			'current_foogallery_album',
			'current_foogallery_album_arguments',
			'current_foogallery_album_template',
		);
		$previous_globals = array();

		foreach ( $global_names as $global_name ) {
			$previous_globals[ $global_name ] = array(
				'exists' => array_key_exists( $global_name, $GLOBALS ),
				'value'  => array_key_exists( $global_name, $GLOBALS ) ? $GLOBALS[ $global_name ] : null,
			);
		}

		$is_nested = self::$render_depth > 0;
		++self::$render_depth;

		try {
			/**
			 * Filters album template arguments before the album is resolved.
			 *
			 * Extensions can use this filter to resolve routing state and replace the
			 * album ID used for the current render.
			 *
			 * @param array $args Arguments passed to the template loader.
			 */
			$render_args = apply_filters( 'foogallery_album_template_args', $args );

			$GLOBALS['current_foogallery_album_arguments'] = $render_args;

			$album = $this->find_album( $render_args );

			$GLOBALS['current_foogallery_album'] = $album;

			if ( false === $album ) {
				esc_html_e( 'Could not load the album!', 'foogallery' );
				$this->finish_render( $previous_globals, $is_nested );
				return;
			}

			$template_slug = $this->get_arg( $render_args, 'template', $album->album_template );

			if ( empty( $template_slug ) ) {
				$template_slug = foogallery_get_default( 'album_template' );
			}

			if ( empty( $template_slug ) ) {
				$available_templates = foogallery_album_templates();
				$template_slug       = $available_templates[0]['slug'];
			}

			$this->set_render_globals( $album, $render_args, $template_slug );

			/**
			 * Filters whether the current album has items that can be rendered.
			 *
			 * Extensions can use this filter to make albums containing non-gallery
			 * items, such as child albums, pass the empty album check.
			 *
			 * @param bool            $has_renderable_items Whether the album has renderable items.
			 * @param FooGalleryAlbum $album               The album being rendered.
			 * @param array           $args                Arguments passed in from the shortcode.
			 * @param string          $template_slug       The resolved album template slug.
			 */
			$has_renderable_items = apply_filters(
				'foogallery_album_has_renderable_items',
				$album->has_galleries(),
				$album,
				$render_args,
				$template_slug
			);

			if ( ! $has_renderable_items ) {
				// Preserve the malformed legacy dynamic action for compatibility.
				$this->set_render_globals( $album, $render_args, $template_slug );
				do_action( "foogallery_album_template_no_galleries-($template_slug)", $album );

				/**
				 * Fires when an album has no renderable items.
				 *
				 * @param FooGalleryAlbum $album         The album being rendered.
				 * @param array           $args          Arguments passed in from the shortcode.
				 * @param string          $template_slug The resolved album template slug.
				 */
				$this->set_render_globals( $album, $render_args, $template_slug );
				do_action( 'foogallery_album_template_no_galleries', $album, $render_args, $template_slug );
				$this->set_render_globals( $album, $render_args, $template_slug );
				do_action( "foogallery_album_template_no_galleries-{$template_slug}", $album, $render_args );
				$this->set_render_globals( $album, $render_args, $template_slug );

				$this->finish_render( $previous_globals, $is_nested );
				return;
			}

			$instance_name = FOOGALLERY_SLUG . '_album_templates';
			$loader        = new Foo_Plugin_File_Locator_v1( $instance_name, FOOGALLERY_FILE, 'templates', FOOGALLERY_SLUG );

			$this->add_extension_pickup_locations( $loader, apply_filters( $instance_name . '_files', array() ) );

			$template_location = $loader->locate_file( "album-{$template_slug}.php" );
			if ( false === $template_location ) {
				esc_html_e( 'No album layout found!', 'foogallery' );
				$this->finish_render( $previous_globals, $is_nested );
				return;
			}

			$this->set_render_globals( $album, $render_args, $template_slug );
			do_action( 'foogallery_located_album_template', $album );
			$this->set_render_globals( $album, $render_args, $template_slug );
			do_action( "foogallery_located_album_template-{$template_slug}", $album );
			$this->set_render_globals( $album, $render_args, $template_slug );

			$js_location = $loader->locate_file( "album-{$template_slug}.js" );
			if ( false !== $js_location ) {
				wp_enqueue_script( "foogallery-album-template-{$template_slug}", $js_location['url'] );
			}

			$css_location = $loader->locate_file( "album-{$template_slug}.css" );
			if ( false !== $css_location ) {
				foogallery_enqueue_style( "foogallery-album-template-{$template_slug}", $css_location['url'] );
			}

			/**
			 * Filters whether an external renderer handled the album template.
			 *
			 * @param bool            $handled           Whether the template was handled externally.
			 * @param FooGalleryAlbum $album             The album being rendered.
			 * @param array           $args              Arguments passed in from the shortcode.
			 * @param array           $template_location The located template path and URL.
			 * @param string          $template_slug     The resolved album template slug.
			 */
			$handled = apply_filters(
				'foogallery_load_album_template',
				false,
				$album,
				$render_args,
				$template_location,
				$template_slug
			);

			// A nested render or callback may have touched the globals. Reassert the
			// immutable outer snapshots before a legacy template reads them.
			$this->set_render_globals( $album, $render_args, $template_slug );

			if ( ! $handled ) {
				load_template( $template_location['path'], false );
			}

			$this->set_render_globals( $album, $render_args, $template_slug );

			// Existing lifecycle actions retain their order and argument counts.
			do_action( 'foogallery_loaded_album_template', $album );
			$this->set_render_globals( $album, $render_args, $template_slug );
			do_action( "foogallery_loaded_album_template-($template_slug)", $album );
			$this->set_render_globals( $album, $render_args, $template_slug );

			/**
			 * Fires after an album template has been handled or loaded.
			 *
			 * @param FooGalleryAlbum $album The album that was rendered.
			 * @param array           $args  Arguments passed in from the shortcode.
			 */
			do_action( "foogallery_loaded_album_template-{$template_slug}", $album, $render_args );

			$this->set_render_globals( $album, $render_args, $template_slug );

			$this->finish_render( $previous_globals, $is_nested );
		} catch ( Throwable $exception ) {
			$this->finish_render( $previous_globals, $is_nested );
			throw $exception;
		}
	}

	/**
	 * Set the legacy globals for one immutable render snapshot.
	 *
	 * @param FooGalleryAlbum $album         Album being rendered.
	 * @param array           $render_args   Resolved render arguments.
	 * @param string          $template_slug Resolved template slug.
	 * @return void
	 */
	private function set_render_globals( $album, $render_args, $template_slug ) {
		$GLOBALS['current_foogallery_album']           = $album;
		$GLOBALS['current_foogallery_album_arguments'] = $render_args;
		$GLOBALS['current_foogallery_album_template']  = $template_slug;
	}

	/**
	 * Finish one render and restore the outer render when this was re-entrant.
	 *
	 * @param array $previous_globals Previous existence and value snapshots.
	 * @param bool  $restore_globals  Whether this invocation had an active outer render.
	 * @return void
	 */
	private function finish_render( $previous_globals, $restore_globals ) {
		if ( $restore_globals ) {
			foreach ( $previous_globals as $global_name => $previous_global ) {
				if ( $previous_global['exists'] ) {
					$GLOBALS[ $global_name ] = $previous_global['value'];
				} else {
					unset( $GLOBALS[ $global_name ] );
				}
			}
		}

		self::$render_depth = max( 0, self::$render_depth - 1 );
	}

	/**
	 * Add pickup locations to the loader to make it easier for extensions
	 *
	 * @param $loader Foo_Plugin_File_Locator_v1
	 * @param $extension_files array
	 */
	function add_extension_pickup_locations( $loader, $extension_files ) {
		if ( count( $extension_files ) > 0 ) {
			$position = 120;
			foreach ( $extension_files as $file ) {

				//add pickup location for php template
				$loader->add_location( $position, array(
					'path' => trailingslashit( plugin_dir_path( $file ) ),
					'url'  => trailingslashit( plugin_dir_url( $file ) )
				) );

				$position++;

				//add pickup location for extensions js folder
				$loader->add_location( $position, array(
					'path' => trailingslashit( plugin_dir_path( $file ) . 'js' ),
					'url'  => trailingslashit( plugin_dir_url( $file ) . 'js' )
				) );

				$position++;

				//add pickup location for extension css folder
				$loader->add_location( $position, array(
					'path' => trailingslashit( plugin_dir_path( $file ) . 'css' ),
					'url'  => trailingslashit( plugin_dir_url( $file ) . 'css' )
				) );

				$position++;

			}
		}
	}

	/**
	 * load the gallery based on either the id or slug, passed in via arguments
	 *
	 * @param $args array       Arguments passed in from the shortcode
	 *
	 * @return bool|FooGallery  The gallery object we want to render
	 */
	function find_album( $args ) {

		$id = intval( $this->get_arg( $args, 'id' ), 0 );

		if ( $id > 0 ) {

			//load album by ID
			return FooGalleryAlbum::get_by_id( $id );

		} else {

			//take into account the cases where id is passed in via the 'album' attribute
			$album = $this->get_arg( 'album', 0 );

			if ( intval( $album ) > 0 ) {
				//we have an id, so load
				return FooGalleryAlbum::get_by_id( intval( $album ) );
			}

			//we are dealing with a slug
			return FooGalleryAlbum::get_by_slug( $album );
		}
	}

	/**
	 * Helper to get an argument value from an array arguments
	 *
	 * @param $args    Array    the array of arguments to search
	 * @param $key     string   the key of the argument you are looking for
	 * @param $default string   a default value if the argument is not found
	 *
	 * @return string
	 */
	function get_arg( $args, $key, $default = '' ) {
		if ( empty($args)
		     || !is_array( $args )
		     || !array_key_exists( $key, $args ) ) {
			return $default;
		}

		return $args[ $key ];
	}
}
