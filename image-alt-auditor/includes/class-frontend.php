<?php
/**
 * Frontend shortcode registration and asset enqueuing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Alt_Auditor_Frontend {

	public function __construct() {
		add_shortcode( 'image_alt_auditor', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		// Only enqueue if the shortcode is present on the page
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'image_alt_auditor' ) ) {

			wp_register_style(
				'image-alt-auditor-style',
				plugins_url( 'assets/css/style.css', dirname( __FILE__ ) ),
				array(),
				IMAGE_ALT_AUDITOR_VERSION
			);
			wp_enqueue_style( 'image-alt-auditor-style' );

			wp_register_script(
				'image-alt-auditor-script',
				plugins_url( 'assets/js/script.js', dirname( __FILE__ ) ),
				array(),
				IMAGE_ALT_AUDITOR_VERSION,
				true
			);
			wp_enqueue_script( 'image-alt-auditor-script' );

			wp_localize_script(
				'image-alt-auditor-script',
				'imageAltAuditorData',
				array(
					'rest_url' => esc_url_raw( rest_url( 'image-alt-auditor/v1/audit' ) ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	public function render_shortcode() {
		ob_start();
		?>
		<div class="iaa-container">
			<div class="iaa-header">
				<h2 class="iaa-title">Image Alt Text Auditor</h2>
				<p class="iaa-description">Enter a URL to scan for images missing alt attributes or using CSS backgrounds.</p>
			</div>

			<form id="iaa-form" class="iaa-form">
				<div class="iaa-input-group">
					<input type="url" id="iaa-url" class="iaa-input" placeholder="https://example.com" required>
					<button type="submit" id="iaa-submit" class="iaa-btn">Audit URL</button>
				</div>
			</form>

			<div id="iaa-loading" class="iaa-loading" style="display: none;">
				<div class="iaa-spinner"></div>
				<p>Auditing... This may take a moment.</p>
			</div>

			<div id="iaa-error" class="iaa-error" style="display: none;"></div>

			<div id="iaa-results-container" class="iaa-results-container" style="display: none;">
				<h3 class="iaa-results-title">Audit Results</h3>
				<p id="iaa-results-summary" class="iaa-results-summary"></p>
				<div class="iaa-table-wrapper">
					<table class="iaa-table">
						<thead>
							<tr>
								<th>Image</th>
								<th>URL</th>
								<th>Issue</th>
							</tr>
						</thead>
						<tbody id="iaa-results-body">
							<!-- Results injected here -->
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

new Image_Alt_Auditor_Frontend();
