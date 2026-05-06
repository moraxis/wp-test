<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Citation_Checker_Frontend {

	public function __construct() {
		add_shortcode( 'ai_citation_checker', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		global $post;

		// Only enqueue if the shortcode is present
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ai_citation_checker' ) ) {
			wp_enqueue_style(
				'ai-citation-checker-style',
				AI_CITATION_CHECKER_URL . 'assets/style.css',
				array(),
				AI_CITATION_CHECKER_VERSION
			);

			wp_enqueue_script(
				'ai-citation-checker-script',
				AI_CITATION_CHECKER_URL . 'assets/script.js',
				array( 'jquery' ), // Using jquery for simplicity if needed, though vanilla JS is fine
				AI_CITATION_CHECKER_VERSION,
				true
			);

			wp_localize_script(
				'ai-citation-checker-script',
				'aiCitationData',
				array(
					'apiUrl' => esc_url_raw( rest_url( 'ai-citation/v1/fetch' ) ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	public function render_shortcode( $atts ) {
		ob_start();
		?>
		<div class="ai-citation-wrapper">
			<div class="ai-citation-header">
				<input type="url" id="ai-citation-url" placeholder="https://example.com/article" required>
				<button type="button" id="ai-citation-fetch-btn">Fetch & Analyze</button>
			</div>

			<div class="ai-citation-split-pane">
				<div class="ai-citation-pane">
					<div class="ai-citation-pane-header">Extracted Text</div>
					<textarea class="ai-citation-editor" id="ai-citation-editor" placeholder="Enter text here or fetch from a URL..."></textarea>
				</div>

				<button type="button" class="ai-citation-test-again" id="ai-citation-test-again-btn">
					<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
					Test Again
				</button>

				<div class="ai-citation-pane">
					<div class="ai-citation-pane-header">Analysis Results</div>
					<div id="ai-citation-loading" class="ai-citation-loading">Analyzing...</div>
					<div class="ai-citation-results" id="ai-citation-results" style="display: none;">
						<div class="ai-citation-score-circle" id="ai-citation-score">0%</div>
						<div class="ai-citation-suggestions" id="ai-citation-suggestions">
							<!-- Suggestions will be populated here -->
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

new AI_Citation_Checker_Frontend();
