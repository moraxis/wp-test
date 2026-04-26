jQuery(document).ready(function($) {
	let editor;

	// Initialize CodeMirror when document is ready
	if ($('#llmstxt-output-editor').length) {
		editor = CodeMirror.fromTextArea(document.getElementById('llmstxt-output-editor'), {
			mode: 'markdown',
			lineNumbers: true,
			theme: 'default',
			lineWrapping: true,
			readOnly: true // User can copy but not edit here directly for simplicity
		});
	}

	$('#llmstxt-generate-btn').on('click', function(e) {
		e.preventDefault();

		const mainTitle = $('#llmstxt-main-title').val().trim();
		const mainDesc = $('#llmstxt-main-description').val().trim();
		const urlsRaw = $('#llmstxt-urls').val();

		// Split URLs by newline, trim, and remove empty ones
		let urls = urlsRaw.split(/\r?\n/).map(url => url.trim()).filter(url => url !== '');

		if (urls.length === 0 && mainTitle === '' && mainDesc === '') {
			alert('Please provide some information or URLs to generate the file.');
			return;
		}

		// Limit to 100 URLs
		if (urls.length > 100) {
			urls = urls.slice(0, 100);
			alert('You entered more than 100 URLs. Only the first 100 will be processed.');
		}

		// UI Updates for Processing
		const $btn = $(this);
		$btn.prop('disabled', true).text('Generating...');
		$('#llmstxt-progress-container').show();
		$('#llmstxt-result-section').hide();
		$('#llmstxt-warnings-container').hide();
		$('#llmstxt-warnings-list').empty();

		let processedCount = 0;
		const totalCount = urls.length;
		const results = [];
		const warnings = [];

		// If no URLs, just generate static top part immediately
		if (totalCount === 0) {
			finishGeneration(mainTitle, mainDesc, results, warnings);
			$btn.prop('disabled', false).text('Generate llms.txt');
			return;
		}

		function updateProgress() {
			const percent = Math.round((processedCount / totalCount) * 100);
			$('#llmstxt-progress-text').text(`Fetching ${processedCount} of ${totalCount} URLs...`);
			$('#llmstxt-progress-bar-fill').css('width', `${percent}%`);
		}

		updateProgress();

		// Process URLs sequentially to avoid 429
		function processNextUrl(index) {
			if (index >= totalCount) {
				// Done
				finishGeneration(mainTitle, mainDesc, results, warnings);
				$btn.prop('disabled', false).text('Generate llms.txt');
				$('#llmstxt-progress-text').text('Complete!');
				setTimeout(() => {
					$('#llmstxt-progress-container').hide();
					$('#llmstxt-progress-bar-fill').css('width', '0%');
				}, 1000);
				return;
			}

			const url = urls[index];

			$.ajax({
				url: llmstxtGenerator.ajax_url,
				type: 'POST',
				data: {
					action: 'llmstxt_scrape',
					nonce: llmstxtGenerator.nonce,
					url: url
				},
				success: function(response) {
					if (response.success) {
						const data = response.data;
						results.push(data);
						if (!data.description || data.description.trim() === '') {
							warnings.push(url);
						}
					} else {
						// On failure, just add the URL with a failure note
						results.push({ url: url, title: url, description: 'Failed to fetch details.' });
						warnings.push(`${url} (Failed to fetch)`);
					}
				},
				error: function() {
					results.push({ url: url, title: url, description: 'Request error.' });
					warnings.push(`${url} (Request error)`);
				},
				complete: function() {
					processedCount++;
					updateProgress();

					// Slight delay between requests to be polite to servers
					setTimeout(function() {
						processNextUrl(index + 1);
					}, 500);
				}
			});
		}

		// Start processing
		processNextUrl(0);
	});

	function finishGeneration(title, description, linksData, warnings) {
		let output = '';

		// Title
		if (title) {
			output += `# ${title}\n\n`;
		} else {
			output += `# llms.txt\n\n`;
		}

		// Description
		if (description) {
			output += `> ${description}\n\n`;
		}

		// Sections - For simplicity, we just put everything under "## Links"
		// Users can manually modify this later if they want, but the spec says "Section name"
		if (linksData.length > 0) {
			output += `## Provided Links\n\n`;

			linksData.forEach(link => {
				output += `- [${link.title}](${link.url})`;
				if (link.description) {
					output += `: ${link.description}`;
				}
				output += `\n`;
			});
			output += `\n`;
		}

		// Add Tool Credit Comment
		output += `<!--\n`;
		output += `  Generated with the LLMs.txt Generator tool at www.theknez.com/llms-generator/\n`;
		output += `  Courtesy of Nikola Knezhevich.\n`;
		output += `-->\n`;

		// Set editor value
		editor.setValue(output);

		// Handle warnings
		if (warnings.length > 0) {
			const $warningsList = $('#llmstxt-warnings-list');
			warnings.forEach(w => {
				const $li = $('<li></li>').text(w);
				$warningsList.append($li);
			});
			$('#llmstxt-warnings-container').show();
		}

		// Show results and refresh editor layout
		$('#llmstxt-result-section').show();

		// CodeMirror might render incorrectly if initialized in a hidden div, so refresh it
		setTimeout(function() {
			editor.refresh();

			// Scroll down smoothly
			$('html, body').animate({
				scrollTop: $("#llmstxt-result-section").offset().top - 20
			}, 500);
		}, 100);
	}

	// Copy to clipboard
	$('#llmstxt-copy-btn').on('click', function(e) {
		e.preventDefault();
		const textToCopy = editor.getValue();

		// Use modern navigator.clipboard API if available
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(textToCopy).then(() => {
				showCopySuccess();
			});
		} else {
			// Fallback for older browsers or non-HTTPS
			const textArea = document.createElement("textarea");
			textArea.value = textToCopy;
			textArea.style.position = "fixed";  // Avoid scrolling to bottom
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();

			try {
				document.execCommand('copy');
				showCopySuccess();
			} catch (err) {
				console.error('Fallback: Oops, unable to copy', err);
				alert("Copy failed. Please select the text and copy manually.");
			}
			document.body.removeChild(textArea);
		}
	});

	function showCopySuccess() {
		const $success = $('#llmstxt-copy-success');
		$success.fadeIn(200);
		setTimeout(function() {
			$success.fadeOut(500);
		}, 2000);
	}
});