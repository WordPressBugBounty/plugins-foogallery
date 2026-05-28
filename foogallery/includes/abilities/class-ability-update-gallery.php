<?php
/**
 * Update gallery ability.
 */

if ( ! class_exists( 'FooGallery_Ability_Update_Gallery' ) ) {

	class FooGallery_Ability_Update_Gallery {

		const ID = 'foogallery/update-gallery';

		/**
		 * Register the ability.
		 */
		public function __construct() {
			if ( foogallery_abilities_wp_api_available() ) {
				add_action( 'foogallery_register_abilities', array( $this, 'register' ) );
			}
		}

		/**
		 * Register the ability with the WordPress core Abilities API.
		 */
		public function register() {
			wp_register_ability(
				self::ID,
				array(
					'category'            => FooGallery_Abilities::CATEGORY,
					'label'               => __( 'Update Gallery', 'foogallery' ),
					'description'         => __( 'Update an existing FooGallery gallery by `gallery_id`. Use `layout` to switch layouts, derive valid `settings` IDs from the `foogallery/get-gallery-layout-schema` tool for that layout, and pass `settings` as an array of setting update objects. You can also update `title`, `status`, `sort`, or `custom_css`.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'gallery_id' ),
						'properties'           => array(
							'gallery_id'  => array(
								'type'        => 'integer',
								'description' => __( 'The numeric ID of the FooGallery gallery to update.', 'foogallery' ),
							),
							'title'       => array(
								'type'        => 'string',
								'description' => __( 'Optional replacement gallery title.', 'foogallery' ),
							),
							'status'      => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish', 'private' ),
								'description' => __( 'Optional replacement post status for the gallery.', 'foogallery' ),
							),
							'layout'      => array(
								'type'        => 'string',
								'description' => __( 'Optional gallery layout slug. When changed, FooGallery rebuilds the stored settings base for the new layout before applying any supplied `settings`.', 'foogallery' ),
							),
							'settings'    => array(
								'type'        => 'array',
								'description' => __( 'Optional array of layout setting update objects. Use the IDs and field types returned by the `foogallery/get-gallery-layout-schema` tool for the active or target layout.', 'foogallery' ),
								'items'       => foogallery_abilities_get_setting_update_schema(),
							),
							'sort'        => array(
								'type'        => 'string',
								'description' => __( 'Optional FooGallery sort mode to store for the gallery.', 'foogallery' ),
							),
							'custom_css'  => array(
								'type'        => 'string',
								'description' => __( 'Optional custom CSS to save with the gallery. Pass an empty string to clear it.', 'foogallery' ),
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'gallery' => array_merge(
								foogallery_abilities_get_gallery_detail_schema( false ),
								array(
									'description' => __( 'The updated gallery record after all changes have been applied.', 'foogallery' ),
								)
							),
						),
					),
					'execute_callback'    => array( $this, 'execute' ),
					'permission_callback' => array( $this, 'can_execute' ),
					'meta'                => array(
						'annotations' => array(
							'readonly'    => false,
							'destructive' => true,
							'idempotent'  => true,
						),
						'show_in_rest' => true,
					),
				)
			);
		}

		/**
		 * Check if the current user can execute the ability.
		 *
		 * @param array $args Ability arguments.
		 *
		 * @return bool
		 */
		public function can_execute( $args ) {
			$args = foogallery_abilities_normalize_input( $args );

			$gallery_id = isset( $args['gallery_id'] ) ? absint( $args['gallery_id'] ) : 0;
			if ( $gallery_id <= 0 || ! current_user_can( 'edit_post', $gallery_id ) ) {
				return false;
			}

			if ( isset( $args['status'] ) ) {
				$status = foogallery_abilities_sanitize_gallery_status( $args['status'], '' );
				if ( 'publish' === $status && ! current_user_can( 'publish_foogalleries' ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Execute the ability.
		 *
		 * @param array $args Ability arguments.
		 *
		 * @return array|WP_Error
		 */
		public function execute( $args ) {
			$args = foogallery_abilities_normalize_input( $args );

			$gallery = foogallery_abilities_get_gallery( isset( $args['gallery_id'] ) ? $args['gallery_id'] : 0 );

			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}

			$target_template = foogallery_abilities_get_requested_layout( $args, $gallery->gallery_template );
			$template        = foogallery_abilities_get_template( $target_template );

			if ( is_wp_error( $template ) ) {
				return $template;
			}

			if ( array_key_exists( 'title', $args ) || array_key_exists( 'status', $args ) ) {
				$post_update = array(
					'ID' => $gallery->ID,
				);

				if ( array_key_exists( 'title', $args ) ) {
					$post_update['post_title'] = sanitize_text_field( $args['title'] );
				}

				if ( array_key_exists( 'status', $args ) ) {
					$post_update['post_status'] = foogallery_abilities_sanitize_gallery_status( $args['status'], $gallery->post_status );
				}

				$result = wp_update_post( $post_update, true );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			$request_context = array(
				'ability'    => self::ID,
				'gallery_id' => $gallery->ID,
				'layout'     => $template['slug'],
				'template'   => $template['slug'],
				'settings'   => foogallery_abilities_prepare_template_setting_updates(
					$template['slug'],
					isset( $args['settings'] ) ? $args['settings'] : array()
				),
			);

			if ( is_wp_error( $request_context['settings'] ) ) {
				return $request_context['settings'];
			}

			if ( $gallery->gallery_template === $template['slug'] ) {
				$settings = array_merge(
					foogallery_abilities_build_template_settings_base( $template['slug'] ),
					is_array( $gallery->settings ) ? $gallery->settings : array()
				);
			} else {
				$settings = foogallery_abilities_build_template_settings_base( $template['slug'] );
			}

			$settings = foogallery_abilities_normalize_template_settings(
				$template['slug'],
				$request_context['settings'],
				$settings,
				$gallery->ID,
				$request_context
			);

			update_post_meta( $gallery->ID, FOOGALLERY_META_TEMPLATE, $template['slug'] );
			update_post_meta( $gallery->ID, FOOGALLERY_META_SETTINGS, $settings );

			if ( array_key_exists( 'sort', $args ) ) {
				$sort = foogallery_abilities_sanitize_gallery_sort( $args['sort'] );
				if ( '' === $sort ) {
					delete_post_meta( $gallery->ID, FOOGALLERY_META_SORT );
				} else {
					update_post_meta( $gallery->ID, FOOGALLERY_META_SORT, $sort );
				}
			}

			if ( array_key_exists( 'custom_css', $args ) ) {
				$custom_css = foogallery_sanitize_full( $args['custom_css'] );
				if ( '' === $custom_css ) {
					delete_post_meta( $gallery->ID, FOOGALLERY_META_CUSTOM_CSS );
				} else {
					update_post_meta( $gallery->ID, FOOGALLERY_META_CUSTOM_CSS, $custom_css );
				}
			}

			foogallery_abilities_clear_gallery_cache( $gallery->ID );
			do_action( 'foogallery_after_save_gallery', $gallery->ID, $request_context );

			$gallery = foogallery_abilities_get_gallery( $gallery->ID );

			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}

			return array(
				'gallery' => foogallery_abilities_prepare_gallery_details( $gallery ),
			);
		}
	}
}
