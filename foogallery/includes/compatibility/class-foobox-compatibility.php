<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds in better support for FooBox Free and PRO
 */

if ( !class_exists( 'FooGallery_FooBox_Compatibility' ) ) {

	class FooGallery_FooBox_Compatibility {

		function __construct() {
			//we need to make sure outdated versions of FooBox never run in the future
			$this->ensure_outdated_foobox_extensions_never_run();

			//add the FooBox lightbox option no matter if using Free or Pro
			add_filter( 'foogallery_gallery_template_field_lightboxes', array($this, 'add_lightbox'), 11, 2 );

			//alter the default lightbox to be foobox
			add_filter( 'foogallery_alter_gallery_template_field', array( $this, 'make_foobox_default_lightbox' ), 10, 2 );

            //allow changing of field values
            add_filter( 'foogallery_render_gallery_template_field_value', array( $this, 'check_lightbox_value' ), 10, 4 );

            if ( class_exists( 'fooboxV2' ) ) {
				//FooBox PRO specific functionality

				//only add FooBox PRO functionality after FooBox version 1.2.29
				if ( defined( 'FOOBOX_BASE_VERSION' ) && version_compare( FOOBOX_BASE_VERSION, '1.2.29', '>' ) ) {
					add_filter( 'foogallery_attachment_custom_fields', array($this, 'add_panning_fields' ) );
					add_filter( 'foogallery_attachment_html_link_attributes', array( $this, 'add_panning_attributes' ), 10, 3 );
				}

			} else {
				//FooBox Free specific functionality
				add_filter( 'foogallery_album_stack_link_class_name', array($this, 'album_stack_link_class_name'));
			}

			//cater for different captions sources
			add_filter( 'foogallery_attachment_html_link_attributes', array( $this, 'add_caption_attributes' ), 20, 3 );

			//add custom captions
			add_filter( 'foogallery_build_attachment_html_caption_custom', array( &$this, 'customize_captions' ), 90, 3 );

			//add fields for FooBox free captions
			add_filter( 'foogallery_override_gallery_template_fields', array( $this, 'add_caption_fields' ), 20, 2 );
		}

		/**
		 * Customize the captions if needed
		 *
		 * @param $captions
		 * @param $foogallery_attachment    FooGalleryAttachment
		 * @param $args array
		 *
		 * @return array
		 */
		function customize_captions( $captions, $foogallery_attachment, $args) {

			if ( isset( $foogallery_attachment->custom_captions ) && $foogallery_attachment->custom_captions ) {
				//specifically for foobox, make sure the custom captions are set
				$foogallery_attachment->caption_title = ' ';
				$foogallery_attachment->caption_desc  = $captions['desc'];
			}

			return $captions;
		}

		/**
		 * Handle custom captions for the lightbox
		 * @param $attr
		 * @param $args
		 * @param $foogallery_attachment
		 *
		 * @return mixed
		 */
		function add_caption_attributes( $attr, $args, $foogallery_attachment ) {
			global $current_foogallery;

			if ( ! isset( $current_foogallery->lightbox ) || 'foobox' !== $current_foogallery->lightbox ) {
				return $attr;
			}

			$schema         = $this->caption_settings_schema();
			$caption_source = foogallery_gallery_template_setting( $schema['source'], '' );

			if ( 'override' === $caption_source ) {
				$caption_title_source = foogallery_gallery_template_setting( $schema['title'], '' );
				if ( 'none' === $caption_title_source ) {
					$attr['data-caption-title'] = ' ';
				} elseif ( '' === $caption_title_source ) {
					$attr['data-caption-title'] = $this->thumbnail_caption_value( $foogallery_attachment, 'caption_title' );
				} else {
					$attr['data-caption-title'] = foogallery_sanitize_full( foogallery_get_caption_by_source( $foogallery_attachment, $caption_title_source, 'title' ) );
				}

				$caption_desc_source = foogallery_gallery_template_setting( $schema['desc'], '' );
				if ( 'none' === $caption_desc_source ) {
					$attr['data-caption-desc'] = ' ';
				} elseif ( '' === $caption_desc_source ) {
					$attr['data-caption-desc'] = $this->thumbnail_caption_value( $foogallery_attachment, 'caption_desc' );
				} else {
					$attr['data-caption-desc'] = foogallery_sanitize_full( foogallery_get_caption_by_source( $foogallery_attachment, $caption_desc_source, 'description' ) );
				}
			} elseif ( 'custom' === $caption_source && isset( $schema['custom'] ) ) {
				$template = foogallery_gallery_template_setting( $schema['custom'], '' );
				if ( $this->has_custom_caption_builder() ) {
					$attr['data-caption-title'] = ' ';
					$attr['data-caption-desc']  = foogallery_sanitize_full( FooGallery_Pro_Advanced_Captions::build_custom_caption( $template, $foogallery_attachment ) );
				} else {
					// A downgraded gallery cannot render a PRO custom template, so retain only a usable title.
					$attr['data-caption-title'] = ! empty( $foogallery_attachment->title ) ? foogallery_sanitize_full( $foogallery_attachment->title ) : ' ';
					$attr['data-caption-desc']  = ' ';
				}
			} elseif ( 'same' === $caption_source || ( '' === $caption_source && empty( $schema['smart'] ) ) ) {
				// Keep the FooBox caption the same as the resolved thumbnail caption.
				$attr['data-caption-title'] = $this->thumbnail_caption_value( $foogallery_attachment, 'caption_title' );
				$attr['data-caption-desc']  = $this->thumbnail_caption_value( $foogallery_attachment, 'caption_desc' );
			} elseif ( ( ! isset( $attr['data-caption-title'] ) || '' === $attr['data-caption-title'] ) && ! empty( $foogallery_attachment->title ) ) {
				// Smart mode falls back to the attachment title without changing alt text.
				$attr['data-caption-title'] = foogallery_sanitize_full( $foogallery_attachment->title );
			}

			return $attr;
		}

		/**
		 * Whether the Pro custom-caption builder is available.
		 *
		 * @return bool
		 */
		protected function has_custom_caption_builder() {
			return class_exists( 'FooGallery_Pro_Advanced_Captions' );
		}

		/**
		 * Resolve the persisted caption settings schema for the current gallery.
		 *
		 * The persisted FooBox-specific schema remains authoritative when its
		 * source key exists, including after an entitlement change. The generic
		 * lightbox schema is used only when the FooBox-specific key is absent;
		 * galleries without either key retain the legacy Smart default.
		 *
		 * @return array
		 */
		private function caption_settings_schema() {
			$free_schema = array(
				'source' => 'foobox_caption_source',
				'title'  => 'foobox_caption_override_title',
				'desc'   => 'foobox_caption_override_desc',
				'smart'  => true,
			);
			$pro_schema  = array(
				'source' => 'lightbox_caption_override',
				'title'  => 'lightbox_caption_override_title',
				'desc'   => 'lightbox_caption_override_desc',
				'custom' => 'lightbox_caption_custom_template',
			);

			if ( $this->gallery_template_setting_exists( $free_schema['source'] ) ) {
				return $free_schema;
			}

			if ( $this->gallery_template_setting_exists( $pro_schema['source'] ) ) {
				return $pro_schema;
			}

			return $free_schema;
		}

		/**
		 * Return a resolved thumbnail caption value for FooBox.
		 *
		 * @param object $foogallery_attachment Gallery attachment.
		 * @param string $property              Resolved caption property.
		 * @return string
		 */
		private function thumbnail_caption_value( $foogallery_attachment, $property ) {
			return isset( $foogallery_attachment->{$property} ) ? foogallery_sanitize_full( $foogallery_attachment->{$property} ) : ' ';
		}

		/**
		 * Check whether a setting was supplied or persisted, including empty values.
		 *
		 * @param string $key Setting key without the template prefix.
		 * @return bool
		 */
		private function gallery_template_setting_exists( $key ) {
			global $current_foogallery;
			global $current_foogallery_template;

			$settings_key = "{$current_foogallery_template}_{$key}";

			return ! empty( $current_foogallery ) && is_array( $current_foogallery->settings ) && array_key_exists( $settings_key, $current_foogallery->settings );
		}

		/**
		 * Add caption fields for FooBox FREE
		 *
		 * @param $fields
		 * @param $template
		 *
		 * @return mixed
		 */
		function add_caption_fields( $fields, $template ) {
			//see if the template has a lightbox field
			$found_lightbox = false;
			foreach ( $fields as $key => &$field ) {
				if ( 'lightbox' === $field['id'] ) {
					$found_lightbox = true;
					break;
				}
			}

			if ( $found_lightbox && $this->is_foobox_installed() && !foogallery_is_pro() ) {

				$new_fields[] = array(
					'id'      => 'foobox_caption_source',
					'title'   => __( 'Lightbox Caption Source', 'foogallery' ),
					'desc'    => __( 'The lightbox captions can be different to the thumbnail captions.', 'foogallery' ),
					'section_id' => 'lightbox',
					'subsection_id' => 'lightbox-general',
					'type'    => 'radio',
					'default' => '',
					'class'   => 'foogallery-radios-stacked',
					'choices' => array(
						'' => __('Smart (try to show both caption titles and descriptions if available)', 'foogallery' ),
						'same' => __( 'Same As Thumbnail', 'foogallery' ),
						'override'  => __( 'Override', 'foogallery' ),
					),
					'row_data'=> array(
						'data-foogallery-hidden' => true,
						'data-foogallery-show-when-field' => 'lightbox',
						'data-foogallery-show-when-field-value' => 'foobox',
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-value-selector'  => 'input:checked',
					)
				);

				$new_fields[] = array(
					'id'      => 'foobox_caption_override_title',
					'title'   => __( 'Override Caption Title', 'foogallery' ),
					'desc'    => __( 'You can override the caption title to be different from the thumbnail caption title.', 'foogallery' ),
					'section_id' => 'lightbox',
					'subsection_id' => 'lightbox-general',
					'type'    => 'radio',
					'default' => '',
					'class'   => 'foogallery-radios-stacked',
					'choices' => array(
						'' => __( 'Same As Thumbnail', 'foogallery' ),
						'title'  => __( 'Attachment Title', 'foogallery' ),
						'caption'  => __( 'Attachment Caption', 'foogallery' ),
						'alt'  => __( 'Attachment Alt', 'foogallery' ),
						'desc'  => __( 'Attachment Description', 'foogallery' ),
						'none'  => __( 'None', 'foogallery' ),
					),
					'row_data'=> array(
						'data-foogallery-hidden'                   => true,
						'data-foogallery-show-when-field'          => 'foobox_caption_source',
						'data-foogallery-show-when-field-operator' => '===',
						'data-foogallery-show-when-field-value'    => 'override',
						'data-foogallery-change-selector'          => 'input:radio',
						'data-foogallery-value-selector'           => 'input:checked',
					)
				);

				$new_fields[] = array(
					'id'      => 'foobox_caption_override_desc',
					'title'   => __( 'Override Caption Desc.', 'foogallery' ),
					'desc'    => __( 'You can override the caption description to be different from the thumbnail caption description.', 'foogallery' ),
					'section_id' => 'lightbox',
					'subsection_id' => 'lightbox-general',
					'type'    => 'radio',
					'default' => '',
					'class'   => 'foogallery-radios-stacked',
					'choices' => array(
						'' => __( 'Same As Thumbnail', 'foogallery' ),
						'title'  => __( 'Attachment Title', 'foogallery' ),
						'caption'  => __( 'Attachment Caption', 'foogallery' ),
						'alt'  => __( 'Attachment Alt', 'foogallery' ),
						'desc'  => __( 'Attachment Description', 'foogallery' ),
						'none'  => __( 'None', 'foogallery' ),
					),
					'row_data'=> array(
						'data-foogallery-hidden'                   => true,
						'data-foogallery-show-when-field'          => 'foobox_caption_source',
						'data-foogallery-show-when-field-operator' => '===',
						'data-foogallery-show-when-field-value'    => 'override',
						'data-foogallery-change-selector'          => 'input:radio',
						'data-foogallery-value-selector'           => 'input:checked',
					)
				);

				//find the index of the first Hover Effect field
				$index = foogallery_admin_fields_find_index_of_section( $fields, 'hover-effects' );

				array_splice( $fields, $index, 0, $new_fields );
			}

			return $fields;
		}

        /***
         * Check if we have a lightbox value from FooBox free and change it if foobox free is no longer active
         * @param $value
         * @param $field
         * @param $gallery
         * @param $template
         *
         * @return string
         */
        function check_lightbox_value($value, $field, $gallery, $template) {

            if ( isset( $field['lightbox'] ) ) {
                if ( 'foobox-free' === $value ) {
                    if ( !class_exists( 'Foobox_Free' ) ) {
                        return 'foobox';
                    }
                }
            }

            return $value;
        }

        /**
         * Change the default for lightbox if foobox is activated
         *
         * @param $field
         * @param $gallery_template
         * @return mixed
         */
		function make_foobox_default_lightbox( $field, $gallery_template ) {
		    if ( $this->is_foobox_installed() ) {
                if (isset($field['lightbox']) && true === $field['lightbox']) {
                    $field['default'] = 'foobox';
                }
            }

		    return $field;
        }

		function is_foobox_installed() {
		    return $this->is_foobox_free_installed() || $this->is_foobox_pro_installed();
        }

		function is_foobox_free_installed() {
			return class_exists( 'FooBox' );
		}

		function is_foobox_pro_installed() {
			return class_exists( 'fooboxV2' );
		}

		function ensure_outdated_foobox_extensions_never_run() {
			global $foogallery_extensions;

			//backwards compatibility for older versions of the FooBox Free extension class
			if ( class_exists( 'FooGallery_FooBox_Free_Extension' ) ) {
				$foogallery_extensions['foobox-image-lightbox'] = $this;
			}

			//backwards compatibility for older versions of the FooBox PRO extension class
			if ( class_exists( 'FooGallery_FooBox_Extension' ) ) {
				$foogallery_extensions['foobox'] = $this;
			}
		}

		function add_lightbox($lightboxes) {
			$option_text = __( 'FooBox', 'foogallery' );
			if ( !$this->is_foobox_installed() ) {
				$option_text .= __( ' (Not installed!)', 'foogallery' );
			}

			$lightboxes['foobox'] = $option_text;
			return $lightboxes;
		}

		function album_stack_link_class_name( $class_name ) {
			return str_replace( 'foobox-free', 'foobox', $class_name );
		}

		function add_panning_fields( $fields ) {
			$fields['foobox_panning'] = array(
				'label'       =>  __( 'Panning', 'foogallery' ),
				'input'       => 'radio',
				'helps'       => __( 'Enable mouse panning for this image in the lightbox.', 'foogallery' ),
				'exclusions'  => array( 'audio', 'video' ),
				'options'     => array(
					'' => __( 'Disabled', 'foogallery' ),
					'enabled' => __( 'Enabled', 'foogallery' )
				)
			);

			return $fields;
		}

		function add_panning_attributes( $attr, $args, $foogallery_attachment ) {

			$foobox_panning = get_post_meta( $foogallery_attachment->ID, '_foobox_panning', true );

			if ( !empty( $foobox_panning ) ) {
				//add data-overflow="true" + data-proportion="false" attributes to the anchor link
				$attr['data-overflow'] = 'true';
				$attr['data-proportion'] = 'false';
			}

			return $attr;
		}
	}
}
