<?php
/**
 * WordPress Mocks for Testing
 */

define('ABSPATH', true);

$mock_actions = array();
function add_action($tag, $callback) {
    global $mock_actions;
    $mock_actions[$tag][] = $callback;
}

$mock_filters = array();
function add_filter($tag, $callback) {
    global $mock_filters;
    $mock_filters[$tag][] = $callback;
}

function do_action($tag) {
    global $mock_actions;
    if (isset($mock_actions[$tag])) {
        foreach ($mock_actions[$tag] as $callback) {
            call_user_func($callback);
        }
    }
}
function add_shortcode($tag, $callback) {}
function wp_register_style($handle, $src, $deps = array(), $ver = false, $media = 'all') {}
function wp_register_script($handle, $src, $deps = array(), $ver = false, $in_footer = false) {}
function wp_localize_script($handle, $object_name, $l10n) {}
function plugins_url($path = '', $plugin = '') { return $path; }
function rest_url($path = '') { return 'http://example.com/wp-json/' . $path; }
function wp_create_nonce($action = -1) { return 'mock_nonce'; }
function esc_url_raw($url) { return $url; }
function register_rest_route($namespace, $route, $args = array()) {
    global $mock_rest_routes;
    $mock_rest_routes[$namespace . $route] = $args;
}
function rest_ensure_response($response) { return $response; }

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code = '', $message = '', $data = '') {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_message() { return $this->message; }
}

function is_wp_error($thing) { return $thing instanceof WP_Error; }

$current_user_can_return = true;
function current_user_can($capability) {
    global $current_user_can_return;
    return $current_user_can_return;
}

// Ensure we handle plugin_dir_path and plugin_dir_url needed by the new plugin
function plugin_dir_path($file) { return trailingslashit(dirname($file)); }
function plugin_dir_url($file) { return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function has_shortcode($content, $tag) { return strpos($content, '[' . $tag . ']') !== false; }
function trailingslashit($string) { return rtrim($string, '/\\') . '/'; }

$mock_remote_get_responses = array();
function wp_safe_remote_get($url, $args = array()) {
    global $mock_remote_get_args, $mock_remote_get_responses;
    $mock_remote_get_args = $args;

    if (strpos($url, 'error') !== false) {
        return new WP_Error('fetch_error', 'Mock error');
    }

    if (isset($mock_remote_get_responses[$url])) {
        return $mock_remote_get_responses[$url];
    }

    return array(
        'body' => 'mock content',
        'response' => array('code' => 200),
        'headers' => array()
    );
}

function wp_remote_retrieve_response_code($response) {
    return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body($response) {
    return isset($response['body']) ? $response['body'] : '';
}

function wp_remote_retrieve_headers($response) {
    return isset($response['headers']) ? $response['headers'] : array();
}

function wp_remote_retrieve_header($response, $header) {
    if (isset($response['headers']) && is_array($response['headers']) && isset($response['headers'][strtolower($header)])) {
        return $response['headers'][strtolower($header)];
    }
    return '';
}

function wp_http_validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function wp_parse_url($url) {
    return parse_url($url);
}

// Added mocks for enqueue functions used in the new plugin
function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {}
function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {}

class WP_REST_Request {
    public $params;
    public function __construct($params = array()) {
        $this->params = $params;
    }
    public function get_param($key) {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}
