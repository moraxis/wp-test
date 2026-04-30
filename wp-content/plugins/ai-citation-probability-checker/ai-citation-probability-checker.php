<?php
/**
 * Plugin Name: AI Citation Probability Checker
 * Description: Analyzes the semantic structure of text and scores how easily AI can extract and cite facts from it.
 * Version: 1.0.0
 * Author: Nikola Knezhevich
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'AI_CITATION_CHECKER_VERSION', '1.0.0' );
define( 'AI_CITATION_CHECKER_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_CITATION_CHECKER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Disable WordPress.org repository update checks for this custom plugin.
 */
add_filter( 'site_transient_update_plugins', 'ai_citation_checker_disable_update_checks' );
function ai_citation_checker_disable_update_checks( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	if ( isset( $transient->response['ai-citation-probability-checker/ai-citation-probability-checker.php'] ) ) {
		unset( $transient->response['ai-citation-probability-checker/ai-citation-probability-checker.php'] );
	}
	return $transient;
}

// Include the API class
require_once AI_CITATION_CHECKER_DIR . 'includes/class-api.php';
require_once AI_CITATION_CHECKER_DIR . 'includes/class-frontend.php';
