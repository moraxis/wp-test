<?php
/**
 * REST API Endpoint registration and handling
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Alt_Auditor_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'image-alt-auditor/v1',
			'/audit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'audit_endpoint' ),
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

	public function check_permission( $request ) {
		// Since this is a public tool, we rely on nonces to deter basic abuse
		// WordPress automatically checks the X-WP-Nonce header for REST requests if a user is logged in
		// However, for non-logged in users we want to allow access but verify our custom nonce
		$nonce = $request->get_header( 'x_wp_nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', 'Invalid nonce. Please refresh the page and try again.', array( 'status' => 403 ) );
		}

		return true;
	}

	public function audit_endpoint( $request ) {
		$url = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', 'No URL provided.', array( 'status' => 400 ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-parser.php';
		$parser = new Image_Alt_Auditor_Parser();

		$result = $parser->audit_url( $url );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $result->get_error_message(),
			) );
		}

		return rest_ensure_response( array(
			'success'  => true,
			'findings' => $result,
		) );
	}
}

new Image_Alt_Auditor_API();
