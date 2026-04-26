<?php
/**
 * AJAX Handlers for scraping URLs
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

function llmstxt_generator_scrape_url() {
	// Verify nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'llmstxt_generator_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed.' ) );
	}

	$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';

	if ( empty( $url ) ) {
		wp_send_json_error( array( 'message' => 'No URL provided.' ) );
	}

	// Make the remote request
	$args = array(
		'timeout'     => 15,
		'redirection' => 5,
		'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36', // Generic user agent to avoid basic blocks
	);

	// Use wp_safe_remote_get to prevent Server-Side Request Forgery (SSRF)
	$response = wp_safe_remote_get( $url, $args );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => 'Failed to fetch URL: ' . $response->get_error_message() ) );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code !== 200 ) {
		wp_send_json_error( array( 'message' => 'Failed to fetch URL. HTTP Status: ' . $response_code ) );
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		wp_send_json_error( array( 'message' => 'Empty response from URL.' ) );
	}

	// Parse the HTML body for title and meta description
	// Using regex as a lightweight fallback, though DOMDocument is more robust. We'll try DOMDocument.
	$title = '';
	$description = '';

	// Suppress warnings from malformed HTML
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	// Hack to handle encoding issues and empty bodies safely
	if ( function_exists( 'mb_convert_encoding' ) ) {
		$body = mb_convert_encoding( $body, 'HTML-ENTITIES', 'UTF-8' );
	}

	$loaded = @$dom->loadHTML( $body );
	if ( $loaded ) {
		// Get Title
		$title_nodes = $dom->getElementsByTagName( 'title' );
		if ( $title_nodes->length > 0 ) {
			$title = $title_nodes->item( 0 )->nodeValue;
		}

		// Get Meta Description
		$meta_nodes = $dom->getElementsByTagName( 'meta' );
		foreach ( $meta_nodes as $node ) {
			if ( strtolower( $node->getAttribute( 'name' ) ) === 'description' ) {
				$description = $node->getAttribute( 'content' );
				break;
			}
			// Sometimes it's property="og:description"
			if ( strtolower( $node->getAttribute( 'property' ) ) === 'og:description' && empty( $description ) ) {
				$description = $node->getAttribute( 'content' );
			}
		}
	}
	libxml_clear_errors();

	// Clean up extracted data
	$title = sanitize_text_field( trim( $title ) );
	$description = sanitize_text_field( trim( $description ) );

	// Fallback if title is empty
	if ( empty( $title ) ) {
		$title = $url;
	}

	wp_send_json_success( array(
		'url'         => $url,
		'title'       => $title,
		'description' => $description,
	) );
}

add_action( 'wp_ajax_llmstxt_scrape', 'llmstxt_generator_scrape_url' );
add_action( 'wp_ajax_nopriv_llmstxt_scrape', 'llmstxt_generator_scrape_url' );
