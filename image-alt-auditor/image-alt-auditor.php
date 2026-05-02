<?php
/**
 * Plugin Name: Image Alt Text Auditor
 * Description: A tool to audit a URL for images missing alt attributes or using background images.
 * Version: 1.0.0
 * Author: Nikola Knezhevich
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IMAGE_ALT_AUDITOR_VERSION', '1.0.0' );

// Include required files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-frontend.php';

/**
 * Disable update checks for this custom plugin to prevent conflicts with public plugins.
 */
add_filter( 'site_transient_update_plugins', 'image_alt_auditor_disable_updates' );
function image_alt_auditor_disable_updates( $value ) {
	if ( isset( $value ) && is_object( $value ) ) {
		unset( $value->response[ plugin_basename( __FILE__ ) ] );
	}
	return $value;
}
