<?php

/**
 * This file registers the settings
 *
 * @package Code_Snippets
 */

/**
 * Returns 'true' if plugin settings are unified on a multisite installation
 * under the Network Admin settings menu
 *
 * This option is controlled by the "Enable administration menus" setting on the Network Settings menu
 *
 * @return bool
 */
function code_snippets_unified_settings() {
	if ( ! is_multisite() ) {
		return false;
	}

	$menu_perms = get_site_option( 'menu_items', array() );

	return empty( $menu_perms['snippets_settings'] );
}

/**
 * Retrieve the setting values from the database.
 * If a setting does not exist in the database, the default value will be returned.
 *
 * @return array
 */
function code_snippets_get_settings() {
	/* Check if the settings have been cached */
	if ( $settings = wp_cache_get( 'code_snippets_settings' ) ) {
		return $settings;
	}

	/* Begin with the default settings */
	$settings = code_snippets_get_default_settings();

	/* Retrieve saved settings from the database */
	$saved = array_merge(
		code_snippets_unified_settings() ?
			get_site_option( 'code_snippets_settings', array() ) :
			get_option( 'code_snippets_settings', array() ),
		code_snippets_get_current_user_settings()
	);

	/* Replace the default field values with the ones saved in the database */
	if ( function_exists( 'array_replace_recursive' ) ) {

		/* Use the much more efficient array_replace_recursive() function in PHP 5.3 and later */
		$settings = array_replace_recursive( $settings, $saved );
	} else {

		/* Otherwise, do it manually */
		foreach ( $settings as $section => $fields ) {
			foreach ( $fields as $field => $value ) {

				if ( isset( $saved[ $section ][ $field ] ) ) {
					$settings[ $section ][ $field ] = $saved[ $section ][ $field ];
				}
			}
		}
	}

	wp_cache_set( 'code_snippets_settings', $settings );

	return $settings;
}

/**
 * Retrieve an individual setting field value
 *
 * @param string $section The ID of the section the setting belongs to
 * @param string $field The ID of the setting field
 *
 * @return array
 */
function code_snippets_get_setting( $section, $field ) {
	$settings = code_snippets_get_settings();

	return $settings[ $section ][ $field ];
}

/**
 * Retrieve the settings sections
 *
 * @return array
 */
function code_snippets_get_settings_sections() {
	$sections = array(
		'general'            => __( 'General', 'code-snippets' ),
		'description_editor' => __( 'Description Editor', 'code-snippets' ),
		'editor'             => __( 'Code Editor', 'code-snippets' ),
	);

	return apply_filters( 'code_snippets_settings_sections', $sections );
}

/**
 * Register settings sections, fields, etc
 */
function code_snippets_register_settings() {
	if ( code_snippets_unified_settings() ) {

		if ( ! get_site_option( 'code_snippets_settings', false ) ) {
			add_site_option( 'code_snippets_settings', code_snippets_get_default_settings() );
		}
	} else {

		if ( ! get_option( 'code_snippets_settings', false ) ) {
			add_option( 'code_snippets_settings', code_snippets_get_default_settings() );
		}
	}

	/* Register the setting */
	register_setting( 'code-snippets', 'code_snippets_settings', 'code_snippets_settings_validate' );

	/* Register settings sections */
	foreach ( code_snippets_get_settings_sections() as $section_id => $section_name ) {
		add_settings_section(
			'code-snippets-' . $section_id,
			$section_name,
			'__return_empty_string',
			'code-snippets'
		);
	}

	/* Register settings fields */
	foreach ( code_snippets_get_settings_fields() as $section_id => $fields ) {
		foreach ( $fields as $field_id => $field ) {
			$atts            = $field;
			$atts['id']      = $field_id;
			$atts['section'] = $section_id;

			add_settings_field(
				'code_snippets_' . $field_id,
				$field['name'],
				"code_snippets_{$field['type']}_field",
				'code-snippets',
				'code-snippets-' . $section_id,
				$atts
			);
		}
	}

	/* Add editor preview as a field */
	add_settings_field(
		'code_snippets_editor_preview',
		__( 'Editor Preview', 'code-snippets' ),
		'code_snippets_settings_editor_preview',
		'code-snippets',
		'code-snippets-editor'
	);
}

add_action( 'admin_init', 'code_snippets_register_settings' );

/**
 * Validate the settings
 *
 * @param array $input The sent settings
 *
 * @return array        The validated settings
 */
function code_snippets_settings_validate( array $input ) {
	$settings        = code_snippets_get_settings();
	$settings_fields = code_snippets_get_settings_fields();

	// Don't directly loop through $input as it does not include as deselected checkboxes
	foreach ( $settings_fields as $section_id => $fields ) {

		// Loop through fields
		foreach ( $fields as $field_id => $field ) {

			switch ( $field['type'] ) {

				case 'checkbox':
					$settings[ $section_id ][ $field_id ] =
						isset( $input[ $section_id ][ $field_id ] ) && 'on' === $input[ $section_id ][ $field_id ];
					break;

				case 'number':
					$settings[ $section_id ][ $field_id ] = absint( $input[ $section_id ][ $field_id ] );
					break;

				case 'ace_theme_select':
					$available_themes = code_snippets_get_available_themes();
					$selected_theme   = $input[ $section_id ][ $field_id ];

					if ( in_array( $selected_theme, $available_themes, true ) ) {
						$settings[ $section_id ][ $field_id ] = $selected_theme;
					}

					break;

				case 'ace_font_select':
					$available_fonts = code_snippets_get_available_fonts();
					$selected_font   = $input[ $section_id ][ $field_id ];

					if ( in_array( $selected_font, $available_fonts, true ) ) {
						$settings[ $section_id ][ $field_id ] = $selected_font;
					}

					break;

				default:
					break;

			}
		}
	}

	/* Add an updated message */
	add_settings_error(
		'code-snippets-settings-notices',
		'settings-saved',
		__( 'Settings saved.', 'code-snippets' ),
		'updated'
	);

	return $settings;
}


/**
 * Get saved user settings
 *
 * @return array
 */
function code_snippets_get_current_user_settings(): array {
	$saved = get_user_meta( get_current_user_id(), 'code_snippets_settings' );
	if ( empty( $saved ) ) {
		return array();
	}

	return $saved[0];
}

/**
 * Hooks to options.php and separates user based settings to save them to user meta
 */
function code_snippets_separate_settings() {
	if ( empty( get_user_meta( get_current_user_id(), 'code_snippets_settings' ) ) ) {
		add_user_meta( get_current_user_id(), 'code_snippets_settings', '' );
	}
	add_filter( "pre_update_option_code_snippets_settings", 'remove_user_settings', 10, 1 );
	function remove_user_settings( $new_value ): array {
		$user_settings   = array();
		$global_settings = array();
		foreach ( $new_value as $field => $value ) {
			if ( $field === 'editor' ) {
				$user_settings[ $field ] = $value;
				continue;
			}
			$global_settings[ $field ] = $value;
		}
		update_user_meta( get_current_user_id(), 'code_snippets_settings', $user_settings );

		return $global_settings;
	}

}

code_snippets_separate_settings();