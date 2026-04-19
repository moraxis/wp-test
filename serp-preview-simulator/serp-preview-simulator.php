<?php
/**
 * Plugin Name: SERP Preview Simulator
 * Description: A SERP preview simulator with support for manual structured data (JSON-LD) input.
 * Version: 1.0.0
 * Author: Nikola Knezhevich
 * Text Domain: serp-preview-simulator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class SERP_Preview_Simulator {

	public function __construct() {
		add_shortcode( 'serp_preview_simulator', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		global $post;

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'serp_preview_simulator' ) ) {
			wp_enqueue_style( 'codemirror-css', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css', array(), '5.65.13' );
			wp_enqueue_style( 'codemirror-theme', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/monokai.min.css', array(), '5.65.13' );
			wp_enqueue_script( 'codemirror-js', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js', array(), '5.65.13', true );
			wp_enqueue_script( 'codemirror-js-mode', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js', array('codemirror-js'), '5.65.13', true );

			wp_enqueue_style( 'serp-simulator-style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), '1.0.0' );
			wp_enqueue_script( 'serp-simulator-script', plugin_dir_url( __FILE__ ) . 'assets/js/script.js', array( 'jquery', 'codemirror-js', 'codemirror-js-mode' ), '1.0.0', true );
		}
	}

	public function render_shortcode() {
		ob_start();
		?>
		<div id="serp-simulator-app" class="serp-simulator-container">
            <div class="serp-sim-left-column">
                <div class="serp-sim-tabs">
                    <button class="serp-sim-tab-btn active" data-tab="basic-info">Basic Information</button>
                    <button class="serp-sim-tab-btn" data-tab="structured-data">Structured Data</button>
                </div>

                <div class="serp-sim-tab-content active" id="tab-basic-info">
                    <!-- Checkboxes -->
                    <div class="serp-sim-checkboxes">
                        <label><input type="checkbox" id="sim-check-ai" /> AI Overview</label>
                        <label><input type="checkbox" id="sim-check-date" /> Date</label>
                        <label><input type="checkbox" id="sim-check-rating" /> Rating Override</label>
                        <label><input type="checkbox" id="sim-check-ads" /> Ads</label>
                        <label><input type="checkbox" id="sim-check-map" /> Map Pack</label>
                    </div>

                    <!-- Input Fields -->
                    <div class="serp-sim-inputs">
                        <div class="sim-input-group">
                            <label for="sim-input-url">URL</label>
                            <input type="text" id="sim-input-url" placeholder="https://example.com/page" value="https://example.com/" />
                        </div>
                        <div class="sim-input-group">
                            <label for="sim-input-sitename">Site Name</label>
                            <input type="text" id="sim-input-sitename" placeholder="Example Site" value="Example Site" />
                        </div>
                        <div class="sim-input-group">
                            <label for="sim-input-title">Title</label>
                            <input type="text" id="sim-input-title" placeholder="Page Title - Example Site" value="Your Page Title Goes Here" />
                        </div>
                        <div class="sim-input-group">
                            <label for="sim-input-desc">Meta description</label>
                            <textarea id="sim-input-desc" rows="3" placeholder="Write an engaging meta description...">This is an example meta description. It should be engaging and relevant to the page content.</textarea>
                        </div>
                        <div class="sim-input-group">
                            <label for="sim-input-bold">Bold keywords (comma separated)</label>
                            <input type="text" id="sim-input-bold" placeholder="example, meta description" />
                        </div>
                    </div>
                </div>

                <div class="serp-sim-tab-content" id="tab-structured-data">
                    <div class="sim-input-group">
                        <label for="sim-input-jsonld">Structured Data (JSON-LD)</label>
                        <textarea id="sim-input-jsonld" name="sim-input-jsonld"></textarea>
                    </div>
                </div>
            </div>

            <div class="serp-sim-right-column">
                <div class="serp-sim-preview-wrapper">
                    <h3>Preview</h3>

                    <div class="serp-sim-preview-container">
                        <!-- AI Overview module -->
                        <div id="sim-preview-ai" class="sim-preview-module sim-hidden">
                            <div class="sim-ai-header">
                                <svg focusable="false" viewBox="0 0 24 24" fill="#d93025" width="24" height="24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"></path></svg>
                                <span>AI Overview</span>
                            </div>
                            <div class="sim-ai-content">
                                Based on the search, here is a synthesized AI overview generated for this query. It attempts to provide a direct answer.
                            </div>
                        </div>

                        <!-- Map Pack module -->
                        <div id="sim-preview-map" class="sim-preview-module sim-hidden">
                            <div class="sim-map-img"></div>
                            <div class="sim-map-results">
                                <div class="sim-map-item">Local Business 1<br><span style="color:#70757a;font-size:12px;">4.5 ★ (120) · Category</span></div>
                                <div class="sim-map-item">Local Business 2<br><span style="color:#70757a;font-size:12px;">4.2 ★ (85) · Category</span></div>
                                <div class="sim-map-item">Local Business 3<br><span style="color:#70757a;font-size:12px;">4.8 ★ (200) · Category</span></div>
                            </div>
                        </div>

                        <!-- Main Organic Result -->
                        <div class="sim-preview-result">
                            <div class="sim-preview-url-container">
                                <div class="sim-preview-favicon"></div>
                                <div class="sim-preview-url-text">
                                    <span id="sim-preview-sitename">Example Site</span>
                                    <span class="sim-preview-url-cite">
                                        <span id="sim-preview-ads-label" class="sim-hidden"><b>Sponsored</b> · </span>
                                        <span id="sim-preview-url">https://example.com</span>
                                        <span id="sim-preview-breadcrumbs" class="sim-hidden"></span>
                                    </span>
                                </div>
                            </div>
                            <div class="sim-preview-title" id="sim-preview-title">Your Page Title Goes Here</div>

                            <!-- Rating -->
                            <div class="sim-preview-rating-container sim-hidden" id="sim-preview-rating-wrap">
                                <span class="sim-rating-stars">★★★★★</span>
                                <span class="sim-rating-score">5.0</span>
                                <span class="sim-rating-count">(99)</span>
                            </div>

                            <div class="sim-preview-snippet">
                                <span id="sim-preview-date" class="sim-hidden">Oct 24, 2023 — </span>
                                <span id="sim-preview-desc">This is an example meta description. It should be engaging and relevant to the page content.</span>
                            </div>

                            <!-- Structured Data Specific UI (FAQ, Products, etc.) -->
                            <div id="sim-preview-sd-features"></div>
                        </div>
                    </div>
                </div>
            </div>
		</div>
		<?php
		return ob_get_clean();
	}
}

new SERP_Preview_Simulator();
