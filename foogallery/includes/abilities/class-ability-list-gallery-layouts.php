<?php
/**
 * List gallery layouts ability.
 */

if ( ! class_exists( 'FooGallery_Ability_List_Gallery_Layouts' ) ) {

	class FooGallery_Ability_List_Gallery_Layouts {

		const ID = 'foogallery/list-layouts';

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
					'label'               => __( 'List Gallery Layouts', 'foogallery' ),
					'description'         => __( 'List registered FooGallery gallery layouts. Use `layout` to limit the response to one layout and `include_fields` to include or omit the normalized setting field definitions.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'properties'           => array(
							'layout'         => array(
								'type'        => 'string',
								'description' => __( 'Optional gallery layout slug used to return a single layout record instead of the full registry.', 'foogallery' ),
							),
							'include_fields' => array(
								'type'        => 'boolean',
								'default'     => true,
								'description' => __( 'When `true`, include the normalized setting field definitions for each layout. When `false`, return only the top-level layout metadata.', 'foogallery' ),
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'layouts' => array(
								'type'        => 'array',
								'description' => __( 'The gallery layouts that matched the request.', 'foogallery' ),
								'items'       => foogallery_abilities_get_template_schema( true ),
							),
						),
					),
					'execute_callback'    => array( $this, 'execute' ),
					'permission_callback' => array( $this, 'can_execute' ),
					'meta'                => array(
						'annotations' => array(
							'readonly'   => true,
							'idempotent' => true,
						),
						'show_in_rest' => true,
					),
				)
			);
		}

		/**
		 * Check if the current user can execute the ability.
		 *
		 * @return bool
		 */
		public function can_execute( $args = null ) {
			return current_user_can( 'edit_foogalleries' );
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

			$include_fields = ! isset( $args['include_fields'] ) || (bool) $args['include_fields'];
			$layout         = foogallery_abilities_get_requested_layout( $args );

			if ( '' !== $layout ) {
				$template = foogallery_abilities_get_template( $layout );

				if ( is_wp_error( $template ) ) {
					return $template;
				}

				return array(
					'layouts' => array(
						foogallery_abilities_prepare_template( $template, $include_fields ),
					),
				);
			}

			$layouts = array();
			foreach ( foogallery_gallery_templates() as $template ) {
				$layouts[] = foogallery_abilities_prepare_template( $template, $include_fields );
			}

			return array(
				'layouts' => $layouts,
			);
		}
	}
}
