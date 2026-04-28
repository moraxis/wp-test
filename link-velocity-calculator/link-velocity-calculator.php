<?php
/**
 * Plugin Name: Link Velocity Calculator
 * Description: Calculates how many links per month are needed to catch up with a competitor.
 * Version: 1.0.0
 * Author: Nikola Knezhevich
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Link_Velocity_Calculator {

    public function __construct() {
        add_shortcode( 'link-velocity-calculator', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'site_transient_update_plugins', array( $this, 'disable_update_check' ) );
    }

    /**
     * Disable update checks for this custom plugin
     */
    public function disable_update_check( $transient ) {
        if ( isset( $transient->response['link-velocity-calculator/link-velocity-calculator.php'] ) ) {
            unset( $transient->response['link-velocity-calculator/link-velocity-calculator.php'] );
        }
        return $transient;
    }

    /**
     * Enqueue assets
     */
    public function enqueue_scripts() {
        global $post;

        // Only enqueue if the shortcode is present on the page
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'link-velocity-calculator' ) ) {
            $version = '1.0.0';

            wp_enqueue_style(
                'lvc-style',
                plugins_url( 'assets/css/style.css', __FILE__ ),
                array(),
                $version
            );

            wp_enqueue_script(
                'lvc-script',
                plugins_url( 'assets/js/script.js', __FILE__ ),
                array(),
                $version,
                true // Load in footer
            );
        }
    }

    /**
     * Render the shortcode HTML
     */
    public function render_shortcode() {
        ob_start();
        ?>
        <div class="lvc-container">
            <h2 class="lvc-heading">Link Velocity Calculator</h2>
            <div class="lvc-form-group">
                <label for="lvc-my-links">My Current Links</label>
                <input type="number" id="lvc-my-links" placeholder="e.g. 50" min="0">
            </div>
            <div class="lvc-form-group">
                <label for="lvc-competitor-links">Competitor Current Links</label>
                <input type="number" id="lvc-competitor-links" placeholder="e.g. 200" min="0">
            </div>
            <div class="lvc-form-group">
                <label for="lvc-competitor-growth">Competitor Link Growth (per month)</label>
                <input type="number" id="lvc-competitor-growth" placeholder="e.g. 10" min="0">
            </div>
            <div class="lvc-form-group">
                <label for="lvc-months">Months to Catch Up</label>
                <input type="number" id="lvc-months" placeholder="e.g. 12" min="1">
            </div>
            <button id="lvc-calculate-btn" class="lvc-button">Calculate Required Links</button>

            <div id="lvc-result-container" class="lvc-result-hidden">
                <h3>Required Link Velocity:</h3>
                <p><span id="lvc-result-number">0</span> links / month</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Link_Velocity_Calculator();
