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
$shortcodes = array();
function add_shortcode($tag, $callback) {
    global $shortcodes;
    $shortcodes[$tag] = $callback;
}
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
    if ($option === 'the_link_goblin_api_key' && !isset($mock_options[$option])) return 'mock_key';
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

function wp_strip_all_tags($text) { return strip_tags($text); }

function get_post($post_id) {
    return (object) array(
        'ID' => $post_id,
        'post_content' => 'Test content.',
        'post_status' => 'publish',
        'post_title' => 'Test Post',
        'post_type' => 'post'
    );
}

function get_permalink($post_id) { return "http://example.com/p/$post_id"; }

function get_posts($args) {
    return array(
        (object) array('ID' => 101, 'post_title' => 'Target 1'),
    );
}

function wp_trim_words($text, $num_words = 55, $more = null) { return $text; }

function wp_json_encode($data) { return json_encode($data); }

function wp_safe_remote_post($url, $args) {
    $suggestions = array(
        array('target_id' => 101, 'anchor_text' => 'Test', 'context_sentence' => 'Test content.'),
        array('target_id' => 101, 'anchor_text' => 'Test', 'context_sentence' => 'Test content.'),
        array('target_id' => 101, 'anchor_text' => 'Test', 'context_sentence' => 'Test content.'),
    );

    return array(
        'response' => array('code' => 200),
        'body' => json_encode(array(
            'choices' => array(
                array(
                    'message' => array(
                        'content' => json_encode($suggestions)
                    )
                )
            )
        ))
    );
}

function get_post_status($post_id) { return 'publish'; }

function current_time($type) { return date('Y-m-d H:i:s'); }

$mock_post_meta = array();

function get_post_meta($post_id, $key = '', $single = false) {
    global $mock_post_meta;
    if (isset($mock_post_meta[$post_id][$key])) {
        return $single ? $mock_post_meta[$post_id][$key][0] : $mock_post_meta[$post_id][$key];
    }
    return $single ? '' : array();
}

function update_post_meta($post_id, $meta_key, $meta_value) {
    global $mock_post_meta;
    $mock_post_meta[$post_id][$meta_key] = array($meta_value);
    return true;
}

function delete_post_meta($post_id, $meta_key) {
    global $mock_post_meta;
    unset($mock_post_meta[$post_id][$meta_key]);
    return true;
}

function get_edit_post_link($post_id) { return "http://example.com/wp-admin/post.php?post=$post_id&action=edit"; }

function get_the_title($post_id) { return "Post $post_id"; }

function get_post_type_object($post_type) {
    return (object) array(
        'labels' => (object) array(
            'singular_name' => ucfirst($post_type)
        )
    );
}

function home_url() { return 'http://example.com'; }

function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }

function admin_url($path) { return 'http://example.com/wp-admin/' . $path; }

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

    $code = 200;
    if (preg_match('/status=(\d+)/', $url, $matches)) {
        $code = intval($matches[1]);
    }

    return array(
        'body' => 'mock content',
        'response' => array('code' => $code)
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
