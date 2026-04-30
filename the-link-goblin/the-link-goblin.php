<?php
/**
 * Plugin Name: The Link Goblin
 * Plugin URI: https://example.com
 * Description: Private WordPress plugin that uses the DeepSeek API to generate internal linking suggestions via a centralized dashboard and post edit screens.
 * Version: 1.0.1
 * Author: Nikola Knezhevich
 * Author URI: https://example.com
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'THE_LINK_GOBLIN_VERSION', '1.0.1' );
define( 'THE_LINK_GOBLIN_FILE', __FILE__ );
define( 'THE_LINK_GOBLIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'THE_LINK_GOBLIN_URL', plugin_dir_url( __FILE__ ) );

// Require classes
require_once THE_LINK_GOBLIN_DIR . 'includes/class-activation.php';
require_once THE_LINK_GOBLIN_DIR . 'includes/class-settings.php';
require_once THE_LINK_GOBLIN_DIR . 'includes/class-scanner.php';
require_once THE_LINK_GOBLIN_DIR . 'includes/class-dashboard.php';
require_once THE_LINK_GOBLIN_DIR . 'includes/class-metabox.php';

// Register activation hook
register_activation_hook( __FILE__, array( 'The_Link_Goblin_Activation', 'activate' ) );

/**
 * Prevent erroneous update notifications by disabling WordPress.org repository update checks.
 * This should be done by hooking into the site_transient_update_plugins filter and removing
 * the custom plugin slug from the update response to avoid conflicts with similarly named public plugins.
 */
add_filter( 'site_transient_update_plugins', 'the_link_goblin_disable_update_checks' );
function the_link_goblin_disable_update_checks( $transient ) {
    if ( is_object( $transient ) && isset( $transient->response[ plugin_basename( __FILE__ ) ] ) ) {
        unset( $transient->response[ plugin_basename( __FILE__ ) ] );
    }
    return $transient;
}
