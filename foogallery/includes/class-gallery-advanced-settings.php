<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for adding advanced settings to all gallery templates
 */
if ( ! class_exists( 'FooGallery_Advanced_Gallery_Settings' ) ) {

	class FooGallery_Advanced_Gallery_Settings {

		function __construct() {
			//add fields to all templates
			add_filter( 'foogallery_override_gallery_template_fields', array( $this, 'add_advanced_fields' ), 20, 2 );

			//add data options
			add_filter( 'foogallery_build_container_data_options', array( $this, 'add_data_options' ), 30, 3 );

			//add custom attributes
			add_filter( 'foogallery_build_container_attributes', array( $this, 'add_container_attributes' ), 10, 3 );

			//sanitize and authorize developer settings when gallery settings are saved
			add_filter( 'foogallery_save_gallery_settings', array( $this, 'save_custom_attribute_settings' ), 20, 3 );

			//add custom class to container
			add_filter( 'foogallery_build_class_attribute', array( $this, 'add_custom_class' ), 10, 2 );

			//remove the title attribute from the image
			add_filter('foogallery_attachment_html_image_attributes', array($this, 'remove_title_attribute'), 99, 3);
		}

		/**
		 * @param array $attr
		 * @param array $args
		 * @param FooGalleryAttachment $attachment
		 * @return mixed
		 */
		function remove_title_attribute($attr, $args, $attachment) {
			//make sure we use a cached value
			if ( !foogallery_current_gallery_has_cached_value( 'include_title') ) {
				foogallery_current_gallery_set_cached_value( 'include_title', foogallery_gallery_template_setting( 'include_title', '' ) );
			}

			if ( 'disabled' === foogallery_current_gallery_get_cached_value( 'include_title' ) ) {
				if ( array_key_exists( 'title', $attr ) ) {
					unset( $attr['title'] );
				}
			}

			return $attr;
		}

		/**
		 * Add fields to the gallery template
		 *
		 * @param $fields
		 * @param $template
		 *
		 * @return array
		 */
		function add_advanced_fields( $fields, $template ) {
			$custom_attribute_disabled = ! current_user_can( 'manage_options' );
			$custom_attribute_row_data = $custom_attribute_disabled ? array( 'data-foogallery-locked' => 'true' ) : array();

			$fields[] = array(
				'id'       => 'custom_settings',
				'disabled' => $custom_attribute_disabled,
				'row_data' => $custom_attribute_row_data,
				'title'    => __( 'Custom Settings', 'foogallery' ),
				'desc'     => __( 'Add any custom settings to the gallery which will be merged with existing settings. Only administrators can edit this setting. JSON must be an object, at most 64 KiB and 32 levels deep, without prototype keys. To be used by developers only!', 'foogallery' ),
				'section_id' => 'advanced',
				'type'     => 'textarea',
				'default'  => '',
			);

            $fields[] = array(
                'id'       => 'custom_attribute_key',
                'title'    => __( 'Custom Attribute Key', 'foogallery' ),
                'desc'     => __( 'Used in combination with "Custom Attribute Value" to add a custom attribute to the gallery container. Only administrators can edit this setting. To be used by developers only!', 'foogallery' ),
                'section_id' => 'advanced',
                'type'     => 'text',
                'default'  => '',
                'disabled' => $custom_attribute_disabled,
                'row_data' => $custom_attribute_row_data,
            );

            $fields[] = array(
                'id'       => 'custom_attribute_value',
                'title'    => __( 'Custom Attribute Value', 'foogallery' ),
                'desc'     => __( 'Used in combination with "Custom Attribute Key" to add a custom attribute to the gallery container. Only administrators can edit this setting. To be used by developers only!', 'foogallery' ),
                'section_id' => 'advanced',
                'type'     => 'text',
                'default'  => '',
                'disabled' => $custom_attribute_disabled,
                'row_data' => $custom_attribute_row_data,
            );

			$fields[] = array(
				'id'       => 'custom_class',
				'title'    => __( 'Custom Gallery Class', 'foogallery' ),
				'desc'     => __( 'Add a custom class to the gallery container.', 'foogallery' ),
				'section_id' => 'advanced',
				'type'     => 'text',
				'default'  => '',
			);

			$fields[] = array(
				'id'      => 'include_title',
				'title'   => __( 'Image Title Attribute', 'foogallery' ),
				'desc'    => __( 'You can choose to include a title attribute on the thumbnail image or not.', 'foogallery' ),
				'section_id' => 'advanced',
				'type'     => 'radio',
				'default'  => '',
				'choices'  => array(
					'disabled' => __( 'Disabled', 'foogallery' ),
					'' => __( 'Enabled', 'foogallery' ),
				),
				'row_data' => array(
					'data-foogallery-change-selector' => 'input:radio',
					'data-foogallery-preview' => 'shortcode'
				)
			);

			return $fields;
		}

		/**
		 * Add the required data options
		 *
		 * @param $options
		 * @param $gallery    FooGallery
		 *
		 * @param $attributes array
		 *
		 * @return array
		 */
		function add_data_options($options, $gallery, $attributes) {
			$custom_settings = foogallery_gallery_template_setting( 'custom_settings', '' );

			if ( !empty( $custom_settings ) ) {
				$validated = $this->validate_custom_settings( $custom_settings );
				$settings_array = false !== $validated ? json_decode( $validated, true ) : null;

				if ( is_array( $settings_array ) ) {
					$options = array_replace_recursive( $options, $settings_array );
				}
			}

			return $options;
		}

		/**
		 * Validate developer JSON before saving and before merging legacy metadata.
		 *
		 * @param mixed $value Unslashed JSON string.
		 * @return string|false Original JSON, or false for invalid input.
		 */
		function validate_custom_settings( $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 65536 ) {
				return false;
			}
			if ( '' === trim( $value ) ) {
				return '';
			}
			$decoded = json_decode( $value, false, 32 );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $decoded ) || ! $this->custom_settings_keys_are_safe( $decoded ) ) {
				return false;
			}
			return $value;
		}

		/**
		 * Reject prototype mutation keys at every level, including objects in arrays.
		 *
		 * @param mixed $value Decoded JSON value.
		 * @return bool
		 */
		function custom_settings_keys_are_safe( $value ) {
			if ( is_object( $value ) || is_array( $value ) ) {
				foreach ( $value as $key => $child ) {
					if ( in_array( $key, array( '__proto__', 'prototype', 'constructor' ), true ) || ! $this->custom_settings_keys_are_safe( $child ) ) {
						return false;
					}
				}
			}
			return true;
		}

		/**
		 * Returns a saved custom attribute setting for the current gallery template.
		 *
		 * @param FooGallery $gallery Gallery object.
		 * @param string     $key     Setting key without the template prefix.
		 *
		 * @return string
		 */
		function get_saved_custom_attribute_setting( $gallery, $key ) {
			$setting_key = $gallery->gallery_template . '_' . $key;

			if ( isset( $gallery->settings ) && is_array( $gallery->settings ) && array_key_exists( $setting_key, $gallery->settings ) ) {
				return $gallery->settings[ $setting_key ];
			}

			return '';
		}

		/**
		 * Returns true when a gallery setting key stores protected developer data.
		 *
		 * @param string $key Setting key.
		 *
		 * @return bool
		 */
		function is_custom_attribute_setting_key( $key ) {
			return is_string( $key ) && 1 === preg_match( '/_custom_(?:attribute_(?:key|value)|settings)$/', $key );
		}

		/**
		 * Sanitizes and authorizes saved custom attributes and developer JSON.
		 *
		 * @param array $settings  Incoming settings.
		 * @param int   $post_id   Gallery post ID.
		 * @param array $post_data Submitted post data or save context.
		 *
		 * @return array
		 */
		function save_custom_attribute_settings( $settings, $post_id, $post_data ) {
			$settings = is_array( $settings ) ? $settings : array();

			if ( ! current_user_can( 'manage_options' ) ) {
				foreach ( array_keys( $settings ) as $setting_key ) {
					if ( $this->is_custom_attribute_setting_key( $setting_key ) ) {
						unset( $settings[ $setting_key ] );
					}
				}

				$existing_settings = get_post_meta( $post_id, FOOGALLERY_META_SETTINGS, true );
				if ( is_array( $existing_settings ) ) {
					foreach ( $existing_settings as $setting_key => $setting_value ) {
						if ( $this->is_custom_attribute_setting_key( $setting_key ) ) {
							$settings[ $setting_key ] = isset( $post_data[ FOOGALLERY_CPT_GALLERY . '_nonce' ] ) ? wp_slash( $setting_value ) : $setting_value;
						}
					}
				}

				return $settings;
			}

			foreach ( $settings as $setting_key => $setting_value ) {
				if ( is_string( $setting_key ) && '_custom_settings' === substr( $setting_key, -16 ) ) {
					// Native editor submissions are slashed; API callers supply plain values.
					$slashed = isset( $post_data[ FOOGALLERY_CPT_GALLERY . '_nonce' ] );
					$value = $this->validate_custom_settings( $slashed ? wp_unslash( $setting_value ) : $setting_value );
					if ( false !== $value ) {
						// Retain the existing HTML/JavaScript boundary without storing broken JSON.
						$value = $this->validate_custom_settings( foogallery_sanitize_full( $value ) );
					}
					if ( false === $value ) {
						$existing = get_post_meta( $post_id, FOOGALLERY_META_SETTINGS, true );
						$value = isset( $existing[ $setting_key ] ) ? $existing[ $setting_key ] : '';
					}
					$settings[ $setting_key ] = $slashed ? wp_slash( $value ) : $value;
				}
			}

			$gallery = ( $post_id > 0 && class_exists( 'FooGallery' ) ) ? FooGallery::get_by_id( $post_id ) : null;
			$handled_value_keys = array();

			foreach ( array_keys( $settings ) as $setting_key ) {
				if ( ! is_string( $setting_key ) || '_custom_attribute_key' !== substr( $setting_key, -21 ) ) {
					continue;
				}

				$value_key = substr( $setting_key, 0, -21 ) . '_custom_attribute_value';
				$handled_value_keys[] = $value_key;

				$custom_attribute_key = foogallery_sanitize_custom_attribute_key( $settings[ $setting_key ], $gallery );
				$custom_attribute_value = array_key_exists( $value_key, $settings ) ? foogallery_sanitize_custom_attribute_value( $settings[ $value_key ] ) : '';

				if ( '' === $custom_attribute_key || '' === $custom_attribute_value ) {
					unset( $settings[ $setting_key ], $settings[ $value_key ] );
					continue;
				}

				$settings[ $setting_key ] = $custom_attribute_key;
				$settings[ $value_key ] = $custom_attribute_value;
			}

			foreach ( array_keys( $settings ) as $setting_key ) {
				if ( is_string( $setting_key ) && '_custom_attribute_value' === substr( $setting_key, -23 ) && ! in_array( $setting_key, $handled_value_keys, true ) ) {
					unset( $settings[ $setting_key ] );
				}
			}

			return $settings;
		}

		/**
		 * Adds a custom attribute to the gallery container attributes
		 *
		 * @param $attributes
		 * @param $gallery
		 *
		 * @return mixed
		 */
		function add_container_attributes( $attributes, $gallery ) {
			global $current_foogallery;

			if ( $current_foogallery === $gallery ) {
                $custom_attribute_key = foogallery_sanitize_custom_attribute_key( $this->get_saved_custom_attribute_setting( $gallery, 'custom_attribute_key' ), $gallery );
                $custom_attribute_value = foogallery_sanitize_custom_attribute_value( $this->get_saved_custom_attribute_setting( $gallery, 'custom_attribute_value' ) );

                if ( !empty( $custom_attribute_key ) && !empty( $custom_attribute_value ) ) {
                    $attributes[$custom_attribute_key] = $custom_attribute_value;
                }
            }

			return $attributes;
		}


		/**
		 * Add the custom class to the array of classes
		 *
		 * @param $classes
		 * @param $gallery
		 *
		 * @return array
		 */
		function add_custom_class( $classes, $gallery ) {
			global $current_foogallery;

			if ( $current_foogallery === $gallery ) {
                $custom_class = sanitize_title( foogallery_gallery_template_setting( 'custom_class', '' ) );

				if ( !empty( $custom_class ) ) {
					$classes[] = $custom_class;
				}
			}

			return $classes;
		}
	}
}
