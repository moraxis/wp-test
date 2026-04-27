<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hreflang_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( 'hreflang-tester/v1', '/parse', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'parse_url' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );

		register_rest_route( 'hreflang-tester/v1', '/validate', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'validate_url' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );
	}

	public static function check_permission() {
		return current_user_can( 'edit_posts' );
	}

	public static function parse_url( WP_REST_Request $request ) {
		$url  = $request->get_param( 'url' );
		$type = $request->get_param( 'type' ); // 'page' or 'sitemap'

		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'Invalid URL provided.', array( 'status' => 400 ) );
		}

		$validator = new Hreflang_Validator();

		if ( $type === 'sitemap' ) {
			$result = $validator->parse_sitemap( $url );
		} else {
			$result = $validator->parse_page( $url );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function validate_url( WP_REST_Request $request ) {
		$source_url  = $request->get_param( 'source_url' ); // The page that links to the target
		$target_url  = $request->get_param( 'target_url' ); // The alternate url to validate
		$source_lang = $request->get_param( 'source_lang' ); // Language code of the target page

		if ( empty( $source_url ) || empty( $target_url ) ) {
			return new WP_Error( 'invalid_params', 'Source or target URL missing.', array( 'status' => 400 ) );
		}

		$validator = new Hreflang_Validator();
		$result = $validator->validate_target( $source_url, $target_url, $source_lang );

		return rest_ensure_response( $result );
	}
}

Hreflang_API::init();
