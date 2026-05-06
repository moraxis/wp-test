<div class="llmstxt-generator-container">
	<div class="llmstxt-generator-input-section">
		<h2 class="llmstxt-heading">Generate your llms.txt</h2>

		<div class="llmstxt-form-group">
			<label for="llmstxt-main-title">Project Title (optional)</label>
			<input type="text" id="llmstxt-main-title" placeholder="e.g., My Awesome Project" class="llmstxt-input" />
		</div>

		<div class="llmstxt-form-group">
			<label for="llmstxt-main-description">Project Description (optional)</label>
			<textarea id="llmstxt-main-description" rows="3" placeholder="Brief description of the project..." class="llmstxt-input"></textarea>
		</div>

		<div class="llmstxt-form-group">
			<label for="llmstxt-urls">URLs to include (One URL per line, max 100)</label>
			<textarea id="llmstxt-urls" rows="8" placeholder="https://example.com/&#10;https://example.com/about&#10;https://example.com/docs" class="llmstxt-input"></textarea>
		</div>

		<button id="llmstxt-generate-btn" class="llmstxt-btn llmstxt-btn-primary">Generate llms.txt</button>

		<div id="llmstxt-progress-container" style="display: none;">
			<p id="llmstxt-progress-text">Processing...</p>
			<div class="llmstxt-progress-bar-bg">
				<div id="llmstxt-progress-bar-fill" class="llmstxt-progress-bar-fill"></div>
			</div>
		</div>
	</div>

	<div id="llmstxt-result-section" class="llmstxt-result-section" style="display: none;">
		<div id="llmstxt-warnings-container" class="llmstxt-warnings" style="display: none;">
			<h3 class="llmstxt-heading">Warnings</h3>
			<p>The following URLs did not have a meta description, so their descriptions were left blank. You may want to fill them manually:</p>
			<ul id="llmstxt-warnings-list"></ul>
		</div>

		<h3 class="llmstxt-heading">Generated Output</h3>
		<textarea id="llmstxt-output-editor" style="display: none;"></textarea>

		<div class="llmstxt-actions">
			<button id="llmstxt-copy-btn" class="llmstxt-btn llmstxt-btn-cta">Copy to Clipboard</button>
			<span id="llmstxt-copy-success" style="display: none; margin-left: 10px; color: #f99417; font-weight: bold;">Copied!</span>
		</div>
	</div>
</div>
