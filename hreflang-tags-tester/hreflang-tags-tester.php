<?php
/**
 * Plugin Name: Hreflang Tags Tester
 * Description: A tool to check if hreflang tags for a page (HTML and HTTP headers), or in XML Sitemaps, are correct.
 * Version: 1.0.0
 * Author: Nikola Knezhevich
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HREFLANG_TESTER_VERSION', '1.0.0' );
define( 'HREFLANG_TESTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'HREFLANG_TESTER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Disable WordPress.org update checks for this custom plugin.
 */
add_filter( 'site_transient_update_plugins', 'hreflang_tester_disable_update_check' );
function hreflang_tester_disable_update_check( $transient ) {
	if ( isset( $transient->response['hreflang-tags-tester/hreflang-tags-tester.php'] ) ) {
		unset( $transient->response['hreflang-tags-tester/hreflang-tags-tester.php'] );
	}
	return $transient;
}

/**
 * Enqueue scripts and styles for the shortcode.
 */
function hreflang_tester_enqueue_assets() {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'hreflang_tester' ) ) {
		wp_enqueue_style(
			'hreflang-tester-style',
			HREFLANG_TESTER_URL . 'assets/style.css',
			array(),
			HREFLANG_TESTER_VERSION
		);

		wp_enqueue_script(
			'hreflang-tester-script',
			HREFLANG_TESTER_URL . 'assets/script.js',
			array( 'jquery' ),
			HREFLANG_TESTER_VERSION,
			true
		);

		wp_localize_script( 'hreflang-tester-script', 'hreflangTesterParams', array(
			'restUrl' => esc_url_raw( rest_url( 'hreflang-tester/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' )
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'hreflang_tester_enqueue_assets' );

/**
 * Register the shortcode.
 */
function hreflang_tester_shortcode() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return '<p>You do not have permission to view this tool.</p>';
    }

    ob_start();
    include HREFLANG_TESTER_DIR . 'templates/frontend.php';
    return ob_get_clean();
}
add_shortcode( 'hreflang_tester', 'hreflang_tester_shortcode' );

// Include backend classes
require_once HREFLANG_TESTER_DIR . 'class-hreflang-validator.php';
require_once HREFLANG_TESTER_DIR . 'class-hreflang-api.php';
