jQuery(document).ready(function($) {
    // Ensure CodeMirror is loaded
    if (typeof CodeMirror === 'undefined') {
        console.error('CodeMirror is not loaded.');
        return;
    }

    const textarea = document.getElementById('llms-editor');
    if (!textarea) return;

    // Initialize CodeMirror
    const editor = CodeMirror.fromTextArea(textarea, {
        mode: 'markdown',
        lineNumbers: true,
        lineWrapping: true,
        theme: 'default'
    });

    const errorList = $('#llms-validation-errors');

    const headingRegex = /^(#+)\s+(.*)/;
    const linkRegex = /\[([^\]]+)\]\(([^)]+)\)/;

    // Validation logic
    function validateContent() {
        const content = editor.getValue();
        const lines = content.split('\n');
        const errors = [];

        if (content.trim() === '') {
            renderErrors([{ line: '-', message: 'File is empty.' }]);
            return;
        }

        let hasH1 = false;
        let h1Count = 0;
        let firstNonEmptyLineIndex = -1;

        // Clear previous highlights
        editor.eachLine(function(line) {
            editor.removeLineClass(line, 'background', 'llms-highlight-error');
        });

        let currentSection = null;
        let hasBlockquote = false;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const trimmed = line.trim();
            const lineNum = i + 1;

            if (trimmed === '') continue;

            // Track the first non-empty line index to ensure H1 is at the top
            if (firstNonEmptyLineIndex === -1) {
                firstNonEmptyLineIndex = i;
            }

            // Check headings
            if (trimmed.startsWith('#')) {
                const match = trimmed.match(headingRegex);
                if (match) {
                    const level = match[1].length;

                    if (level === 1) {
                        h1Count++;
                        if (h1Count > 1) {
                            errors.push({ line: lineNum, message: 'Only one H1 (# Title) is allowed, and it must be at the very beginning.' });
                        }
                        if (i !== firstNonEmptyLineIndex) {
                            errors.push({ line: lineNum, message: 'The H1 (# Title) must be the first section in the file.' });
                        }
                        hasH1 = true;
                        currentSection = 'h1';
                    } else if (level === 2) {
                        currentSection = 'h2';
                    } else if (level > 2) {
                        errors.push({ line: lineNum, message: `Heading level H${level} is not allowed. Only H1 and H2 are permitted.` });
                    }
                } else {
                     // Starts with # but no space
                     errors.push({ line: lineNum, message: 'Malformed heading. Make sure there is a space after the `#`.' });
                }
            } else if (trimmed.startsWith('>')) {
                // We expect a blockquote shortly after H1
                hasBlockquote = true;
            } else if (trimmed.startsWith('-') || trimmed.startsWith('*')) {
                // List items inside an H2 section should contain a markdown link
                if (currentSection === 'h2') {
                    // It should contain a link [name](url)
                    // The spec says: "Each 'file list' is a markdown list, containing a required markdown hyperlink [name](url), then optionally a : and notes"
                    if (!linkRegex.test(trimmed)) {
                        errors.push({ line: lineNum, message: 'List items under an H2 section must contain a valid Markdown link [name](url).' });
                    }
                }
            }
        }

        if (!hasH1) {
            errors.push({ line: 1, message: 'The file must contain exactly one H1 (# Title) as the first section.' });
        }

        // Technically the spec says: "A blockquote with a short summary of the project...". We will check if it exists at all.
        if (hasH1 && !hasBlockquote) {
             errors.push({ line: 2, message: 'Expected a blockquote (> short summary) describing the project, usually after the H1 title.' });
        }

        // Apply visual highlights to CodeMirror
        errors.forEach(err => {
            if (err.line && err.line !== '-' && err.line > 0) {
                // CodeMirror lines are 0-indexed
                editor.addLineClass(err.line - 1, 'background', 'llms-highlight-error');
            }
        });

        renderErrors(errors);
    }

    function renderErrors(errors) {
        errorList.empty();

        if (errors.length === 0) {
            errorList.append('<li class="llms-no-errors">✓ All checks passed! Your llms.txt looks good.</li>');
        } else {
            errors.forEach(err => {
                errorList.append(`<li><span class="llms-error-line">Line ${err.line}:</span> ${err.message}</li>`);
            });
        }
    }

    // Bind change event to validate real-time
    editor.on('change', function() {
        validateContent();
    });

    // Handle Fetch button
    $('#llms-fetch-btn').on('click', function() {
        const url = $('#llms-fetch-url').val().trim();
        const errorDiv = $('#llms-fetch-error');

        errorDiv.hide().text('');

        if (!url) {
            errorDiv.text('Please enter a URL.').show();
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text('Fetching...');

        $.ajax({
            url: llmsValidatorConfig.restUrl,
            method: 'GET',
            data: { url: url },
            success: function(response) {
                btn.prop('disabled', false).text('Fetch URL');
                if (response.success) {
                    editor.setValue(response.content);
                } else {
                    errorDiv.text('An error occurred.').show();
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).text('Fetch URL');
                let msg = 'Failed to fetch the URL.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                errorDiv.text(msg).show();
            }
        });
    });

    // Initial validation
    validateContent();
});
