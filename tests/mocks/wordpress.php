<?php
/**
 * Mock WordPress environment for testing.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$wp_actions = [];
$wp_shortcodes = [];
$wp_rest_routes = [];
$wp_styles = [];
$wp_scripts = [];
$wp_localized_data = [];
$wp_safe_remote_get_response = null;

function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
    global $wp_actions;
    $wp_actions[$tag][] = $callback;
}

function add_shortcode($tag, $callback) {
    global $wp_shortcodes;
    $wp_shortcodes[$tag] = $callback;
}

function wp_register_style($handle, $src, $deps = [], $ver = false, $media = 'all') {
    global $wp_styles;
    $wp_styles[$handle] = $src;
}

function wp_register_script($handle, $src, $deps = [], $ver = false, $in_footer = false) {
    global $wp_scripts;
    $wp_scripts[$handle] = $src;
}

function wp_localize_script($handle, $object_name, $l10n) {
    global $wp_localized_data;
    $wp_localized_data[$handle][$object_name] = $l10n;
}

function plugins_url($path = '', $plugin = '') {
    return 'http://example.com/wp-content/plugins/' . ltrim($path, '/');
}

function rest_url($path = '', $scheme = 'rest') {
    return 'http://example.com/wp-json/' . ltrim($path, '/');
}

function wp_create_nonce($action = -1) {
    return 'mock-nonce-' . $action;
}

function wp_enqueue_style($handle) {}
function wp_enqueue_script($handle) {}

function register_rest_route($namespace, $route, $args = []) {
    global $wp_rest_routes;
    $wp_rest_routes[$namespace . $route] = $args;
}

function wp_safe_remote_get($url, $args = []) {
    global $wp_safe_remote_get_response;
    return $wp_safe_remote_get_response;
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

function wp_remote_retrieve_response_code($response) {
    return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body($response) {
    return isset($response['body']) ? $response['body'] : '';
}

function rest_ensure_response($response) {
    return $response;
}

function esc_url_raw($url) {
    return $url;
}

function __return_true() {
    return true;
}

class WP_Error {
    public $errors = [];
    public $error_data = [];

    public function __construct($code = '', $message = '', $data = '') {
        if (empty($code)) return;
        $this->errors[$code][] = $message;
        if (!empty($data)) $this->error_data[$code] = $data;
    }

    public function get_error_message($code = '') {
        if (empty($code)) {
            $code = key($this->errors);
        }
        return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
    }
}
