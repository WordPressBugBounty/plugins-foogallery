<?php
/**
 * Get gallery layout schema ability.
 */

if ( ! class_exists( 'FooGallery_Ability_Get_Gallery_Layout_Schema' ) ) {

	class FooGallery_Ability_Get_Gallery_Layout_Schema {

		const ID = 'foogallery/get-gallery-layout-schema';

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
					'label'               => __( 'Get Gallery Layout Schema', 'foogallery' ),
					'description'         => __( 'Return the schema for one gallery layout. Use the required `layout` slug to fetch the normalized field definitions for that layout.', 'foogallery' ),
					'input_schema'        => array(
						'type'                 => 'object',
						'required'             => array( 'layout' ),
						'properties'           => array(
							'layout' => array(
								'type'        => 'string',
								'description' => __( 'The gallery layout slug to inspect, such as `default` or `masonry`.', 'foogallery' ),
							),
						),
					),
					'output_schema'       => array(
						'type'       => 'object',
						'properties' => array(
							'layout' => array_merge(
								foogallery_abilities_get_template_schema( true ),
								array(
									'description' => __( 'The requested gallery layout definition, including the normalized setting field schema.', 'foogallery' ),
								)
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

			$layout = foogallery_abilities_get_requested_layout( $args );

			if ( '' === $layout ) {
				return new WP_Error(
					'foogallery_ability_missing_layout',
					__( 'A valid layout slug is required.', 'foogallery' )
				);
			}

			$template = foogallery_abilities_get_template( $layout );

			if ( is_wp_error( $template ) ) {
				return $template;
			}

			return array(
				'layout' => foogallery_abilities_prepare_template( $template, true ),
			);
		}
	}
}
