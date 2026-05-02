document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('iaa-form');
	const urlInput = document.getElementById('iaa-url');
	const submitBtn = document.getElementById('iaa-submit');
	const loadingEl = document.getElementById('iaa-loading');
	const errorEl = document.getElementById('iaa-error');
	const resultsContainer = document.getElementById('iaa-results-container');
	const resultsBody = document.getElementById('iaa-results-body');
	const resultsSummary = document.getElementById('iaa-results-summary');

	if (!form) return;

	form.addEventListener('submit', function(e) {
		e.preventDefault();
		const url = urlInput.value.trim();
		if (!url) return;

		// Reset UI
		errorEl.style.display = 'none';
		resultsContainer.style.display = 'none';
		loadingEl.style.display = 'block';
		submitBtn.disabled = true;
		resultsBody.innerHTML = '';

		fetch(imageAltAuditorData.rest_url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': imageAltAuditorData.nonce
			},
			body: JSON.stringify({ url: url })
		})
		.then(response => response.json())
		.then(data => {
			loadingEl.style.display = 'none';
			submitBtn.disabled = false;

			if (!data.success) {
				showError(data.message || 'An unknown error occurred while fetching the URL.');
				return;
			}

			renderResults(data.findings, url);
		})
		.catch(error => {
			loadingEl.style.display = 'none';
			submitBtn.disabled = false;
			showError('A network error occurred. Please try again.');
			console.error('Audit error:', error);
		});
	});

	function showError(message) {
		errorEl.innerHTML = `<p><strong>Error:</strong> ${message}</p>`;
		errorEl.style.display = 'block';
	}

	function renderResults(findings, url) {
		resultsContainer.style.display = 'block';

		if (!findings || findings.length === 0) {
			resultsSummary.textContent = `Great job! No missing alt attributes or background images were found on ${url}.`;
			resultsSummary.style.color = '#2d222d';
			return;
		}

		resultsSummary.textContent = `Found ${findings.length} issue(s) on ${url}:`;

		findings.forEach(finding => {
			const tr = document.createElement('tr');

			// Image Cell
			const tdImg = document.createElement('td');
			if (finding.type === 'img') {
				const img = document.createElement('img');
				img.src = finding.url;
				img.className = 'iaa-thumbnail';
				img.alt = 'Thumbnail';
				img.onerror = function() {
					this.outerHTML = '<div class="iaa-no-image">Broken</div>';
				};
				tdImg.appendChild(img);
			} else {
				// For background images, might be hard to load directly if they are generic, but we try
				const img = document.createElement('img');
				img.src = finding.url;
				img.className = 'iaa-thumbnail';
				img.alt = 'BG Thumbnail';
				img.onerror = function() {
					this.outerHTML = '<div class="iaa-no-image">CSS BG</div>';
				};
				tdImg.appendChild(img);
			}
			tr.appendChild(tdImg);

			// URL Cell
			const tdUrl = document.createElement('td');
			const aUrl = document.createElement('a');
			aUrl.href = finding.url;
			aUrl.target = '_blank';
			aUrl.rel = 'noopener noreferrer';
			aUrl.className = 'iaa-url-link';
			aUrl.textContent = finding.url;
			tdUrl.appendChild(aUrl);
			tr.appendChild(tdUrl);

			// Issue Cell
			const tdIssue = document.createElement('td');
			const spanIssue = document.createElement('span');
			spanIssue.className = 'iaa-issue-badge';
			spanIssue.textContent = finding.issue;

			// Adjust badge color slightly if it's a manual review
			if (finding.issue.includes('manual review')) {
				spanIssue.style.backgroundColor = '#493284'; // Heading color for info
			}

			tdIssue.appendChild(spanIssue);
			tr.appendChild(tdIssue);

			resultsBody.appendChild(tr);
		});
	}
});