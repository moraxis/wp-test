<div class="hreflang-tester-container">
	<div class="hreflang-tester-header">
		<h3>Hreflang Tags Tester</h3>
		<p>Check if hreflang tags for a page (HTML/Headers) or in XML Sitemaps are correct.</p>
	</div>

	<div class="hreflang-tester-controls">
		<div class="hreflang-tester-type-toggle">
			<label>
				<input type="radio" name="hreflang_type" value="page" checked> Page URL
			</label>
			<label>
				<input type="radio" name="hreflang_type" value="sitemap"> XML Sitemap
			</label>
		</div>

		<div class="hreflang-tester-input-group">
			<input type="url" id="hreflang_url_input" placeholder="https://example.com" required>
			<button id="hreflang_test_btn">Test Hreflang Tags</button>
		</div>
		<div id="hreflang_global_error" class="hreflang-tester-message" style="display: none;"></div>
	</div>

	<div class="hreflang-tester-results" id="hreflang_results_container" style="display: none;">
		<div class="hreflang-tester-summary" id="hreflang_summary"></div>

		<div class="hreflang-tester-table-wrapper">
			<table class="hreflang-tester-table" id="hreflang_results_table">
				<thead>
					<tr>
						<th>Source URL</th>
						<th>Language / Region</th>
						<th>Alternate URL</th>
						<th>Status</th>
						<th>Details</th>
					</tr>
				</thead>
				<tbody>
					<!-- Results will be injected here -->
				</tbody>
			</table>
		</div>
	</div>
</div>