<?php
/**
 * FooGallery abilities bootstrap.
 */

if ( ! class_exists( 'FooGallery_Abilities' ) ) {

	class FooGallery_Abilities {

		/**
		 * Main FooGallery ability category slug.
		 *
		 * @var string
		 */
		const CATEGORY = 'foogallery-management';

		/**
		 * Wire up the WordPress core abilities hooks.
		 */
		public function __construct() {
			if ( ! foogallery_abilities_wp_api_available() ) {
				return;
			}

			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_main_category' ) );
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

			new FooGallery_Ability_List_Galleries();
			new FooGallery_Ability_Get_Gallery();
			new FooGallery_Ability_Get_Gallery_Layout_Schema();
			new FooGallery_Ability_List_Gallery_Layouts();
			new FooGallery_Ability_Media_Search();
			new FooGallery_Ability_Create_Gallery();
			new FooGallery_Ability_Update_Gallery();
			new FooGallery_Ability_Update_Gallery_Attachments();
		}

		/**
		 * Register the main FooGallery ability category.
		 *
		 * @return void
		 */
		public function register_main_category() {
			wp_register_ability_category(
				self::CATEGORY,
				array(
					'label'       => __( 'FooGallery Management', 'foogallery' ),
					'description' => __( 'Create, inspect, and update FooGallery galleries using the existing gallery, layout, and attachment APIs.', 'foogallery' ),
				)
			);
		}

		/**
		 * Allow FooGallery code to register abilities.
		 *
		 * @return void
		 */
		public function register_abilities() {
			do_action( 'foogallery_register_abilities' );
		}
	}
}
