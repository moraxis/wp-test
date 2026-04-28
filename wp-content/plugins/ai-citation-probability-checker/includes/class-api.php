<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Citation_Checker_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ai-citation/v1',
			'/fetch',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'fetch_url' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	public function check_permission() {
		// Publicly accessible, but relies on nonce verification in the frontend
		return true;
	}

	public function fetch_url( WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		// Get visitor IP
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		if ( empty( $ip ) ) {
			return new WP_Error( 'missing_ip', 'Could not determine IP address.', array( 'status' => 400 ) );
		}

		// Rate limiting: 10 requests per hour
		$transient_key = 'ai_citation_rate_' . md5( $ip );
		$requests      = get_transient( $transient_key );

		if ( false === $requests ) {
			$requests = 0;
		}

		if ( $requests >= 10 ) {
			return new WP_Error( 'rate_limit_exceeded', 'You reached your limit, try again in an hour.', array( 'status' => 429 ) );
		}

		set_transient( $transient_key, $requests + 1, HOUR_IN_SECONDS );

		// Fetch the URL securely
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'limit_response_size' => 1048576, // 1MB
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_failed', 'Failed to fetch the URL.', array( 'status' => 500 ) );
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return new WP_Error( 'empty_content', 'The URL returned no content.', array( 'status' => 404 ) );
		}

		$extracted_text = $this->extract_content( $body );

		return rest_ensure_response(
			array(
				'success' => true,
				'text'    => $extracted_text,
			)
		);
	}

	private function extract_content( $html ) {
		// Suppress warnings for malformed HTML
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Remove unwanted elements
		$unwanted_queries = array( '//script', '//style', '//nav', '//header', '//footer', '//aside', '//noscript', '//iframe' );
		foreach ( $unwanted_queries as $query ) {
			$nodes = $xpath->query( $query );
			foreach ( $nodes as $node ) {
				$node->parentNode->removeChild( $node );
			}
		}

		// Try to find main content
		$content_node = null;

		$article_nodes = $xpath->query( '//article' );
		if ( $article_nodes->length > 0 ) {
			$content_node = $article_nodes->item( 0 );
		} else {
			$main_nodes = $xpath->query( '//main' );
			if ( $main_nodes->length > 0 ) {
				$content_node = $main_nodes->item( 0 );
			} else {
				$body_nodes = $xpath->query( '//body' );
				if ( $body_nodes->length > 0 ) {
					$content_node = $body_nodes->item( 0 );
				}
			}
		}

		if ( ! $content_node ) {
			return strip_tags( $html ); // Fallback
		}

		// Basic extraction: preserving some structure
		$text = '';
		$child_nodes = $xpath->query( './/p | .//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6 | .//li', $content_node );

		if ( $child_nodes->length > 0 ) {
			foreach ( $child_nodes as $node ) {
				$node_text = trim( $node->textContent );
				if ( ! empty( $node_text ) ) {
					if ( $node->nodeName === 'li' ) {
						$text .= '- ' . $node_text . "\n";
					} else {
						$text .= $node_text . "\n\n";
					}
				}
			}
		} else {
			// If no structured elements found, just get raw text and clean it up
			$text = $content_node->textContent;
			$text = preg_replace( "/\n\s+/", "\n", $text );
			$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		}

		return trim( $text );
	}
}

new AI_Citation_Checker_API();
