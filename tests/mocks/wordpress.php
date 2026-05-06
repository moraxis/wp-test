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
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null) {}
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '', $position = null) {}
function esc_url_raw($url) { return $url; }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }

$mock_settings = array();
function register_setting($option_group, $option_name, $args = array()) {
    global $mock_settings;
    if (is_string($args)) {
        $args = array('sanitize_callback' => $args);
    }
    $mock_settings[$option_name] = $args;
}

function add_settings_section($id, $title, $callback, $page) {}
function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array()) {}

$mock_options = array();
function get_option($option, $default = false) {
    global $mock_options;
    return isset($mock_options[$option]) ? $mock_options[$option] : $default;
}

function update_option($option, $value, $autoload = null) {
    global $mock_options;
    $mock_options[$option] = $value;
    return true;
}

function sanitize_text_field($str) {
    return strip_tags(trim($str));
}

function selected($selected, $current = true, $echo = true) {
    $result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
    if ($echo) {
        echo $result;
    }
    return $result;
}

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

function wp_safe_remote_get($url, $args = array()) {
    global $mock_remote_get_args;
    $mock_remote_get_args = $args;

    if (strpos($url, 'error') !== false) {
        return new WP_Error('fetch_error', 'Mock error');
    }

    return array(
        'body' => 'mock content',
        'response' => array('code' => 200)
    );
}

function wp_remote_retrieve_response_code($response) {
    return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body($response) {
    return isset($response['body']) ? $response['body'] : '';
}

class WP_REST_Request {
    public $params;
    public function __construct($params = array()) {
        $this->params = $params;
    }
    public function get_param($key) {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}
