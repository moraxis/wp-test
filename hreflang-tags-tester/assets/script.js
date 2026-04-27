jQuery(document).ready(function($) {
	const $input = $('#hreflang_url_input');
	const $btn = $('#hreflang_test_btn');
	const $globalError = $('#hreflang_global_error');
	const $resultsContainer = $('#hreflang_results_container');
	const $tbody = $('#hreflang_results_table tbody');
	const $summary = $('#hreflang_summary');

	let validationQueue = [];
	let isProcessing = false;
	const CONCURRENT_REQUESTS = 5;

	$btn.on('click', function() {
		const url = $input.val().trim();
		const type = $('input[name="hreflang_type"]:checked').val();

		if (!url) {
			showError('Please enter a valid URL.');
			return;
		}

		// Reset state
		$globalError.hide();
		$resultsContainer.hide();
		$tbody.empty();
		$btn.prop('disabled', true).text('Parsing...');
		validationQueue = [];

		// Step 1: Parse the URL (Page or Sitemap)
		$.ajax({
			url: hreflangTesterParams.restUrl + '/parse',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', hreflangTesterParams.nonce);
			},
			data: {
				url: url,
				type: type
			},
			success: function(response) {
				if (!response || !response.urls || response.urls.length === 0) {
					showError('No URLs or hreflang tags found.');
					$btn.prop('disabled', false).text('Test Hreflang Tags');
					return;
				}

				populateTable(response.urls);
				$resultsContainer.show();
				$btn.text('Validating...');

				startBatchValidation();
			},
			error: function(xhr) {
				let errMsg = 'An error occurred while parsing the URL.';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errMsg = xhr.responseJSON.message;
				}
				showError(errMsg);
				$btn.prop('disabled', false).text('Test Hreflang Tags');
			}
		});
	});

	function showError(msg) {
		$globalError.text(msg).show();
	}

	function populateTable(urls) {
		let taskCount = 0;

		urls.forEach(function(item) {
			const sourceUrl = item.url;
			const alternates = item.alternates || [];

			if (alternates.length === 0) {
				// Add row showing no tags
				$tbody.append(`
					<tr>
						<td class="hreflang-word-break">${escapeHtml(sourceUrl)}</td>
						<td>-</td>
						<td>-</td>
						<td><span class="hreflang-status-icon hreflang-status-error">✗</span></td>
						<td>No hreflang tags found for this URL.</td>
					</tr>
				`);
			} else {
				// Has tags, check for self-referencing
				let hasSelf = false;

				alternates.forEach(function(alt, index) {
					const targetUrl = alt.href;
					const lang = alt.hreflang;
					const rowId = 'row_' + Math.random().toString(36).substr(2, 9);

					if (sourceUrl.replace(/\/$/, '') === targetUrl.replace(/\/$/, '')) {
						hasSelf = true;
					}

					// Build table row with loading state
					$tbody.append(`
						<tr id="${rowId}">
							<td class="hreflang-word-break">${index === 0 ? escapeHtml(sourceUrl) : ''}</td>
							<td>${escapeHtml(lang)}</td>
							<td class="hreflang-word-break">${escapeHtml(targetUrl)}</td>
							<td class="status-cell"><span class="hreflang-status-loading"></span></td>
							<td class="details-cell">Pending validation...</td>
						</tr>
					`);

					// Add to queue
					validationQueue.push({
						rowId: rowId,
						sourceUrl: sourceUrl,
						targetUrl: targetUrl,
						sourceLang: lang
					});
					taskCount++;
				});

				if (!hasSelf) {
					// Add a visual warning about missing self-referencing tag to the top of the table.
					$tbody.prepend(`
						<tr>
							<td class="hreflang-word-break">${escapeHtml(sourceUrl)}</td>
							<td>-</td>
							<td>-</td>
							<td class="status-cell"><span class="hreflang-status-icon hreflang-status-error" style="color: #f57c00;">!</span></td>
							<td class="details-cell"><ul class="hreflang-details-warnings"><li>Missing self-referencing tag (Google recommendation, not strictly mandatory).</li></ul></td>
						</tr>
					`);
				}
			}
		});

		$summary.text(`Found ${taskCount} hreflang tags to validate.`);
	}

	function startBatchValidation() {
		if (isProcessing) return;
		isProcessing = true;

		let activeWorkers = 0;
		let completed = 0;
		const total = validationQueue.length;

		function processNext() {
			if (validationQueue.length === 0 && activeWorkers === 0) {
				// All done
				isProcessing = false;
				$btn.prop('disabled', false).text('Test Hreflang Tags');
				$summary.text(`Validation complete. Processed ${total} tags.`);
				return;
			}

			while (activeWorkers < CONCURRENT_REQUESTS && validationQueue.length > 0) {
				const task = validationQueue.shift();
				activeWorkers++;

				validateUrl(task, function() {
					completed++;
					activeWorkers--;
					$summary.text(`Validating... (${completed}/${total})`);
					processNext();
				});
			}
		}

		processNext();
	}

	function validateUrl(task, callback) {
		$.ajax({
			url: hreflangTesterParams.restUrl + '/validate',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', hreflangTesterParams.nonce);
			},
			data: {
				source_url: task.sourceUrl,
				target_url: task.targetUrl,
				source_lang: task.sourceLang
			},
			success: function(response) {
				updateRowStatus(task.rowId, response);
			},
			error: function() {
				updateRowStatus(task.rowId, {
					status: 'Error',
					errors: ['Validation request failed.']
				});
			},
			complete: function() {
				callback();
			}
		});
	}

	function updateRowStatus(rowId, result) {
		const $row = $('#' + rowId);
		const $statusCell = $row.find('.status-cell');
		const $detailsCell = $row.find('.details-cell');

		let hasErrors = false;
		let errorHtml = '';
		let warningHtml = '';

		if (result.errors && result.errors.length > 0) {
			hasErrors = true;
			errorHtml = '<ul class="hreflang-details-errors"><li>' + result.errors.map(escapeHtml).join('</li><li>') + '</li></ul>';
		}

		if (result.warnings && result.warnings.length > 0) {
			warningHtml = '<ul class="hreflang-details-warnings"><li>' + result.warnings.map(escapeHtml).join('</li><li>') + '</li></ul>';
		}

		// Self-referencing check
		if (!result.is_self) {
			// Backend checks for return tags. If it's a self-ref, return tag doesn't make sense the same way, but let's check missing self tag on a global level.
		}

		if (hasErrors) {
			$statusCell.html('<span class="hreflang-status-icon hreflang-status-error">✗</span>');
		} else {
			$statusCell.html('<span class="hreflang-status-icon hreflang-status-success">✓</span>');
		}

		let details = '';
		if (result.status && result.status !== 'Error') {
			details += `<strong>HTTP:</strong> ${escapeHtml(String(result.status))}<br>`;
		}

		if (!hasErrors && !warningHtml) {
			details += 'All checks passed.';
		}

		$detailsCell.html(details + errorHtml + warningHtml);
	}

	function escapeHtml(unsafe) {
		if (unsafe == null) return '';
		return (unsafe || '').toString()
			 .replace(/&/g, "&amp;")
			 .replace(/</g, "&lt;")
			 .replace(/>/g, "&gt;")
			 .replace(/"/g, "&quot;")
			 .replace(/'/g, "&#039;");
	}
});