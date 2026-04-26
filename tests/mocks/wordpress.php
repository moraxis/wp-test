<?php
// tests/mocks/wordpress.php

class WordPressMock {
    public static $enqueued_styles = [];
    public static $enqueued_scripts = [];
    public static $registered_styles = [];
    public static $registered_scripts = [];
    public static $localized_scripts = [];
    public static $shortcodes = [];
    public static $actions = [];

    public static function reset() {
        self::$enqueued_styles = [];
        self::$enqueued_scripts = [];
        self::$registered_styles = [];
        self::$registered_scripts = [];
        self::$localized_scripts = [];
        self::$shortcodes = [];
        self::$actions = [];
    }
}

function wp_enqueue_style($handle) {
    WordPressMock::$enqueued_styles[] = $handle;
}

function wp_enqueue_script($handle) {
    WordPressMock::$enqueued_scripts[] = $handle;
}

function wp_register_style($handle, $src, $deps = [], $ver = false, $media = 'all') {
    WordPressMock::$registered_styles[$handle] = $src;
}

function wp_register_script($handle, $src, $deps = [], $ver = false, $in_footer = false) {
    WordPressMock::$registered_scripts[$handle] = $src;
}

function wp_localize_script($handle, $object_name, $l10n) {
    WordPressMock::$localized_scripts[$handle] = [$object_name => $l10n];
}

function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
    WordPressMock::$actions[$tag][] = $function_to_add;
}

function add_shortcode($tag, $callback) {
    WordPressMock::$shortcodes[$tag] = $callback;
}

function plugins_url($path = '', $plugin = '') {
    return 'http://example.com/wp-content/plugins/' . $path;
}

function rest_url($path = '') {
    return 'http://example.com/wp-json/' . ltrim($path, '/');
}

function wp_create_nonce($action = -1) {
    return 'mock_nonce_' . $action;
}

function register_rest_route($namespace, $route, $args = [], $override = false) {
    // Mock implementation
}

function __return_true() {
    return true;
}

function esc_url_raw($url) {
    return $url;
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../llms-txt-validator/');
}
