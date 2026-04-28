<?php
/**
 * Plugin Name: Live Header Response & Redirect Chain Checker
 * Description: Displays the full redirect chain and important HTTP headers.
 * Version: 1.0.0
 * Author: Nikola Knezhevich
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LIVE_HEADER_CHECKER_VERSION', '1.0.0');
define('LIVE_HEADER_CHECKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LIVE_HEADER_CHECKER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Disable WordPress.org update checks for this custom plugin
add_filter('site_transient_update_plugins', 'live_header_checker_disable_updates');
function live_header_checker_disable_updates($transient) {
    if (isset($transient->response[plugin_basename(__FILE__)])) {
        unset($transient->response[plugin_basename(__FILE__)]);
    }
    return $transient;
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'live_header_checker_enqueue_assets');
function live_header_checker_enqueue_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'live_header_checker')) {
        wp_enqueue_style(
            'live-header-checker-style',
            LIVE_HEADER_CHECKER_PLUGIN_URL . 'assets/style.css',
            array(),
            LIVE_HEADER_CHECKER_VERSION
        );

        wp_enqueue_script(
            'live-header-checker-script',
            LIVE_HEADER_CHECKER_PLUGIN_URL . 'assets/script.js',
            array('jquery'),
            LIVE_HEADER_CHECKER_VERSION,
            true
        );

        wp_localize_script('live-header-checker-script', 'liveHeaderCheckerData', array(
            'restUrl' => esc_url_raw(rest_url('live-header-checker/v1/check')),
            'nonce'   => wp_create_nonce('wp_rest')
        ));
    }
}

// Register shortcode
add_shortcode('live_header_checker', 'live_header_checker_shortcode');
function live_header_checker_shortcode($atts) {
    ob_start();
    ?>
    <div id="lhc-container" class="lhc-container">
        <h2 class="lhc-heading">Live Header Response & Redirect Chain Checker</h2>
        <div class="lhc-form">
            <input type="url" id="lhc-url" class="lhc-input" placeholder="Enter URL (e.g. https://example.com)" required>
            <select id="lhc-user-agent" class="lhc-select">
                <option value="default">Default Browser</option>
                <option value="googlebot-desktop">Googlebot Desktop</option>
                <option value="googlebot-smartphone">Googlebot Smartphone</option>
                <option value="bingbot">Bingbot</option>
            </select>
            <button id="lhc-check-btn" class="lhc-btn">Check</button>
        </div>
        <div id="lhc-results" class="lhc-results"></div>
    </div>
    <?php
    return ob_get_clean();
}

// Load REST API endpoint
require_once LIVE_HEADER_CHECKER_PLUGIN_DIR . 'includes/api.php';
