jQuery(document).ready(function($) {
    const $form = $('.lhc-form');
    const $urlInput = $('#lhc-url');
    const $uaSelect = $('#lhc-user-agent');
    const $checkBtn = $('#lhc-check-btn');
    const $results = $('#lhc-results');

    $checkBtn.on('click', function(e) {
        e.preventDefault();

        const url = $urlInput.val().trim();
        const ua = $uaSelect.val();

        if (!url) {
            alert('Please enter a URL.');
            return;
        }

        // Add basic http/https if missing
        let processUrl = url;
        if (!/^https?:\/\//i.test(processUrl)) {
            processUrl = 'http://' + processUrl;
            $urlInput.val(processUrl);
        }

        // Disable button and show loading
        $checkBtn.prop('disabled', true).text('Checking...');
        $results.html('<div class="lhc-loading"><div class="lhc-spinner"></div></div>');

        $.ajax({
            url: liveHeaderCheckerData.restUrl,
            method: 'POST',
            data: {
                url: processUrl,
                user_agent: ua
            },
            headers: {
                'X-WP-Nonce': liveHeaderCheckerData.nonce
            },
            success: function(response) {
                $checkBtn.prop('disabled', false).text('Check');

                if (response.success && response.chain) {
                    renderResults(response.chain);
                } else if (response.message) {
                    renderError(response.message);
                } else {
                    renderError('An unknown error occurred.');
                }
            },
            error: function(xhr) {
                $checkBtn.prop('disabled', false).text('Check');
                let errorMsg = 'Failed to connect to the server.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                renderError(errorMsg);
            }
        });
    });

    // Handle accordion toggle
    $results.on('click', '.lhc-step-header', function() {
        $(this).parent('.lhc-step').toggleClass('expanded');
    });

    function getStatusClass(status) {
        if (status === 'Error') return 'status-error';
        const code = parseInt(status, 10);
        if (code === 200) return 'status-200';
        if ([301, 302, 307, 308].includes(code)) return 'status-301';
        if (code >= 400 && code < 500) return 'status-404';
        if (code >= 500) return 'status-500';
        return 'status-other';
    }

    function renderError(message) {
        $results.html('<div class="lhc-error-message"><strong>Error:</strong> ' + escapeHtml(message) + '</div>');
    }

    function renderResults(chain) {
        $results.empty();

        if (chain.length === 0) {
            $results.html('<p>No data returned.</p>');
            return;
        }

        chain.forEach((step, index) => {
            const statusClass = getStatusClass(step.status);
            const isLast = index === chain.length - 1;

            // Build the step container
            const $step = $('<div class="lhc-step"></div>');

            // By default expand the last step or steps with errors
            if (isLast || step.status === 'Error') {
                $step.addClass('expanded');
            }

            // Header
            const headerHtml = `
                <div class="lhc-step-header">
                    <div class="lhc-step-info">
                        <span class="lhc-status ${statusClass}">${escapeHtml(String(step.status))}</span>
                        <span class="lhc-url-text">${escapeHtml(step.url)}</span>
                    </div>
                    <div class="lhc-toggle-icon">▼</div>
                </div>
            `;
            $step.append(headerHtml);

            // Body
            const $body = $('<div class="lhc-step-body"></div>');

            if (step.status === 'Error') {
                $body.append('<div class="lhc-error-message">' + escapeHtml(step.error) + '</div>');
            } else {
                // Render important headers first
                const importantKeys = ['x-robots-tag', 'vary', 'cache-control', 'location', 'server'];
                let importantHtml = '<div class="lhc-important-headers"><strong>Important Headers:</strong><br><br>';
                let hasImportant = false;

                // Render all headers
                let allHtml = '<div class="lhc-all-headers"><strong>All Headers:</strong><br><br>';

                if (step.headers && Object.keys(step.headers).length > 0) {
                    for (const [key, value] of Object.entries(step.headers)) {
                        const lowerKey = key.toLowerCase();
                        const rowHtml = `
                            <div class="lhc-header-row">
                                <span class="lhc-header-key">${escapeHtml(key)}:</span>
                                <span class="lhc-header-value">${escapeHtml(String(value))}</span>
                            </div>
                        `;

                        allHtml += rowHtml;

                        if (importantKeys.includes(lowerKey)) {
                            importantHtml += rowHtml;
                            hasImportant = true;
                        }
                    }
                } else {
                    allHtml += '<p>No headers found.</p>';
                }

                importantHtml += '</div>';
                allHtml += '</div>';

                if (hasImportant) {
                    $body.append(importantHtml);
                }
                $body.append(allHtml);
            }

            $step.append($body);
            $results.append($step);
        });
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').replace(/[&<"'>]/g, function (match) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return map[match];
        });
    }
});
