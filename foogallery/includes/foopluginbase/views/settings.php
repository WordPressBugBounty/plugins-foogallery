<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Default settings page used by Foo_Plugin_Base
*/
global $wp_version, $wp_settings_sections, $wp_settings_fields;

//need to make sure are included correctly
if ( !isset($this) || !is_subclass_of( $this, 'Foo_Plugin_Base_v2_4' ) ) {
	throw new Exception("This settings view has not been included correctly!");
}

$tabs = $this->settings()->get_tabs();
$plugin_info = $this->get_plugin_info();
$plugin_slug = $plugin_info['slug'];
$summary = $this->apply_filters( $plugin_slug . '_admin_settings_page_summary', '' );

/**
 * Render Settings API rows, letting untitled fields span both columns.
 *
 * @param string $section Registered section ID.
 */
$render_fields = function ( $section ) use ( $plugin_slug ) {
	global $wp_settings_fields;
	if ( ! isset( $wp_settings_fields[ $plugin_slug ][ $section ] ) ) {
		return;
	}
	foreach ( $wp_settings_fields[ $plugin_slug ][ $section ] as $field ) {
		$has_label = isset( $field['title'] ) && '' !== trim( (string) $field['title'] );
		$class = isset( $field['args']['class'] ) ? $field['args']['class'] : '';
		if ( ! $has_label ) {
			$class = trim( $class . ' foo-settings-full-width' );
		}
		echo '<tr' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
		if ( $has_label ) {
			echo '<th scope="row">';
			if ( ! empty( $field['args']['label_for'] ) ) {
				echo '<label for="' . esc_attr( $field['args']['label_for'] ) . '">' . wp_kses_post( $field['title'] ) . '</label>';
			} else {
				echo wp_kses_post( $field['title'] );
			}
			echo '</th><td>';
		} else {
			echo '<td colspan="2">';
		}
		call_user_func( $field['callback'], $field['args'] );
		echo '</td></tr>';
	}
};

?>
<div class="wrap" id="<?php echo esc_attr( $plugin_slug ); ?>-settings">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<?php
        //only show the settings messages if less than WP3.5
        if (version_compare($wp_version, '3.5') < 0) {
            settings_errors();
        }

	if ( !isset($wp_settings_sections) || !isset($wp_settings_sections[$plugin_slug]) ) {
            return;
	}

		if ( !empty($summary) ) {
			echo esc_html( $summary );
		}
    ?>
	<div id="<?php echo esc_attr( $plugin_slug ); ?>-settings-wrapper">
		<div id="<?php echo esc_attr( $plugin_slug ); ?>-settings-main">
	<form action="options.php" method="post">
		<?php settings_fields($plugin_slug); ?>
                <?php
                if (!empty($tabs)) {
                    //we have tabs - woot!
                ?>
                <div style="float:left;height:16px;width:16px;"><!-- spacer for tabs --></div>
                <h2 class="foo-nav-tabs nav-tab-wrapper">
                <?php
                    //loop through the tabs to render the actual tabs at the top
                    $first = true;
                    foreach ($tabs as $tab) {
                        $class = $first ? "nav-tab nav-tab-active" : "nav-tab";
                        echo "<a href='#" . esc_attr( $tab['id'] ) . "' class='" . esc_attr( $class ) . "'>" . esc_html( $tab['title'] ) . "</a>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							if ( $first ) {
								$first = false;
							}
                    }
                ?>
                </h2>
                <?php
                    //now loop through the tabs to render the content containers
                    $first = true;
                    foreach ($tabs as $tab) {
                        $style = $first ? "" : "style='display:none'";

                        echo "<div class='nav-container' id='" . esc_attr( $tab['id'] ) . "_tab' " . $style . ">"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

						foreach ( (array) $wp_settings_sections[$plugin_slug] as $section ) {
							if (in_array($section['id'], $tab['sections'])) {
								echo "<h3>" . esc_html( $section['title'] ) . "</h3>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								call_user_func($section['callback'], $section);
								if ( !isset($wp_settings_fields) || !isset($wp_settings_fields[$plugin_slug]) || !isset($wp_settings_fields[$plugin_slug][$section['id']]) ) {
									continue;
								}
								echo '<table class="form-table">';
								$render_fields( $section['id'] );
								echo '</table>';
							}
						}

                        echo "</div>";
						if ( $first ) {
							$first = false;
						}
                    }
                ?>
                <?php
                } else {
                    //no tabs so just render the sections
                    foreach ( (array) $wp_settings_sections[ $plugin_slug ] as $section ) {
                        if ( ! empty( $section['before_section'] ) ) {
                            echo wp_kses_post( ! empty( $section['section_class'] ) ? sprintf( $section['before_section'], esc_attr( $section['section_class'] ) ) : $section['before_section'] );
                        }
                        if ( $section['title'] ) {
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                        }
                        if ( $section['callback'] ) {
                            call_user_func( $section['callback'], $section );
                        }
                        if ( isset( $wp_settings_fields[ $plugin_slug ][ $section['id'] ] ) ) {
                            echo '<table class="form-table" role="presentation">';
                            $render_fields( $section['id'] );
                            echo '</table>';
                        }
                        if ( ! empty( $section['after_section'] ) ) {
                            echo wp_kses_post( $section['after_section'] );
                        }
                    }
                }
                ?>
		<p class="submit">
					<input name="submit" class="button-primary" type="submit"
						   value="<?php esc_attr_e( 'Save Changes', $plugin_slug ); ?>"/>
					<input name="<?php echo esc_attr( $plugin_slug ); ?>[reset-defaults]"
						   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to restore all settings back to their default values?', $plugin_slug ); ?>');"
						   class="button-secondary" type="submit"
						   value="<?php esc_attr_e( 'Restore Defaults', $plugin_slug ); ?>"/>
			<?php do_action($plugin_slug . '_admin_settings_buttons') ?>
		</p>
	</form>
		</div>
		<div id="<?php echo esc_attr( $plugin_slug ); ?>-settings-sidebar" class="postbox-container">
			<?php do_action($plugin_slug . '_admin_settings_sidebar'); ?>
		</div>
	</div>
</div>
