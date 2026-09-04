<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Settings helper with sanitized internal values
/**
 * Created by bradvin
 * Date: 28/04/2017
 *
 */
if ( ! class_exists( 'FooGallery_Admin_Gallery_MetaBox_Settings_Helper' ) ) {

	class FooGallery_Admin_Gallery_MetaBox_Settings_Helper {

		
        /**
         * @var FooGallery
         */
        private $gallery;

        /**
         * @var bool
         */
        private $hide_help;

        /**
         * @var bool
         */
        private $hide_promo;

        /**
         * @var array
         */
        public $gallery_templates;

        /**
         * @var string
         */
        private $current_gallery_template;

		/**
		 * FooGallery_Admin_Gallery_MetaBox_Settings_Helper constructor.
		 * @param $gallery FooGallery
		 */
		function __construct($gallery) {
			$this->gallery = $gallery;
			$this->hide_help = 'on' == foogallery_get_setting( 'hide_gallery_template_help' );
			$this->hide_promo = 'on' == foogallery_get_setting( 'pro_promo_disabled' );

			$this->gallery_templates = foogallery_gallery_templates();

			$this->current_gallery_template = foogallery_default_gallery_template();
			if ( ! empty( $this->gallery->gallery_template ) ) {
				$this->current_gallery_template = $this->gallery->gallery_template;
			}
		}

		/**
		 * Render gallery template settings tabs.
		 *
		 * @param array $template The gallery template configuration.
		 * @param array $sections The sections within the template.
		 *
		 * @return void
		 */
		private function render_gallery_template_settings_tabs( $template, $sections ) {
			$tab_active = 'foogallery-tab-active';
			foreach ( $sections as $section_slug => $section ) {
				$subsection_active = '';
			    //if there are no fields then set the slug to the first subsection
				if ( isset( $section['subsections'] ) && count( $section['fields'] ) === 0 ) {
				    foreach ( $section['subsections'] as $subsection_slug => $subsection ) {
					    $section_slug = $subsection_slug;
					    $subsection_active = 'foogallery-tab-active';
					    break;
                    }
				}
			    ?>
				<div class="foogallery-vertical-tab <?php echo $tab_active; ?>"
					 data-name="<?php echo $template['slug']; ?>-<?php echo $section_slug; ?>">
					<span class="dashicons <?php echo $section['icon_class']; ?>"></span>
					<span class="foogallery-tab-text"><?php echo $section['name']; ?></span>
                    <?php
                    if ( isset( $section['subsections'] ) ) { ?>
                        <div class="foogallery-vertical-child-tabs">
                        <?php foreach ( $section['subsections'] as $subsection_slug => $subsection ) { ?>
                            <div class="foogallery-vertical-child-tab <?php echo $subsection_active; ?>" data-name="<?php echo esc_attr( $template['slug'] . '-' . $subsection_slug ); ?>">
                                <span class="foogallery-tab-text"><?php echo esc_html( $subsection['name'] ); ?></span>
                            </div>
                        <?php
	                        $subsection_active = '';
                            } ?>
                        </div>
                    <?php } ?>
				</div>
				<?php
				$tab_active = '';
			}
		}

		/**
		 * Render gallery template settings tab contents.
		 *
		 * @param array  $template     The gallery template configuration.
		 * @param array  $sections     The sections within the template.
		 * @param string $tab_active   The active tab class.
		 *
		 * @return void
		 */
		private function render_gallery_template_settings_tab_contents( $template, $sections, $tab_active = 'foogallery-tab-active' ) {
			foreach ( $sections as $section_slug => $section ) {
				$subsection_active = '';

				//if we are on an active section, then do the check for subsections
				if ( $tab_active === 'foogallery-tab-active' ) {
					//if there are no fields then set the slug to the first subsection
					if ( isset( $section['subsections'] ) && count( $section['fields'] ) === 0 ) {
						foreach ( $section['subsections'] as $subsection_slug => $subsection ) {
							$subsection_active = 'foogallery-tab-active';
							break;
						}
					}
				}

				?>
				<div class="foogallery-tab-content <?php echo $tab_active; ?>"
					 data-name="<?php echo $template['slug']; ?>-<?php echo $section_slug; ?>">
					<?php $this->render_gallery_template_settings_tab_contents_fields( $template, $section ); ?>
				</div>
				<?php
                if ( isset( $section['subsections'] ) ) {
                    $this->render_gallery_template_settings_tab_contents( $template, $section['subsections'], $subsection_active );
	                $subsection_active = '';
                }
				$tab_active = '';
			}
		}

		/**
		 * Render gallery template settings tab contents fields.
		 *
		 * @param array $template The gallery template configuration.
		 * @param array $section  The section within the template.
		 *
		 * @return void
		 */
		private function render_gallery_template_settings_tab_contents_fields( $template, $section ) {
			$minimize_gallery_settings_help = foogallery_get_setting( 'minimize_gallery_settings_help', false ) === 'on';
			?>
			<table class="foogallery-metabox-settings">
				<tbody>
				<?php
				foreach ( $section['fields'] as $field ) {
					$field_type        = isset( $field['type'] ) ? $field['type'] : 'unknown';
					$field_class       = "foogallery_template_field foogallery_template_field_type-{$field_type} foogallery_template_field_id-{$field['id']} foogallery_template_field_template-{$template['slug']} foogallery_template_field_template_id-{$template['slug']}-{$field['id']}";
					$is_mobile_capable    = isset( $field['mobile'] ) && ( true === $field['mobile'] || is_array( $field['mobile'] ) );
					$mobile_field         = false;
					$mobile_mode          = null;
					$mobile_fallback_mode = null;
					$mobile_fallback_value = null;
					if ( $is_mobile_capable ) {
						$desktop_default = array_key_exists( 'default', $field ) ? $field['default'] : null;
						$desktop_value   = $this->gallery->get_meta( $template['slug'] . '_' . $field['id'], $desktop_default );
						$mobile_field    = foogallery_get_mobile_field_for_template_field( $field, $desktop_value, $template['slug'] );
					}
					$is_mobile_friendly = is_array( $mobile_field );
					$is_mobile_locked   = $is_mobile_capable && ! $is_mobile_friendly && ! $this->hide_promo;
					if ( $is_mobile_friendly ) {
						$mobile_definition     = is_array( $field['mobile'] ) ? $field['mobile'] : array();
						$mobile_has_default    = array_key_exists( 'default', $mobile_definition );
						$mobile_fallback_mode  = $mobile_has_default ? 'default' : 'inherit';
						$mobile_fallback_value = $mobile_has_default ? $mobile_definition['default'] : $desktop_value;
						$mobile_storage_key    = $template['slug'] . '_' . $mobile_field['id'];
						$mobile_is_custom      = is_array( $this->gallery->settings )
							&& array_key_exists( $mobile_storage_key, $this->gallery->settings )
							&& ! foogallery_mobile_setting_values_match( $this->gallery->settings[ $mobile_storage_key ], $mobile_fallback_value );
						$mobile_mode           = $mobile_is_custom ? 'custom' : $mobile_fallback_mode;

						if ( ! $mobile_has_default && ( is_scalar( $mobile_fallback_value ) || null === $mobile_fallback_value ) && isset( $mobile_field['choices'] ) && is_array( $mobile_field['choices'] ) && array_key_exists( $mobile_fallback_value, $mobile_field['choices'] ) ) {
							$choice       = $mobile_field['choices'][ $mobile_fallback_value ];
							$choice_label = is_array( $choice ) && isset( $choice['label'] ) ? $choice['label'] : $choice;
							/* translators: %s: desktop setting choice label. */
							$choice_label = sprintf( __( '%s (Same as desktop)', 'foogallery' ), $choice_label );

							if ( is_array( $choice ) ) {
								$choice['label'] = $choice_label;
								$mobile_field['choices'][ $mobile_fallback_value ] = $choice;
							} else {
								$mobile_field['choices'][ $mobile_fallback_value ] = $choice_label;
							}
						}
					}
					$is_promo           = array_key_exists( 'promo', $field );
					if ( $is_promo ) {
						$field_class .= ' foogallery_template_field_promo';
					}
					if ( array_key_exists( 'class', $field ) ) {
						$field_class .= ' ' . $field['class'];
					}
					if ( $is_mobile_friendly ) {
						$field_class .= ' foogallery_template_field_mobile_friendly';
						$field['row_data'] = isset( $field['row_data'] ) && is_array( $field['row_data'] ) ? $field['row_data'] : array();
						$field['row_data']['data-foogallery-change-selector'] = ':input, range-input';
					}
					if ( $is_mobile_locked ) {
						$field_class .= ' foogallery_template_field_mobile_locked';
					}
					$field_row_data_html = '';
					if ( isset( $field['row_data'] ) ) {
						$field_row_data = array_map( 'esc_attr', $field['row_data'] );
						foreach ( $field_row_data as $field_row_data_name => $field_row_data_value ) {
							$field_row_data_html .= " $field_row_data_name=" . '"' . esc_attr( $field_row_data_value ) . '"';
						}
					}
					?>
					<tr data-foogallery-setting-id="<?php echo esc_attr( $field['id'] ); ?>" data-foogallery-setting-type="<?php echo esc_attr( $field_type ); ?>" class="<?php echo esc_attr( $field_class ); ?>"<?php echo $field_row_data_html; ?><?php echo $is_mobile_friendly ? ' data-foogallery-mobile-friendly="true" data-foogallery-mobile-setting-id="' . esc_attr( $mobile_field['id'] ) . '" data-foogallery-setting-viewport="desktop" data-foogallery-mobile-mode="' . esc_attr( $mobile_mode ) . '" data-foogallery-mobile-fallback-mode="' . esc_attr( $mobile_fallback_mode ) . '" data-foogallery-mobile-fallback-value="' . esc_attr( wp_json_encode( $mobile_fallback_value ) ) . '"' : ''; ?><?php echo $is_mobile_locked ? ' data-foogallery-mobile-locked="true"' : ''; ?>>
						<?php
						if ( 'warning' === $field_type ) {
							?>
							<td colspan="2">
								<div class="foogallery-help foogallery-warning">
									<i class="dashicons dashicons-warning"></i>
									<?php if ( array_key_exists( 'title', $field ) ) { ?>
									<?php echo '<h4>' . $field['title'] . '</h4>'; ?>
									<?php } ?>
									<?php if ( array_key_exists( 'desc', $field ) ) { ?>
									<p><?php echo $field['desc']; ?></p>
									<?php } ?>
								</div>
							</td>
						<?php }                        
                        else if ( 'help' === $field_type ) { ?>
							<td colspan="2">
								<div class="foogallery-help">
									<i class="dashicons dashicons-editor-help"></i>
									<?php if ( array_key_exists( 'title', $field ) ) { ?>
									<?php echo '<h4>' . $field['title'] . '</h4>'; ?>
									<?php } ?>
									<?php if ( array_key_exists( 'desc', $field ) ) { ?>
									<p><?php echo $field['desc']; ?></p>
									<?php } ?>
								</div>
							</td>
						<?php } else if ( 'promo' === $field_type ) { ?>
                            <td colspan="2">
                                <div class="foogallery-promo">
	                                <button class="foogallery-admin-promo-dismiss notice-dismiss"></button>
	                                <?php echo '<strong>' . $field['title'] . '</strong><br /><br />'; ?>
									<?php
                                    echo $field['desc'];
									if ( array_key_exists( 'cta_text', $field ) ) {
									    echo '<a class="button-primary" href="' . $field['cta_link'] . '" target="_blank">' . $field['cta_text'] . '</a>';
                                    }
									if ( array_key_exists( 'cta', $field ) ) {
										foreach ( $field['cta'] as $cta ) {
											$button_class = isset( $cta['class'] ) ? $cta['class'] : 'button-primary';
											echo '<a class="' . $button_class . '" href="' . $cta['link'] . '" target="_blank">' . $cta['text'] . '</a>';
										}
									}
									?>
                                </div>
                            </td>
						<?php } else { 
							$for_attribute  = $this->field_for_attribute( $template, $field );
							$setting_helper = ! empty( $field['alias'] ) ? $field['alias'] : $field['id'];
							if ( !empty( $for_attribute ) ) {
								$for_html = ' for="' . esc_attr( $for_attribute ) . '"';
							} else {
								$for_html = '';
							}
							?>
							<th>
								<label class="foogallery-setting-label" data-setting="<?php echo esc_attr( $setting_helper ); ?>" data-desktop-for="<?php echo esc_attr( $for_attribute ); ?>"<?php if ( $is_mobile_friendly ) { ?> data-mobile-for="<?php echo esc_attr( $this->field_for_attribute( $template, $mobile_field ) ); ?>"<?php } ?><?php echo $for_html; ?>><?php esc_html_e( $field['title'] ); ?></label>
								<?php if ( $is_promo ) { ?>
                                    <span data-balloon-length="large" data-balloon-pos="right" data-balloon="<?php echo esc_attr($field['promo']); ?>"><i class="dashicons dashicons-star-filled"></i></span>
								<?php } ?>
								<?php if ( !empty( $field['desc'] ) && $minimize_gallery_settings_help ) { ?>
									<span class="foogallery-settings-help" data-balloon-length="large" data-balloon-pos="right" data-balloon="<?php echo esc_attr($field['desc']); ?>"><i class="dashicons dashicons-editor-help"></i></span>
								<?php } ?>
								<?php if ( $is_mobile_friendly ) { ?>
									<div class="foogallery-setting-viewport-actions" role="group" aria-label="<?php /* translators: %s: Gallery setting label. */ echo esc_attr( sprintf( __( '%s responsive values', 'foogallery' ), $field['title'] ) ); ?>">
										<button type="button" class="foogallery-setting-viewport-btn active" data-viewport="desktop" aria-label="<?php /* translators: %s: Gallery setting label. */ echo esc_attr( sprintf( __( 'Edit %s desktop and tablet value', 'foogallery' ), $field['title'] ) ); ?>" aria-controls="<?php echo esc_attr( $this->field_control_id( $template, $field, 'desktop' ) ); ?>" aria-pressed="true"><span class="dashicons dashicons-desktop" aria-hidden="true"></span></button>
										<button type="button" class="foogallery-setting-viewport-btn" data-viewport="mobile" aria-label="<?php /* translators: %s: Gallery setting label. */ echo esc_attr( sprintf( __( 'Edit %s mobile value', 'foogallery' ), $field['title'] ) ); ?>" aria-controls="<?php echo esc_attr( $this->field_control_id( $template, $field, 'mobile' ) ); ?>" aria-pressed="false"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span></button>
									</div>
								<?php } elseif ( $is_mobile_locked ) { ?>
									<div class="foogallery-setting-viewport-actions" role="group" aria-label="<?php /* translators: %s: Gallery setting label. */ echo esc_attr( sprintf( __( '%s responsive availability', 'foogallery' ), $field['title'] ) ); ?>">
										<button type="button" class="foogallery-setting-viewport-btn foogallery-setting-mobile-locked-desktop active" data-viewport="desktop" aria-label="<?php /* translators: %s: Gallery setting label. */ echo esc_attr( sprintf( __( 'Use %s desktop and tablet value', 'foogallery' ), $field['title'] ) ); ?>" aria-pressed="true"><span class="dashicons dashicons-desktop" aria-hidden="true"></span></button>
										<button type="button" class="foogallery-setting-mobile-locked" data-viewport="mobile" data-foogallery-mobile-promo-label="<?php echo esc_attr( wp_strip_all_tags( $field['title'] ) ); ?>" aria-label="<?php /* translators: %s: Gallery setting label. */ echo esc_attr( sprintf( __( 'Give %s its own mobile value with PRO Starter', 'foogallery' ), $field['title'] ) ); ?>" aria-haspopup="dialog" aria-expanded="false" aria-pressed="false" data-foogallery-mobile-promo="true" title="<?php /* translators: %s: Gallery setting label. */ echo esc_attr( sprintf( __( 'Give %s its own mobile value with PRO Starter', 'foogallery' ), $field['title'] ) ); ?>"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span><span class="dashicons dashicons-star-filled" aria-hidden="true"></span></button>
									</div>
								<?php } ?>
							</th>
							<td>
								<?php if ( $is_mobile_friendly ) { ?>
									<div id="<?php echo esc_attr( $this->field_control_id( $template, $field, 'desktop' ) ); ?>" class="foogallery-setting-control foogallery-setting-control-desktop" data-viewport="desktop">
										<?php do_action( 'foogallery_render_gallery_template_field', $field, $this->gallery, $template ); ?>
									</div>
									<fieldset id="<?php echo esc_attr( $this->field_control_id( $template, $field, 'mobile' ) ); ?>" class="foogallery-setting-control foogallery-setting-control-mobile" data-viewport="mobile" hidden>
										<?php do_action( 'foogallery_render_gallery_template_field', $mobile_field, $this->gallery, $template ); ?>
									</fieldset>
								<?php } else { ?>
									<?php do_action( 'foogallery_render_gallery_template_field', $field, $this->gallery, $template ); ?>
								<?php } ?>
								<?php if ( ! empty( $field['desc'] ) && ! $minimize_gallery_settings_help ) { ?>
									<p class="foogallery-settings-description"><?php echo esc_html( $field['desc'] ); ?></p>
								<?php } ?>
							</td>
						<?php } ?>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Return the input ID targeted by a field label.
		 *
		 * @param array $template Gallery template definition.
		 * @param array $field    Gallery field definition.
		 *
		 * @return string
		 */
		private function field_for_attribute( $template, $field ) {
			$for_attribute = 'FooGallerySettings_' . $template['slug'] . '_' . $field['id'];
			$field_type = isset( $field['type'] ) ? $field['type'] : 'unknown';
			$radio_types = array( 'radio', 'checkboxlist', 'icon', 'htmlicon' );

			if ( in_array( $field_type, $radio_types, true ) ) {
				$for_attribute .= '0';
			}

			if ( isset( $field['for'] ) ) {
				$for_attribute = 'none' === $field['for'] ? '' : 'FooGallerySettings_' . $template['slug'] . '_' . $field['for'];
			}

			return $for_attribute;
		}

		/**
		 * Return a stable ID for one responsive control wrapper.
		 *
		 * @param array  $template Gallery template definition.
		 * @param array  $field    Gallery field definition.
		 * @param string $viewport Responsive control viewport.
		 *
		 * @return string
		 */
		private function field_control_id( $template, $field, $viewport ) {
			return 'FooGallerySettingControl_' . $template['slug'] . '_' . $field['id'] . '_' . $viewport;
		}

		/**
		 * Render the settings for a specific gallery template.
		 *
		 * @param array $template The gallery template configuration.
		 *
		 * @return void
		 */
		private function render_gallery_template_settings( $template ) {
			$sections = $this->build_model_for_template( $template );
			?>
			<div class="foogallery-settings">
				<div class="foogallery-vertical-tabs">
					<?php $this->render_gallery_template_settings_tabs( $template, $sections ); ?>
				</div>
				<div class="foogallery-tab-contents">
					<?php $this->render_gallery_template_settings_tab_contents( $template, $sections ); ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Public method to render gallery settings for all templates.
		 *
		 * @return void
		 */
		public function render_gallery_settings() {
			foreach ( $this->gallery_templates as $template ) {
				$field_visibility = ( $this->current_gallery_template !== $template['slug'] ) ? 'style="display:none"' : '';
				?><div
				class="foogallery-settings-container foogallery-settings-container-<?php echo $template['slug']; ?>"
				<?php echo $field_visibility; ?>>
				<?php $this->render_gallery_template_settings( $template ); ?>
				</div><?php
			}
		}

		/**
		 * Build the model used to render gallery settings.
		 *
		 * @param array $template Gallery template definition.
		 * @return array
		 */
		private function build_model_for_template( $template ) {
			$fields              = foogallery_get_fields_for_template( $template );
			$registered_sections = foogallery_get_gallery_setting_sections();

			$sections = array();
			foreach ( $fields as $field ) {
				if ( isset( $field['type'] ) && 'help' === $field['type'] && $this->hide_help ) {
					continue;
				}

				if ( isset( $field['type'] ) && 'promo' === $field['type'] && $this->hide_promo ) {
					continue;
				}

				$field          = foogallery_normalize_gallery_setting_field_sections( $field, $registered_sections );
				$section_slug   = $field['section_id'];
				$section_config = isset( $registered_sections[ $section_slug ] ) && is_array( $registered_sections[ $section_slug ] )
					? $registered_sections[ $section_slug ]
					: array();
				$section_name   = isset( $section_config['label'] )
					? $section_config['label']
					: ( isset( $field['section'] ) ? $field['section'] : ucwords( str_replace( array( '-', '_' ), ' ', $section_slug ) ) );
				$section_order  = isset( $section_config['order'] )
					? intval( $section_config['order'] )
					: ( isset( $field['section_order'] ) ? intval( $field['section_order'] ) : 99 );

				if ( ! isset( $sections[ $section_slug ] ) ) {
					$sections[ $section_slug ] = array(
						'name'       => $section_name,
						'icon_class' => foogallery_get_gallery_setting_section_icon( $section_slug, $section_name ),
						'fields'     => array(),
						'order'      => $section_order,
					);
				}

				if ( ! empty( $field['subsection_id'] ) ) {
					$subsection        = $field['subsection_id'];
					$subsection_config = isset( $section_config['subsections'][ $subsection ] ) && is_array( $section_config['subsections'][ $subsection ] )
						? $section_config['subsections'][ $subsection ]
						: array();
					if ( ! isset( $sections[ $section_slug ]['subsections'] ) ) {
						$sections[ $section_slug ]['subsections'] = array();
					}
					if ( ! array_key_exists( $subsection, $sections[ $section_slug ]['subsections'] ) ) {
						$sections[ $section_slug ]['subsections'][ $subsection ] = array(
							'name'   => isset( $subsection_config['label'] )
								? $subsection_config['label']
								: ( isset( $field['subsection'][ $subsection ] ) ? $field['subsection'][ $subsection ] : ucwords( str_replace( array( '-', '_' ), ' ', $subsection ) ) ),
							'fields' => array(),
							'order'  => isset( $subsection_config['order'] ) ? intval( $subsection_config['order'] ) : 99,
						);
					}
					$sections[ $section_slug ]['subsections'][ $subsection ]['fields'][] = $field;
				} else {
					$sections[ $section_slug ]['fields'][] = $field;
				}
			}

			uasort( $sections, array( $this, 'sort_template_sections' ) );
			foreach ( $sections as &$section ) {
				if ( isset( $section['subsections'] ) ) {
					uasort( $section['subsections'], array( $this, 'sort_template_sections' ) );
				}
			}
			unset( $section );

			return $sections;
		}

		/**
		 * Used to sort sections
		 *
		 * @param mixed $a First section.
		 * @param mixed $b Second section.
		 *
		 * @return int
		 */
		public function sort_template_sections( $a, $b ) {
			if ( isset( $a['order'] ) && isset( $b['order'] ) ) {
				if ( $a['order'] === $b['order'] ) {
					return 0;
				}
				return ( $a['order'] < $b['order'] ) ? -1 : 1;
			}

			return 0;
		}
	}
}
