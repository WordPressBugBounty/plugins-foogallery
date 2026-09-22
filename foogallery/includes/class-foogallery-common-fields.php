<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds all functionality related to the common gallery fields that are used in the default gallery templates
 * Date: 12/09/2017
 */
if ( ! class_exists( 'FooGallery_Common_Fields' ) ) {

	class FooGallery_Common_Fields {

		function __construct() {
            //handle some default field types that all templates can reuse
            add_filter( 'foogallery_alter_gallery_template_field', array( $this, 'alter_gallery_template_field' ), 10, 2 );

            //build up class attributes
			add_filter( 'foogallery_build_class_attribute', array( $this, 'add_common_fields_class_attributes' ), 10, 2 );

			//add our common field data attribute
			add_filter( 'foogallery_build_container_attributes', array( $this, 'add_common_fields_data_attribute' ), 10, 2 );

			//add common data options
			add_filter( 'foogallery_build_container_data_options', array( $this, 'add_caption_data_options' ), 10, 3 );
			add_filter( 'foogallery_build_container_mobile_data_options', array( $this, 'add_common_mobile_data_options' ), 10, 3 );

			add_filter( 'foogallery_build_container_attributes', array( $this, 'add_common_fields_attributes' ), 10, 2 );
			add_filter( 'foogallery_template_style_block', array( $this, 'add_common_mobile_css' ), 10, 3 );

            //add common fields to the templates that support it
            add_filter( 'foogallery_override_gallery_template_fields', array( $this, 'add_common_fields' ), 10, 2 );

            //check that we are no longer on pro and have previously used a preset or a loaded effect
            add_filter( 'foogallery_render_gallery_template_field_value', array( $this, 'check_downgrade_values' ), 10, 4 );

			//override settings for older versions
			add_filter( 'foogallery_settings_override', array( $this, 'override_settings_for_older_versions' ), 10, 3 );

			// handle aliases for common fields
			add_filter( 'foogallery_gallery_template_argument_alias', array( $this, 'handle_aliases' ), 10, 2 );
		}

		/**
		 * Handle aliases for common fields.
		 *
		 * @param string $key The key of the field.
		 * @param string $template The template slug.
		 * @return string
		 */
		function handle_aliases( $key, $template ) {
			if ( 'caption_invert_color' === $key ) {
				return 'hover_effect_theme';
			} else if ( 'hover_effect_caption_visibility' === $key ) {
				return 'caption_visibility';
			} else if ( 'thumbnail_dimensions' === $key ) {
				return 'thumbnail_size';
			}
			return $key;
		}

		/**
		 * Alter the gallery template field.
		 *
		 * @param array $field The field array.
		 * @param object $gallery The gallery object.
		 * @return array
		 */
        function alter_gallery_template_field( $field, $gallery ) {
            if ( $field ) {

            	if ( isset( $field['type'] ) ) {
					switch ( $field['type'] ) {
						case 'thumb_link':
							$field['type']    = 'radio';
							$field['class']   = 'foogallery-radios-stacked';
							$field['choices'] = foogallery_gallery_template_field_thumb_link_choices();
							if ( ! array_key_exists( 'desc', $field ) ) {
								$field['desc'] = __( 'You can choose to link each thumbnail to the full size image, the image\'s attachment page, the parent post it was uploaded to, a custom URL, or you can choose to not link to anything.', 'foogallery' );
							}
							if ( !isset( $field['row_data'] ) ) {
								$field['row_data'] = array(
									'data-foogallery-change-selector' => 'input:radio',
									'data-foogallery-value-selector'  => 'input:checked',
									'data-foogallery-preview'         => 'shortcode',
								);
							}
							break;
						case 'lightbox':
                            $field['lightbox'] = true;
                            $field['title'] = __( 'Lightbox', 'foogallery' );
                            $field['type'] = 'select';
                            $field['choices'] = foogallery_gallery_template_field_lightbox_choices();
                            if ( !array_key_exists( 'desc', $field ) ) {
                                $field['desc'] = __( 'Choose which lightbox you want to use. The lightbox will generally only work if you set the thumbnail link to "Full Size Image".', 'foogallery' );
                            }
	                            if ( !array_key_exists( 'section_id', $field ) && ! array_key_exists( 'section', $field ) ) {
	                                $field['section_id'] = 'lightbox';
	                            }
	                            $field['subsection_id'] = 'lightbox-general';
                            $field['default'] = 'foogallery';

							if ( !isset( $field['row_data'] ) ) {
								$field['row_data'] = array(
                                    'data-foogallery-change-selector'          => 'select',
                                    'data-foogallery-value-selector'           => 'select',
                                    'data-foogallery-preview'                  => 'shortcode',
                                );
							}
							break;
					}
				}

                if ( isset($field['help']) && $field['help'] ) {
                    $field['type'] = 'help';
                }
            }
            return $field;
        }

		/**
		 * Add common fields to the gallery template if supported
		 *
		 * @param $fields
		 * @param $template
		 *
		 * @return array
		 */
		function add_common_fields( $fields, $template ) {
			//check if the template supports the common fields
			if ( $template && array_key_exists( 'common_fields_support', $template ) && true === $template['common_fields_support'] ) {

				//region Appearance Fields
				$fields[] = array(
					'id'      => 'theme_custom_help',
					'desc'    => __( 'If you choose to use the Custom theme, then you will need to provide your own Custom CSS in order to style the gallery to suit your needs.', 'foogallery' ),
					'section_id' => 'appearance',
					'type'    => 'help',
					'row_data' => array(
						'data-foogallery-hidden' 				   => true,
						'data-foogallery-show-when-field'          => 'theme',
						'data-foogallery-show-when-field-value'    => 'fg-custom',
					)
				);

				$fields[] = array(
					'id'       => 'theme',
					'title'    => __( 'Theme', 'foogallery' ),
					'desc'     => __( 'The overall appearance of the items in the gallery, affecting the border, background, font and shadow colors.', 'foogallery' ),
					'section_id' => 'appearance',
					'type'     => 'radio',
					'default'  => 'fg-light',
					'choices'  => array(
						'fg-light'  => __( 'Light', 'foogallery' ),
						'fg-dark'   => __( 'Dark', 'foogallery' ),
						'fg-custom' => __( 'Custom', 'foogallery' )
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-value-selector'  => 'input:checked',
						'data-foogallery-preview'         => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'border_size',
					'title'    => __( 'Border Size', 'foogallery' ),
					'desc'     => __( 'The border size applied to each thumbnail', 'foogallery' ),
					'section_id' => 'appearance',
					'type'     => 'radio',
					'default'  => 'fg-border-thin',
					'choices'  => array(
						''                 => __( 'None', 'foogallery' ),
						'fg-border-thin'   => __( 'Thin', 'foogallery' ),
						'fg-border-medium' => __( 'Medium', 'foogallery' ),
						'fg-border-thick'  => __( 'Thick', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-preview'         => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'rounded_corners',
					'title'    => __( 'Rounded Corners', 'foogallery' ),
					'desc'     => __( 'The border radius, or rounded corners applied to each thumbnail', 'foogallery' ),
					'section_id' => 'appearance',
					'type'     => 'radio',
					'default'  => '',
					'choices'  => array(
						''                => __( 'None', 'foogallery' ),
						'fg-round-small'  => __( 'Small', 'foogallery' ),
						'fg-round-medium' => __( 'Medium', 'foogallery' ),
						'fg-round-large'  => __( 'Large', 'foogallery' ),
						'fg-round-full'   => __( 'Full', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-preview'         => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'drop_shadow',
					'title'    => __( 'Drop Shadow', 'foogallery' ),
					'desc'     => __( 'The outer or drop shadow applied to each thumbnail', 'foogallery' ),
					'section_id' => 'appearance',
					'type'     => 'radio',
					'default'  => 'fg-shadow-outline',
					'choices'  => array(
						''                  => __( 'None', 'foogallery' ),
						'fg-shadow-outline' => __( 'Outline', 'foogallery' ),
						'fg-shadow-small'   => __( 'Small', 'foogallery' ),
						'fg-shadow-medium'  => __( 'Medium', 'foogallery' ),
						'fg-shadow-large'   => __( 'Large', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-preview'         => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'inner_shadow',
					'title'    => __( 'Inner Shadow', 'foogallery' ),
					'desc'     => __( 'The inner shadow applied to each thumbnail', 'foogallery' ),
					'section_id' => 'appearance',
					'type'     => 'radio',
					'default'  => '',
					'choices'  => array(
						''                        => __( 'None', 'foogallery' ),
						'fg-shadow-inset-outline' => __( 'Outline', 'foogallery' ),
						'fg-shadow-inset-small'   => __( 'Small', 'foogallery' ),
						'fg-shadow-inset-medium'  => __( 'Medium', 'foogallery' ),
						'fg-shadow-inset-large'   => __( 'Large', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-preview'         => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'loading_icon',
					'title'    => __( 'Loading Icon', 'foogallery' ),
					'desc'     => __( 'An animated loading icon can be shown while the thumbnails are busy loading.', 'foogallery' ),
					'section_id' => 'appearance',
					'default'  => 'fg-loading-default',
					'type'     => 'htmlicon',
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_loading_icon_choices', array(
						''                   => array( 'label' => __( 'None', 'foogallery' ), 'html' => '<div class="foogallery-setting-loading_icon"></div>' ),
						'fg-loading-default' => array( 'label' => __( 'Default', 'foogallery' ), 'html' => '<div class="foogallery-setting-loading_icon foogallery fg-loading-default"><div class="fg-loading"><div class="fg-loader"></div></div></div>' ),
						'fg-loading-bars'    => array( 'label' => __( 'Bars', 'foogallery' ), 'html' => '<div class="foogallery-setting-loading_icon foogallery fg-loading-bars"><div class="fg-loading"><div class="fg-loader"></div></div></div>' ),
						'fg-loading-dots'    => array( 'label' => __( 'Dots', 'foogallery' ), 'html' => '<div class="foogallery-setting-loading_icon foogallery fg-loading-dots"><div class="fg-loading"><div class="fg-loader"></div></div></div>' ),
						'fg-loading-partial' => array( 'label' => __( 'Partial', 'foogallery' ), 'html' => '<div class="foogallery-setting-loading_icon foogallery fg-loading-partial"><div class="fg-loading"><div class="fg-loader"></div></div></div>' ),
						'fg-loading-pulse'   => array( 'label' => __( 'Pulse', 'foogallery' ), 'html' => '<div class="foogallery-setting-loading_icon foogallery fg-loading-pulse"><div class="fg-loading"><div class="fg-loader"></div></div></div>' ),
						'fg-loading-trail'   => array( 'label' => __( 'Trail', 'foogallery' ), 'html' => '<div class="foogallery-setting-loading_icon foogallery fg-loading-trail"><div class="fg-loading"><div class="fg-loader"></div></div></div>' ),
					)
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
                        'data-foogallery-value-selector'  => 'input:checked',
						'data-foogallery-preview'         => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'loaded_effect',
					'title'    => __( 'Loaded Effect', 'foogallery' ),
					'desc'     => __( 'The animation effect used to display the thumbnail, once it has loaded.', 'foogallery' ),
					'section_id' => 'appearance',
					'default'  => 'fg-loaded-fade-in',
					'type'     => 'radio',
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_loaded_effect_choices', array(
							''                      => __( 'None', 'foogallery' ),
							'fg-loaded-fade-in'     => __( 'Fade In', 'foogallery' ),
							'fg-loaded-slide-up'    => __( 'Slide Up', 'foogallery' ),
							'fg-loaded-slide-down'  => __( 'Slide Down', 'foogallery' ),
							'fg-loaded-slide-left'  => __( 'Slide Left', 'foogallery' ),
							'fg-loaded-slide-right' => __( 'Slide Right', 'foogallery' ),
							'fg-loaded-scale-up'    => __( 'Scale Up', 'foogallery' ),
							'fg-loaded-swing-down'  => __( 'Swing Down', 'foogallery' ),
							'fg-loaded-drop'        => __( 'Drop', 'foogallery' ),
							'fg-loaded-fly'         => __( 'Fly', 'foogallery' ),
							'fg-loaded-flip'        => __( 'Flip', 'foogallery' ),
						)
					),
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
                        'data-foogallery-value-selector'  => 'input:checked',
						'data-foogallery-preview'         => 'shortcode'
					),
					'class' => 'foogallery-radios-12em',
				);
				//endregion

				//region Hover Effects Fields
				$fields[] = array(
					'id'       => 'hover_effect_type',
					'title'    => __( 'Hover Effect Type', 'foogallery' ),
					'section_id' => 'hover-effects',
					'default'  => 'normal',
					'type'     => 'radio',
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_hover_effect_type_choices', array(
							'none'   => array(
								'label' => __( 'None', 'foogallery' ),
								'tooltip'  => __( 'No hover effect will be shown.', 'foogallery' )
							),
							'normal' => array(
								'label' => __( 'Normal (icons, captions, color, scaling & transitions)', 'foogallery' ),
								'tooltip'  => __( 'The standard hover effects with icons, captions, color, scaling & transitions', 'foogallery' )
							),
							'preset' =>array(
								'label' => __( 'Presets (stylish, pre-defined look & feel)', 'foogallery' ),
								'tooltip'  => __( 'A preset provides a stylish, pre-defined look & feel for the effect when you hover over the thumbnails.', 'foogallery' )
							)
						)
					),
					'desc'     => __( 'What type of hover effect do you want to show for your thumbnails?', 'foogallery' ),
					'mobile'   => true,
					'row_data' => array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-value-selector'  => 'input:checked',
						'data-foogallery-preview'         => 'shortcode'
					),
					'class' => 'foogallery-radios-stacked'
				);

				$fields[] = array(
					'id'       => 'hover_effect_preset',
					'title'    => __( 'Preset', 'foogallery' ),
					'section_id' => 'hover-effects',
					'default'  => 'fg-preset fg-brad',
					'type'     => 'radio',
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_hover_effect_preset_choices', array(
							'fg-preset fg-brad'    => __( 'Default', 'foogallery' ),
						)
					),
					'desc'     => __( 'A preset styling that is used for the hover effect.', 'foogallery' ),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-value-selector'        => 'input:checked',
						'data-foogallery-preview'               => 'shortcode',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'preset',
					),
					'class' => 'foogallery-radios-12em',
				);
	
				$fields[] = array(
					'id'       => 'hover_effect_preset_size',
					'title'    => __( 'Preset Size', 'foogallery' ),
					'section_id' => 'hover-effects',
					'default'  => 'fg-preset-small',
					'type'     => 'radio',
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_hover_effect_preset_size_choices', array(
							'fg-preset-smallest' => __( 'Smallest', 'foogallery' ),
							'fg-preset-small'    => __( 'Small', 'foogallery' ),
							'fg-preset-medium'   => __( 'Medium', 'foogallery' ),
							'fg-preset-large'    => __( 'Large', 'foogallery' ),
						)
					),
					'mobile'   => true,
					'desc'     => __( 'Choose an appropriate size for the preset hover effects, based on the size of your thumbs. Choose smallest for thumbs under 150 wide, small for thumbs 150-200 wide, medium for thumbs 200-400 wide, and large for thumbs over 400 wide.', 'foogallery' ),
					'row_data' => array(
						'data-foogallery-change-selector'          => 'input:radio',
						'data-foogallery-hidden'                   => true,
						'data-foogallery-show-when-field'          => 'hover_effect_type',
						'data-foogallery-show-when-field-value'    => 'preset',
						'data-foogallery-preview'                  => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'caption_invert_color',
					'title'    => __( 'Theme', 'foogallery' ),
					'desc'     => __( 'Choose a color theme that will be used for the hover effect.', 'foogallery' ),
					'section_id' => 'hover-effects',
					'alias'    => 'hover_effect_theme',
					'type'     => 'radio',
					'default'  => '',
					'mobile'   => true,
					'choices'  => apply_filters( 'foogallery_gallery_template_common_thumbnail_fields_caption_invert_color_choices', array(
						'' => array(
							'label'   => __( 'Dark',   'foogallery' ),
							'tooltip' => __('A dark overlay with white text is shown on hover', 'foogallery'),
						),
						'fg-light-overlays' => array(
							'label'   => __( 'Light',   'foogallery' ),
							'tooltip' => __('A white overlay with dark text is shown on hover', 'foogallery'),
						),
						'fg-transparent-overlays' => array(
							'label'   => __( 'Transparent',   'foogallery' ),
							'tooltip' => __('A transparent overlay with white text is shown on hover', 'foogallery'),
						),
					) ),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'normal',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'hover_effect_color',
					'title'    => __( 'Color Effect', 'foogallery' ),
					'section_id' => 'hover-effects',
					'default'  => '',
					'type'     => 'radio',
					'mobile'   => true,
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_hover_effect_color_choices', array(
						''                   => __( 'None', 'foogallery' ),
						'fg-hover-colorize'  => __( 'Colorize', 'foogallery' ),
						'fg-hover-grayscale' => __( 'Greyscale', 'foogallery' ),
					)
					),
					'desc'     => __( 'Choose an color effect that is applied when you hover over a thumbnail.', 'foogallery' ),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'normal',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'hover_effect_scale',
					'title'    => __( 'Scaling Effect', 'foogallery' ),
					'section_id' => 'hover-effects',
					'default'  => '',
					'type'     => 'radio',
					'mobile'   => true,
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_hover_effect_scale_choices', array(
							''               => __( 'None', 'foogallery' ),
							'fg-hover-scale' => __( 'Scaled', 'foogallery' ),
							'fg-hover-zoomed' => __( 'Zoomed', 'foogallery' ),
							'fg-hover-semi-zoomed' => __( 'Semi Zoomed', 'foogallery' ),
						)
					),
					'desc'     => __( 'Apply a slight scaling effect when hovering over a thumbnail.', 'foogallery' ),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'normal',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'hover_effect_transition',
					'title'    => __( 'Transition', 'foogallery' ),
					'section_id' => 'hover-effects',
					'default'  => 'fg-hover-fade',
					'type'     => 'radio',
					'mobile'   => true,
					'choices'  => apply_filters( 'foogallery_gallery_template_common_thumbnail_fields_hover_effect_transition_choices', array(
						'fg-hover-instant'     => __( 'Instant', 'foogallery' ),
						'fg-hover-fade'        => __( 'Fade', 'foogallery' ),
						'fg-hover-slide-up'    => __( 'Slide Up', 'foogallery' ),
						'fg-hover-slide-down'  => __( 'Slide Down', 'foogallery' ),
						'fg-hover-slide-left'  => __( 'Slide Left', 'foogallery' ),
						'fg-hover-slide-right' => __( 'Slide Right', 'foogallery' ),
						'fg-hover-push'        => __( 'Push', 'foogallery' ) )
					),
					'desc'     => __( 'Choose what effect is used to show the caption when you hover over a thumbnail', 'foogallery' ),
					'row_data' => array(
						'data-foogallery-change-selector' 		=> 'input:radio',
						'data-foogallery-value-selector'  		=> 'input:checked',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'normal',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'hover_effect_icon',
					'title'    => __( 'Icon', 'foogallery' ),
					'desc'     => __( 'Choose which icon is shown when you hover over a thumbnail', 'foogallery' ),
					'section_id' => 'hover-effects',
					'type'     => 'htmlicon',
					'default'  => 'fg-hover-zoom',
					'mobile'   => true,
					'choices'  => apply_filters( 'foogallery_gallery_template_common_thumbnail_fields_hover_effect_icon_choices', array(
							''                     => array( 'label' => __( 'None', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon"></div>' ),
							'fg-hover-zoom'        => array( 'label' => __( 'Zoom', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-zoom"></div>' ),
							'fg-hover-zoom2'       => array( 'label' => __( 'Zoom 2', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-zoom2"></div>' ),
							'fg-hover-zoom3'       => array( 'label' => __( 'Zoom 3', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-zoom3"></div>' ),
							'fg-hover-zoom4'       => array( 'label' => __( 'Zoom 4', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-zoom4"></div>' ),
							'fg-hover-zoom5'       => array( 'label' => __( 'Zoom 5', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-zoom5"></div>' ),
							'fg-hover-plus'        => array( 'label' => __( 'Plus', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-plus"></div>' ),
							'fg-hover-plus2'       => array( 'label' => __( 'Plus 2', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-plus2"></div>' ),
							'fg-hover-plus3'       => array( 'label' => __( 'Plus 3', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-plus3"></div>' ),
							'fg-hover-circle-plus' => array( 'label' => __( 'Circle Plus', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-circle-plus"></div>' ),
							'fg-hover-circle-plus2'=> array( 'label' => __( 'Circle Plus 2', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-circle-plus2"></div>' ),
							'fg-hover-square-plus' => array( 'label' => __( 'Square Plus', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-square-plus"></div>' ), 
							'fg-hover-eye'         => array( 'label' => __( 'Eye', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-eye"></div>' ),
							'fg-hover-external'    => array( 'label' => __( 'External', 'foogallery' ), 'html' => '<div class="foogallery-setting-caption_icon fg-hover-external"></div>' ),
						)
					),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-value-selector'  		=> 'input:checked',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'normal',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'hover_effect_icon_size',
					'title'    => __( 'Icon Size', 'foogallery' ),
					'desc'     => __( 'Choose the size of the icon that is displayed when you hover over a thumbnail.', 'foogallery' ),
					'section_id' => 'hover-effects',
					'type'     => 'radio',
					'default'  => '',
					'mobile'   => true,
					'choices'  => array(
							''   => __( 'Default', 'foogallery' ),
							'48' => __( '1.5x', 'foogallery' ),
							'64' => __( '2x', 'foogallery' ),
							'80' => __( '2.5x', 'foogallery' ),
							'96' => __( '3x', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-value-selector'  		=> 'input:checked',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'normal',
						'data-foogallery-preview'               => 'shortcode'
					)
				);
				//endregion Hover Effects Fields

				//region Caption Fields
				$fields[] = array(
					'id'       => 'hover_effect_caption_visibility',
					'title'    => __( 'Caption Visibility', 'foogallery' ),
					'section_id' => 'captions',
					'alias'    => 'caption_visibility',
					'default'  => 'fg-caption-hover',
					'type'     => 'radio',
					'mobile'   => true,
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_hover_effect_caption_visibility_choices', array(
							''                  => __( 'Not shown', 'foogallery' ),
							'fg-caption-hover'  => __( 'On Hover', 'foogallery' ),
							'fg-caption-always' => __( 'Always Visible', 'foogallery' ),
						)
					),
					'desc'     => __( 'Choose when the captions will be displayed.', 'foogallery' ),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'normal',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'caption_visibility_no_hover_effect',
					'title'    => __( 'Caption Visibility', 'foogallery' ),
					'section_id' => 'captions',
					'default'  => '',
					'type'     => 'radio',
					'choices'  => apply_filters(
						'foogallery_gallery_template_common_thumbnail_fields_caption_visibility_no_hover_effect_choices', array(
							''                  => __( 'Not shown', 'foogallery' ),
							'fg-caption-always' => __( 'Always Visible', 'foogallery' ),
						)
					),
					'desc'     => __( 'Choose when the captions will be displayed.', 'foogallery' ),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'none',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'caption_color_no_hover_effect',
					'title'    => __( 'Caption Theme', 'foogallery' ),
					'desc'     => __( 'Choose the color theme for the captions.', 'foogallery' ),
					'section_id' => 'captions',
					'type'     => 'radio',
					'default'  => '',
					'choices'  => apply_filters( 'foogallery_gallery_template_common_thumbnail_fields_caption_color_no_hover_effect_choices', array(
						'' => array(
							'label'   => __( 'Dark',   'foogallery' ),
							'tooltip' => __('A dark overlay with white text is shown for the captions', 'foogallery'),
						),
						'fg-light-overlays' => array(
							'label'   => __( 'Light',   'foogallery' ),
							'tooltip' => __('A white overlay with dark text is shown for the captions', 'foogallery'),
						),
						'fg-transparent-overlays' => array(
							'label'   => __( 'Transparent',   'foogallery' ),
							'tooltip' => __('A transparent overlay with white text is shown for the captions', 'foogallery'),
						),
					) ),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'hover_effect_type',
						'data-foogallery-show-when-field-value' => 'none',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'caption_alignment',
					'title'    => __( 'Caption Alignment', 'foogallery' ),
					'desc'     => __( 'Change the horizontal alignment of the thumbnail captions', 'foogallery' ),
					'section_id' => 'captions',
					'type'     => 'radio',
					'default'  => '',
					'choices'  => array(
						''       => __( 'Default', 'foogallery' ),
						'fg-c-l' => __( 'Left', 'foogallery' ),
						'fg-c-c' => __( 'Center', 'foogallery' ),
						'fg-c-r' => __( 'Right', 'foogallery' ),
						'fg-c-j' => __( 'Justify', 'foogallery' ),
					),
					'mobile'   => true,
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$settings_link = sprintf( '<a target="blank" href="%s">%s</a>', foogallery_admin_settings_url(), __( 'settings', 'foogallery' ) );

				$fields[] = array(
					'id'       => 'caption_title_source',
					'title'    => __( 'Title', 'foogallery' ),
					'desc'     => __( 'Decide where caption titles are pulled from. By default, what is saved under general settings will be used, but it can be overridden per gallery', 'foogallery' ) . ' ' . __( 'Mobile overrides change thumbnail captions only. Lightbox captions use the desktop settings.', 'foogallery' ),
					'section_id' => 'captions',
					'type'     => 'radio',
					'class'    => 'foogallery-radios-stacked',
					'default'  => '',
					'choices'  => array(
						'none'    => __( 'None', 'foogallery' ),
						/* translators: %s: Value inserted at runtime. */
						''        => sprintf( __( 'Global default (as per %s)', 'foogallery' ), $settings_link ),
						'title'   => foogallery_get_attachment_field_friendly_name( 'title' ),
						'caption' => foogallery_get_attachment_field_friendly_name( 'caption' ),
						'alt'     => foogallery_get_attachment_field_friendly_name( 'alt' ),
						'desc'    => foogallery_get_attachment_field_friendly_name( 'desc' ),
					),
					'mobile'   => array(
						'default' => '',
						'inherit_choice' => __( 'Same as desktop', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'       => 'caption_desc_source',
					'title'    => __( 'Description', 'foogallery' ),
					'desc'     => __( 'Decide where captions descriptions are pulled from. By default, the general settings are used, but it can be overridden per gallery', 'foogallery' ) . ' ' . __( 'Mobile overrides change thumbnail captions only. Lightbox captions use the desktop settings.', 'foogallery' ),
					'section_id' => 'captions',
					'type'     => 'radio',
					'class'    => 'foogallery-radios-stacked',
					'default'  => '',
					'choices'  => array(
						'none'    => __( 'None', 'foogallery' ),
						/* translators: %s: Value inserted at runtime. */
						''        => sprintf( __( 'Global default (as per %s)', 'foogallery' ), $settings_link ),
						'title'   => foogallery_get_attachment_field_friendly_name( 'title' ),
						'caption' => foogallery_get_attachment_field_friendly_name( 'caption' ),
						'alt'     => foogallery_get_attachment_field_friendly_name( 'alt' ),
						'desc'    => foogallery_get_attachment_field_friendly_name( 'desc' ),
					),
					'mobile'   => array(
						'default' => '',
						'inherit_choice' => __( 'Same as desktop', 'foogallery' ),
					),
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input:radio',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'      => 'captions_limit_length',
					'title'   => __( 'Limit Caption Length', 'foogallery' ),
					'desc'    => __( 'You can limit the length of caption title and descriptions in the thumbnails. This will NOT limit the length of captions from within the lightbox.', 'foogallery' ),
					'section_id' => 'captions',
					'default' => 'clamp',
					'type'    => 'radio',
					'class'    => 'foogallery-radios-stacked',
					'choices' => array(
						'' => __( 'No', 'foogallery' ),
						'yes' => array(
							'label' => __( 'Yes (by character length)', 'foogallery' ),
							'tooltip' => __( 'Limit the length of the caption title and description by character length.', 'foogallery' ),
						),
						'clamp' => array(
							'label' => __( 'Yes (by lines)', 'foogallery' ),
							'tooltip' => __( 'Limit the length of the caption title and description by the number of lines.', 'foogallery' ),
						)
					),
					'row_data'=> array(
						'data-foogallery-change-selector' => 'input:radio',
						'data-foogallery-preview' => 'shortcode',
						'data-foogallery-value-selector'  => 'input:checked',
					)
				);

				$fields[] = array(
					'id'      => 'caption_title_length',
					'title'   => __( 'Max Title Length', 'foogallery' ),
					'desc'	  => __( 'A max length of zero will not apply a limit.', 'foogallery' ),
					'section_id' => 'captions',
					'type'    => 'number',
					'class'   => 'small-text',
					'default' => 0,
					'step'    => '1',
					'min'     => '0',
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'captions_limit_length',
						'data-foogallery-show-when-field-value' => 'yes',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'      => 'caption_desc_length',
					'title'   => __( 'Max Desc Length', 'foogallery' ),
					'desc'	  => __( 'A max length of zero will not apply a limit.', 'foogallery' ),
					'section_id' => 'captions',
					'type'    => 'number',
					'class'   => 'small-text',
					'default' => 0,
					'step'    => '1',
					'min'     => '0',
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'captions_limit_length',
						'data-foogallery-show-when-field-value' => 'yes',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'      => 'caption_title_clamp',
					'title'   => __( 'Max Title Lines', 'foogallery' ),
					'desc'	  => __( 'A max number of lines of text to display. A value of zero will not apply a limit.', 'foogallery' ),
					'section_id' => 'captions',
					'type'    => 'number',
					'class'   => 'small-text',
					'default' => 1,
					'step'    => '1',
					'min'     => '0',
					'mobile'  => true,
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'captions_limit_length',
						'data-foogallery-show-when-field-value' => 'clamp',
						'data-foogallery-preview'               => 'shortcode'
					)
				);

				$fields[] = array(
					'id'      => 'caption_desc_clamp',
					'title'   => __( 'Max Desc Lines', 'foogallery' ),
					'desc'	  => __( 'A max number of lines of text to display. A value of zero will not apply a limit.', 'foogallery' ),
					'section_id' => 'captions',
					'type'    => 'number',
					'class'   => 'small-text',
					'default' => 2,
					'step'    => '1',
					'min'     => '0',
					'mobile'  => true,
					'row_data' => array(
						'data-foogallery-change-selector'       => 'input',
						'data-foogallery-hidden'                => true,
						'data-foogallery-show-when-field'       => 'captions_limit_length',
						'data-foogallery-show-when-field-value' => 'clamp',
						'data-foogallery-preview'               => 'shortcode'
					)
				);
				//endregion

			}
			return $fields;
		}

		/**
		 * Return the effective common fields indexed by setting ID.
		 *
		 * @return array
		 */
		function get_common_fields_by_id() {
			global $current_foogallery_template;

			$fields_by_id = array();
			foreach ( foogallery_get_fields_for_template( $current_foogallery_template ) as $field ) {
				if ( is_array( $field ) && ! empty( $field['id'] ) ) {
					$fields_by_id[ $field['id'] ] = $field;
				}
			}

			return $fields_by_id;
		}

		/**
		 * Resolve a common field's effective choices.
		 *
		 * @param string $field_id Field ID.
		 * @return array
		 */
		function get_common_field_choices( $field_id ) {
			$fields_by_id = $this->get_common_fields_by_id();

			return isset( $fields_by_id[ $field_id ]['choices'] ) && is_array( $fields_by_id[ $field_id ]['choices'] ) ? $fields_by_id[ $field_id ]['choices'] : array();
		}

		/**
		 * Resolve the effective mobile hover type from filtered choices.
		 *
		 * @param mixed $desktop_type Desktop hover type.
		 * @return string
		 */
		function get_common_mobile_hover_type( $desktop_type ) {
			$desktop_type = is_scalar( $desktop_type ) ? (string) $desktop_type : '';
			$type_choices = $this->get_common_field_choices( 'hover_effect_type' );
			$valid_types  = ! empty( $type_choices ) ? array_keys( $type_choices ) : array( 'none', 'normal', 'preset' );
			$mobile_type  = foogallery_gallery_template_mobile_setting( 'mobile_hover_effect_type', null );

			return is_scalar( $mobile_type ) && in_array( (string) $mobile_type, $valid_types, true ) ? (string) $mobile_type : $desktop_type;
		}

		/**
		 * Resolve the class tokens used by a common hover branch.
		 *
		 * @param string $type   Hover branch.
		 * @param bool   $mobile Whether to read the responsive setting variants.
		 * @return array
		 */
		function get_common_hover_classes( $type, $mobile = false ) {
			$branch_settings = array(
				'normal' => array(
					'caption_invert_color'            => array( '', 'mobile_caption_invert_color' ),
					'hover_effect_color'              => array( '', 'mobile_hover_effect_color' ),
					'hover_effect_scale'              => array( '', 'mobile_hover_effect_scale' ),
					'hover_effect_caption_visibility' => array( 'fg-caption-hover', 'mobile_hover_effect_caption_visibility' ),
					'hover_effect_transition'         => array( 'fg-hover-fade', 'mobile_hover_effect_transition' ),
					'hover_effect_icon'               => array( 'fg-hover-zoom', 'mobile_hover_effect_icon' ),
				),
				'none' => array(
					'caption_visibility_no_hover_effect' => array( '', '' ),
					'caption_color_no_hover_effect'      => array( '', '' ),
				),
				'preset' => array(
					'hover_effect_preset'      => array( 'fg-preset fg-brad', 'mobile_hover_effect_preset' ),
					'hover_effect_preset_size' => array( 'fg-preset-small', 'mobile_hover_effect_preset_size' ),
				),
			);
			if ( ! is_scalar( $type ) || ! isset( $branch_settings[ (string) $type ] ) ) {
				return array();
			}
			$type = (string) $type;

			$choices_by_id = array();
			$fields_by_id  = array();
			if ( $mobile ) {
				$fields_by_id = $this->get_common_fields_by_id();
				foreach ( $fields_by_id as $field_id => $field ) {
					if ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
						$choices_by_id[ $field_id ] = $field['choices'];
					}
				}
			}

			$classes = array();
			foreach ( $branch_settings[ $type ] as $setting_id => $setting ) {
				$desktop_value = foogallery_gallery_template_setting( $setting_id, $setting[0] );
				$value         = $desktop_value;
				$mobile_id     = $setting[1];
				if ( $mobile && '' !== $mobile_id ) {
					$inherit_value = '__foogallery_mobile_inherit__';
					$mobile_value  = foogallery_gallery_template_mobile_setting( $mobile_id, $inherit_value );
					$field_id = str_replace( 'mobile_', '', $mobile_id );
					if ( $inherit_value !== $mobile_value && null !== $mobile_value && ( ! isset( $choices_by_id[ $field_id ] ) || ( is_scalar( $mobile_value ) && array_key_exists( (string) $mobile_value, $choices_by_id[ $field_id ] ) ) ) ) {
						$value = $mobile_value;
					}
				}

				if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
					continue;
				}
				$tokens = preg_split( '/\s+/', trim( (string) $value ) );
				if ( is_array( $tokens ) ) {
					$classes = array_merge( $classes, $tokens );
				}
			}

			return array_values( array_unique( $classes ) );
		}

		/**
		 * Build up the gallery class attribute for the common fields
		 *
		 * @param $classes array
		 * @param $gallery FooGallery
		 *
		 * @return array
		 */
		function add_common_fields_class_attributes( $classes, $gallery ) {
			if ( foogallery_current_gallery_check_template_has_supported_feature('common_fields_support' ) ) {

				//add the gallery template core class
				$classes[] = 'fg-' . $gallery->gallery_template;

				//always add the fg-ready class, to avoid some javascript errors.
				$classes[] = 'fg-ready';

				//get some default classes from common gallery settings
				$classes[] = foogallery_gallery_template_setting( 'theme', 'fg-light' );
				$classes[] = foogallery_gallery_template_setting( 'border_size', 'fg-border-thin' );
				$classes[] = foogallery_gallery_template_setting( 'rounded_corners', '' );
				$classes[] = foogallery_gallery_template_setting( 'drop_shadow', 'fg-shadow-outline' );
				$classes[] = foogallery_gallery_template_setting( 'inner_shadow', '' );
				$classes[] = foogallery_gallery_template_setting( 'loading_icon', 'fg-loading-default' );
				$classes[] = foogallery_gallery_template_setting( 'loaded_effect', 'fg-loaded-fade-in' );

				$hover_effect_type = foogallery_gallery_template_setting( 'hover_effect_type', '' );
				$classes           = array_merge( $classes, $this->get_common_hover_classes( $hover_effect_type ) );

				$classes[] = foogallery_gallery_template_setting( 'caption_alignment', '' );
			}

			return $classes;
		}

		/**
		 * Add the required data options for captions
		 *
		 * @param $options
		 * @param $gallery    FooGallery
		 *
		 * @param $attributes array
		 *
		 * @return array
		 */
		function add_caption_data_options($options, $gallery, $attributes) {
			//check the template supports common fields
			if ( foogallery_current_gallery_check_template_has_supported_feature('common_fields_support' ) ) {

				$caption_title = foogallery_gallery_template_setting( 'caption_title_source', '' );
				$caption_desc  = foogallery_gallery_template_setting( 'caption_desc_source', '' );

				$options['item']['showCaptionTitle']       = $caption_title !== 'none';
				$options['item']['showCaptionDescription'] = $caption_desc !== 'none';

				$captions_limit_length = foogallery_gallery_template_setting( 'captions_limit_length', 'clamp' );

				if ( 'yes' === $captions_limit_length ) {
					$caption_title_length                    = foogallery_gallery_template_setting( 'caption_title_length', '0' );
					$caption_desc_length                     = foogallery_gallery_template_setting( 'caption_desc_length', '0' );
					$options['item']['maxCaptionLength']     = intval( $caption_title_length );
					$options['item']['maxDescriptionLength'] = intval( $caption_desc_length );
				}
			}
			return $options;
		}

		/**
		 * Add mobile class replacements for common fields backed by container classes.
		 *
		 * @param array      $options    Mobile client options.
		 * @param FooGallery $_gallery   Gallery instance (unused).
		 * @param array      $_attributes Gallery container attributes (unused).
		 *
		 * @return array
		 */
		function add_common_mobile_data_options( $options, $_gallery, $_attributes ) {
			if ( ! foogallery_current_gallery_check_template_has_supported_feature( 'common_fields_support' ) ) {
				return $options;
			}

			// Keep the mobile caption mode at the top level so it does not get
			// shallow-merged into the desktop item options.
			if ( ! empty( foogallery_get_mobile_caption_sources() ) ) {
				$options['mobileCaptions'] = true;
			}

			$desktop_type = foogallery_gallery_template_setting( 'hover_effect_type', '' );
			$desktop_type = is_scalar( $desktop_type ) ? (string) $desktop_type : '';
			$mobile_type  = $this->get_common_mobile_hover_type( $desktop_type );

			$class_tokens = function ( $value ) {
				if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
					return array();
				}
				$tokens = preg_split( '/\s+/', trim( (string) $value ) );
				return is_array( $tokens ) ? $tokens : array();
			};

			$remove_classes = $this->get_common_hover_classes( $desktop_type );
			$add_classes    = $this->get_common_hover_classes( (string) $mobile_type, true );
			if ( empty( array_diff( $remove_classes, $add_classes ) ) && empty( array_diff( $add_classes, $remove_classes ) ) ) {
				return $options;
			}
			$desktop_classes = $remove_classes;
			$remove_classes  = array_diff( $desktop_classes, $add_classes );
			$add_classes     = array_diff( $add_classes, $desktop_classes );
			if ( 'preset' === $desktop_type || 'preset' === $mobile_type ) {
				$preset_sizes   = array( 'fg-preset-smallest', 'fg-preset-small', 'fg-preset-medium', 'fg-preset-large' );
				$remove_classes = array_merge( $preset_sizes, $remove_classes );
				$add_classes    = array_merge( $add_classes, array_intersect( $this->get_common_hover_classes( $mobile_type, true ), $preset_sizes ) );
			}

			$existing_responsive = isset( $options['responsiveClasses'] ) && is_array( $options['responsiveClasses'] ) ? $options['responsiveClasses'] : array();
			$remove_classes       = array_values( array_unique( array_merge( $remove_classes, $class_tokens( $existing_responsive['remove'] ?? '' ) ) ) );
			$add_classes          = array_values( array_unique( array_merge( $add_classes, $class_tokens( $existing_responsive['add'] ?? '' ) ) ) );
			$options['responsiveClasses'] = array( 'remove' => implode( ' ', $remove_classes ), 'add' => implode( ' ', $add_classes ) );

			return $options;
		}

		function add_common_fields_attributes($attributes, $gallery) {
			//check the template supports common fields
			if ( foogallery_current_gallery_check_template_has_supported_feature('common_fields_support' ) ) {
				$captions_limit_length = foogallery_gallery_template_setting( 'captions_limit_length', 'clamp' );

				if ( 'clamp' === $captions_limit_length ) {
					$caption_title_clamp = intval( foogallery_gallery_template_setting( 'caption_title_clamp', '0' ) );
					$caption_desc_clamp = intval( foogallery_gallery_template_setting( 'caption_desc_clamp', '0' ) );
				} else {
                    $caption_title_clamp = $caption_desc_clamp = 0;
				}
                $style = "--fg-title-line-clamp: {$caption_title_clamp}; --fg-description-line-clamp: {$caption_desc_clamp};";
                if ( empty( $attributes['style'] ) ) {
                    $attributes['style'] = $style;
                } else {
                    $attributes['style'] .= $style;
                }
			}

			return $attributes;
		}

		/**
		 * Add responsive CSS for mobile-friendly common fields.
		 *
		 * @param array      $css      CSS rules.
		 * @param FooGallery $gallery  Gallery instance.
		 * @param string     $_template Template slug (unused).
		 *
		 * @return array
		 */
		function add_common_mobile_css( $css, $gallery, $_template ) {
			if ( ! foogallery_current_gallery_check_template_has_supported_feature( 'common_fields_support' ) ) {
				return $css;
			}

			$id = $gallery->container_id();

			if ( 'clamp' === foogallery_gallery_template_setting( 'captions_limit_length', 'clamp' ) ) {
				$mobile_title_clamp = foogallery_gallery_template_mobile_setting( 'mobile_caption_title_clamp', null );
				if ( is_scalar( $mobile_title_clamp ) && is_numeric( trim( (string) $mobile_title_clamp ) ) ) {
					$mobile_title_clamp = max( 0, intval( $mobile_title_clamp ) );
					$mobile_rule        = '#' . $id . ' { --fg-title-line-clamp: ' . $mobile_title_clamp . ' !important; }';
					$css[]              = '@media only screen and (max-width: ' . foogallery_get_mobile_size() . 'px) { ' . $mobile_rule . ' }';
					$css[]              = '.foogallery-preview-wrapper.viewport-mobile ' . $mobile_rule;
				}

				$mobile_desc_clamp = foogallery_gallery_template_mobile_setting( 'mobile_caption_desc_clamp', null );
				if ( is_scalar( $mobile_desc_clamp ) && is_numeric( trim( (string) $mobile_desc_clamp ) ) ) {
					$mobile_desc_clamp = max( 0, intval( $mobile_desc_clamp ) );
					$mobile_rule       = '#' . $id . ' { --fg-description-line-clamp: ' . $mobile_desc_clamp . ' !important; }';
					$css[]             = '@media only screen and (max-width: ' . foogallery_get_mobile_size() . 'px) { ' . $mobile_rule . ' }';
					$css[]             = '.foogallery-preview-wrapper.viewport-mobile ' . $mobile_rule;
				}
			}

			$desktop_hover_type = foogallery_gallery_template_setting( 'hover_effect_type', '' );
			$mobile_hover_type  = $this->get_common_mobile_hover_type( $desktop_hover_type );
			$desktop_icon_size  = foogallery_gallery_template_setting( 'hover_effect_icon_size', '0' );
			$desktop_icon_size  = is_scalar( $desktop_icon_size ) ? $desktop_icon_size : '0';
			$common_fields      = $this->get_common_fields_by_id();
			$icon_choices       = $this->get_common_field_choices( 'hover_effect_icon_size' );
			$inherit_value      = '__foogallery_mobile_inherit__';
			$mobile_icon_size   = foogallery_gallery_template_mobile_setting( 'mobile_hover_effect_icon_size', $inherit_value );
			if ( $inherit_value === $mobile_icon_size || null === $mobile_icon_size || ! is_scalar( $mobile_icon_size ) || ( ! empty( $icon_choices ) && ! array_key_exists( (string) $mobile_icon_size, $icon_choices ) ) ) {
				$mobile_icon_size = $desktop_icon_size;
			}
			if ( isset( $common_fields['hover_effect_icon_size'] ) && 'normal' === (string) $mobile_hover_type && is_scalar( $mobile_icon_size ) && trim( (string) $mobile_icon_size ) !== trim( (string) $desktop_icon_size ) ) {
				$mobile_icon_size = trim( (string) $mobile_icon_size );
				if ( '' === $mobile_icon_size && is_numeric( $desktop_icon_size ) && intval( $desktop_icon_size ) > 0 ) {
					$mobile_icon_size = '32';
				}
				if ( is_numeric( $mobile_icon_size ) && intval( $mobile_icon_size ) > 0 ) {
					$mobile_icon_size = max( 1, min( 96, intval( $mobile_icon_size ) ) );
					$mobile_rule      = '#' . $id . ' { --fg-icon-size: ' . $mobile_icon_size . 'px !important; }';
					$css[]            = '@media only screen and (max-width: ' . foogallery_get_mobile_size() . 'px) { ' . $mobile_rule . ' }';
					$css[]            = '.foogallery-preview-wrapper.viewport-mobile ' . $mobile_rule;
				}
			}

			$mobile_alignment = foogallery_gallery_template_mobile_setting( 'mobile_caption_alignment', null );
			$alignments       = array(
				''       => array( 'center', 'center', 'initial' ),
				'fg-c-l' => array( 'left', 'flex-start', 'initial' ),
				'fg-c-c' => array( 'center', 'center', 'initial' ),
				'fg-c-r' => array( 'right', 'flex-end', 'initial' ),
				'fg-c-j' => array( 'justify', 'stretch', '1' ),
			);
			if ( is_scalar( $mobile_alignment ) && array_key_exists( (string) $mobile_alignment, $alignments ) ) {
				$alignment      = $alignments[ (string) $mobile_alignment ];
				$mobile_rules   = array(
					'#' . $id . ' .fg-caption { text-align: ' . $alignment[0] . '; }',
					'#' . $id . ' .fg-caption-buttons { justify-content: ' . $alignment[1] . '; }',
					'#' . $id . ' .fg-caption-buttons > a { flex: ' . $alignment[2] . '; }',
				);
				$preview_rules  = array();
				foreach ( $mobile_rules as $mobile_rule ) {
					$preview_rules[] = '.foogallery-preview-wrapper.viewport-mobile ' . $mobile_rule;
				}
				$css[] = '@media only screen and (max-width: ' . foogallery_get_mobile_size() . 'px) { ' . implode( ' ', $mobile_rules ) . ' }';
				$css[] = implode( ' ', $preview_rules );
			}

			return $css;
		}

		/**
		 * Build up the gallery data attributes for the common fields
		 *
		 * @param $attributes array
		 * @param $gallery FooGallery
		 *
		 * @return array
		 */
		function add_common_fields_data_attribute( $attributes, $gallery ) {
			//check the template supports common fields
			if ( is_admin() && foogallery_current_gallery_check_template_has_supported_feature('common_fields_support' ) ) {
				$attributes['data-fg-common-fields'] = true;
			}

			$icon_size = intval( foogallery_gallery_template_setting( 'hover_effect_icon_size', '0' ) );
			if ( $icon_size > 0 ) {
				$style = "--fg-icon-size: {$icon_size}px;";
				if ( empty($attributes['style']) ) {
					$attributes['style'] = $style;
				} else {
					$attributes['style'] .= $style;
				}
			}

			return $attributes;
		}

        /***
         * Check if we have a value from PRO and change it if PRO is no longer active
         * @param $value
         * @param $field
         * @param $gallery
         * @param $template
         *
         * @return string
         */
        function check_downgrade_values($value, $field, $gallery, $template) {

            if ( isset( $field['type'] ) ) {
                if ( 'hover_effect_preset' === $field['id'] || 'loaded_effect' === $field['type'] ) {
                    if ( !array_key_exists( $value, $field['choices'] ) ) {
                        $value = $field['default'];
                    }
                }
            }

            return $value;
        }

		/**
		 * Override settings from older versions (pre v1.9.13)
		 *
		 * @param $settings
		 * @param $gallery_template
		 * @param $foogallery
		 *
		 * @return array
		 */
		function override_settings_for_older_versions( $settings, $gallery_template, $foogallery ) {
			if ( is_array( $settings ) ) {
				if ( ! array_key_exists( $gallery_template . '_hover_effect_type', $settings ) ) {
					//we have no hover effect type

					if ( array_key_exists( $gallery_template . '_hover_effect_preset', $settings ) ) {
						$hover_effect_preset = $settings[ $gallery_template . '_hover_effect_preset' ];

						if ( 'fg-custom' === $hover_effect_preset ) {
							$settings[ $gallery_template . '_hover_effect_type' ] = 'normal';
						} else if ( '' === $hover_effect_preset ) {
							$settings[ $gallery_template . '_hover_effect_type' ] = 'none';
						} else if ( strpos( $hover_effect_preset, 'fg-preset' ) !== false ) {
							$settings[ $gallery_template . '_hover_effect_type' ] = 'preset';
						}
					} else {
						//no hover effect type or hover effect preset set
						$settings[ $gallery_template . '_hover_effect_type' ] = 'normal';
					}
				}
			}

			return $settings;
		}
	}
}
