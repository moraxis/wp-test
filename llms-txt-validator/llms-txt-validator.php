<?php
/**
 * Plugin Name: LLMS.txt Validator
 * Description: A shortcode-based plugin to validate llms.txt files according to the standard specifications. Use shortcode [llms_txt_validator].
 * Version: 1.0.1
 * Author: Nikola Knezhevich
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class LLMS_Txt_Validator {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function init() {
        add_shortcode('llms_txt_validator', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        // We will only enqueue these when the shortcode is used, or globally if preferred.
        // For simplicity, we register them here and enqueue them in the shortcode.
        wp_register_style('codemirror-css', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css', array(), '5.65.13');
        wp_register_script('codemirror-js', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js', array(), '5.65.13', true);
        wp_register_script('codemirror-markdown', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/markdown/markdown.min.js', array('codemirror-js'), '5.65.13', true);

        wp_register_style('llms-validator-style', plugins_url('assets/css/style.css', __FILE__), array(), '1.0.0');
        wp_register_script('llms-validator-script', plugins_url('assets/js/validator.js', __FILE__), array('jquery', 'codemirror-js', 'codemirror-markdown'), '1.0.0', true);

        wp_localize_script('llms-validator-script', 'llmsValidatorConfig', array(
            'restUrl' => esc_url_raw(rest_url('llms-validator/v1/fetch')),
            'nonce'   => wp_create_nonce('wp_rest')
        ));
    }

    public function render_shortcode($atts) {
        wp_enqueue_style('codemirror-css');
        wp_enqueue_script('codemirror-js');
        wp_enqueue_script('codemirror-markdown');
        wp_enqueue_style('llms-validator-style');
        wp_enqueue_script('llms-validator-script');

        ob_start();
        ?>
        <div class="llms-validator-container">
            <h2 class="llms-validator-title">LLMS.txt Validator</h2>
            <div class="llms-validator-fetch-section">
                <input type="url" id="llms-fetch-url" placeholder="https://example.com/llms.txt" />
                <button type="button" id="llms-fetch-btn" class="llms-btn-cta">Fetch URL</button>
            </div>
            <div id="llms-fetch-error" class="llms-error-message" style="display:none;"></div>

            <div class="llms-validator-grid">
                <div class="llms-editor-panel">
                    <textarea id="llms-editor" placeholder="Paste your llms.txt content here..."></textarea>
                </div>
                <div class="llms-results-panel">
                    <h3 class="llms-results-title">Validation Results</h3>
                    <ul id="llms-validation-errors" class="llms-error-list">
                        <li class="llms-no-errors">Awaiting input...</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function register_rest_routes() {
        register_rest_route('llms-validator/v1', '/fetch', array(
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

        // Basic validation that it ends with .txt or .md or looks like a valid text path
        // but to be permissive as proxy, we'll just fetch it.
        // Use wp_safe_remote_get to prevent Server-Side Request Forgery (SSRF)
        $response = wp_safe_remote_get($url, array(
            'timeout' => 15,
            'redirection' => 5,
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

new LLMS_Txt_Validator();
