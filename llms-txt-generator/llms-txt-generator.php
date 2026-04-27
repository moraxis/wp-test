<?php
/**
 * Plugin Name:       LLMs.txt Generator
 * Description:       A tool to generate llms.txt files by scraping titles and meta descriptions from a list of URLs.
 * Version:           1.0.1
 * Author:            Nikola Knezhevich
 * Author URI:        https://www.theknez.com/
 * License:           GPL-2.0+
 * Text Domain:       llms-txt-generator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'LLMSTXT_GENERATOR_VERSION', '1.0.1' );

// Disable public WordPress plugin updates for this custom plugin
function llmstxt_generator_disable_updates( $transient ) {
	if ( isset( $transient->response['llms-txt-generator/llms-txt-generator.php'] ) ) {
		unset( $transient->response['llms-txt-generator/llms-txt-generator.php'] );
	}
	return $transient;
}
add_filter( 'site_transient_update_plugins', 'llmstxt_generator_disable_updates' );
define( 'LLMSTXT_GENERATOR_URL', plugin_dir_url( __FILE__ ) );
define( 'LLMSTXT_GENERATOR_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Enqueue scripts and styles for the frontend
 */
function llmstxt_generator_enqueue_scripts() {
	global $post;

	// Only enqueue if the shortcode is present on the page.
	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'llmstxt_generator' ) ) {
		// Enqueue CodeMirror
		wp_enqueue_style( 'codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css', array(), '5.65.13' );
		wp_enqueue_script( 'codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js', array(), '5.65.13', true );
		wp_enqueue_script( 'codemirror-markdown', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/markdown/markdown.min.js', array( 'codemirror' ), '5.65.13', true );

		// Enqueue our custom styles and scripts
		wp_enqueue_style( 'llmstxt-generator-style', LLMSTXT_GENERATOR_URL . 'assets/css/style.css', array(), LLMSTXT_GENERATOR_VERSION );
		wp_enqueue_script( 'llmstxt-generator-script', LLMSTXT_GENERATOR_URL . 'assets/js/script.js', array( 'jquery', 'codemirror' ), LLMSTXT_GENERATOR_VERSION, true );

		// Localize script for AJAX
		wp_localize_script( 'llmstxt-generator-script', 'llmstxtGenerator', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'llmstxt_generator_nonce' ),
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'llmstxt_generator_enqueue_scripts' );

/**
 * Register the shortcode
 */
function llmstxt_generator_shortcode() {
	ob_start();
	include LLMSTXT_GENERATOR_PATH . 'includes/frontend-template.php';
	return ob_get_clean();
}
add_shortcode( 'llmstxt_generator', 'llmstxt_generator_shortcode' );

// Include AJAX handlers
require_once LLMSTXT_GENERATOR_PATH . 'includes/ajax-handlers.php';
