<?php
/**
 * Plugin Name: Robots.txt Validator
 * Description: A shortcode-based plugin to validate robots.txt files. Use shortcode [robots_txt_validator].
 * Version: 1.0.2
 * Author: Nikola Knezhevich
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Robots_Txt_Validator {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function init() {
        add_shortcode('robots_txt_validator', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        wp_register_style('codemirror-css', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css', array(), '5.65.13');
        wp_register_script('codemirror-js', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js', array(), '5.65.13', true);

        // No specific markdown mode needed for robots.txt, default or a simple generic mode is fine, we'll use base.

        wp_register_style('robots-validator-style', plugins_url('assets/css/style.css', __FILE__), array('codemirror-css'), '1.0.2');
        wp_register_script('robots-validator-script', plugins_url('assets/js/validator.js', __FILE__), array('jquery', 'codemirror-js'), '1.0.2', true);

        wp_localize_script('robots-validator-script', 'robotsValidatorConfig', array(
            'restUrl' => esc_url_raw(rest_url('robots-validator/v1/fetch')),
            'nonce'   => wp_create_nonce('wp_rest')
        ));
    }

    public function render_shortcode($atts) {
        wp_enqueue_style('codemirror-css');
        wp_enqueue_script('codemirror-js');
        wp_enqueue_style('robots-validator-style');
        wp_enqueue_script('robots-validator-script');

        ob_start();
        ?>
        <div class="robots-validator-container">
            <h2 class="robots-validator-title">Robots.txt Validator</h2>
            <div class="robots-validator-fetch-section">
                <input type="url" id="robots-fetch-url" placeholder="https://example.com/robots.txt" />
                <button type="button" id="robots-fetch-btn" class="robots-btn-cta">Fetch URL</button>
            </div>
            <div id="robots-fetch-error" class="robots-error-message" style="display:none;"></div>

            <div class="robots-validator-grid" style="position: relative;">
                <div class="robots-editor-panel">
                    <textarea id="robots-editor" placeholder="Paste your robots.txt content here..."></textarea>
                </div>

                <button type="button" id="robots-test-again-btn" class="robots-test-again-btn" title="Run new test">
                    <span class="robots-test-again-text">test</span>
                    <span class="robots-test-again-icon">&#9654;</span>
                </button>

                <div class="robots-results-panel">
                    <h3 class="robots-results-title">Validation Results</h3>
                    <ul id="robots-validation-errors" class="robots-error-list">
                        <li class="robots-no-errors">Awaiting input...</li>
                    </ul>

                    <h3 class="robots-results-title robots-notes-title" style="margin-top: 20px;">Notes / Warnings</h3>
                    <ul id="robots-validation-notes" class="robots-note-list">
                        <li class="robots-no-notes">No notes.</li>
                    </ul>
                </div>
            </div>

            <div class="robots-test-tool" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <h3 class="robots-results-title">URL Path Tester</h3>
                <div class="robots-test-inputs" style="display:flex; flex-direction:row; gap:10px;">
                    <input type="text" id="robots-test-path" placeholder="e.g. /private/page.html" style="flex:1;" />
                    <select id="robots-test-agent" style="flex:1;">
                        <option value="*">Any User-Agent (*)</option>
                        <option value="Googlebot">Googlebot</option>
                        <option value="Bingbot">Bingbot</option>
                        <option value="Slurp">Yahoo Slurp</option>
                        <option value="DuckDuckBot">DuckDuckBot</option>
                        <option value="Baiduspider">Baiduspider</option>
                        <option value="YandexBot">YandexBot</option>
                        <option value="Other">Other (custom)</option>
                    </select>
                    <input type="text" id="robots-test-agent-custom" placeholder="Custom User-Agent" style="display:none; flex:1;" />
                    <button type="button" id="robots-test-btn" class="robots-btn-normal" style="flex:none;">Test Path</button>
                </div>
                <div id="robots-test-result" class="robots-test-result" style="display:none; margin-top: 15px; font-weight: bold;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function register_rest_routes() {
        register_rest_route('robots-validator/v1', '/fetch', array(
            'methods'  => 'GET',
            'callback' => array($this, 'fetch_remote_txt'),
            'permission_callback' => '__return_true', // Publicly accessible to handle frontend requests
            'args' => array(
                'url' => array(
                    'required' => true,
                    'type'     => 'string',
                    'format'   => 'uri',
                    'sanitize_callback' => 'esc_url_raw'
                )
            )
        ));
    }

    public function fetch_remote_txt($request) {
        $url = $request->get_param('url');

        // Append robots.txt if the user just entered the domain, as a helpful fallback
        if (!preg_match('/robots\.txt$/i', $url)) {
            $url = rtrim($url, '/') . '/robots.txt';
        }

        // Use wp_safe_remote_get with a Googlebot user agent to avoid blocks
        $response = wp_safe_remote_get($url, array(
            'timeout' => 15,
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        ));

        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', 'Failed to fetch the URL: ' . $response->get_error_message(), array('status' => 500));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('fetch_error', 'Received status code ' . $status_code . ' from the remote server. Please copy-paste manually if access is blocked.', array('status' => $status_code));
        }

        $body = wp_remote_retrieve_body($response);

        return rest_ensure_response(array(
            'success' => true,
            'content' => $body
        ));
    }
}

new Robots_Txt_Validator();
