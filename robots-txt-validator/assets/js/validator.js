jQuery(document).ready(function($) {
    if (typeof CodeMirror === 'undefined') {
        console.error('CodeMirror is not loaded.');
        return;
    }

    const textarea = document.getElementById('robots-editor');
    if (!textarea) return;

    // Initialize CodeMirror
    const editor = CodeMirror.fromTextArea(textarea, {
        lineNumbers: true,
        lineWrapping: true,
        theme: 'default'
    });

    const errorList = $('#robots-validation-errors');
    const noteList = $('#robots-validation-notes');

    const validGoogleDirectives = ['user-agent', 'allow', 'disallow', 'sitemap'];
    const otherSearchEngineDirectives = ['crawl-delay', 'host', 'clean-param']; // E.g., Bing, Yandex

    function validateContent() {
        const content = editor.getValue();
        const lines = content.split('\n');
        const errors = [];
        const notes = [];

        if (content.trim() === '') {
            renderErrors([{ line: '-', message: 'File is empty.' }]);
            renderNotes([]);
            return;
        }

        // Clear previous highlights
        editor.eachLine(function(line) {
            editor.removeLineClass(line, 'background', 'robots-highlight-error');
            editor.removeLineClass(line, 'background', 'robots-highlight-warning');
        });

        let currentUserAgent = null;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const trimmed = line.trim();
            const lineNum = i + 1;

            // Ignore empty lines and comments
            if (trimmed === '' || trimmed.startsWith('#')) {
                continue;
            }

            // A line with a comment at the end e.g. "Allow: / # comment"
            // We should split by # and take the first part
            const actualContent = trimmed.split('#')[0].trim();
            if (actualContent === '') continue;

            // Basic syntax check: Directive: Value
            const colonIndex = actualContent.indexOf(':');
            if (colonIndex === -1) {
                errors.push({ line: lineNum, message: 'Invalid syntax. Expected format is "Directive: Value".' });
                continue;
            }

            const directive = actualContent.substring(0, colonIndex).trim().toLowerCase();
            const value = actualContent.substring(colonIndex + 1).trim();

            if (directive === '') {
                 errors.push({ line: lineNum, message: 'Missing directive before colon.' });
                 continue;
            }

            if (directive === 'user-agent') {
                currentUserAgent = value;
                if (value === '') {
                    errors.push({ line: lineNum, message: 'User-agent cannot be empty.' });
                }
                continue;
            }

            if (directive === 'sitemap') {
                if (value === '') {
                    errors.push({ line: lineNum, message: 'Sitemap URL cannot be empty.' });
                } else if (!/^https?:\/\//i.test(value)) {
                    errors.push({ line: lineNum, message: 'Sitemap must be an absolute URL starting with http:// or https://' });
                }
                continue;
            }

            // Any rule other than User-Agent or Sitemap must belong to a User-Agent block
            if (!currentUserAgent && directive !== 'sitemap') {
                 errors.push({ line: lineNum, message: `Directive "${directive}" must be preceded by a User-agent directive.` });
                 continue; // Proceed to check the directive itself anyway
            }

            if (!validGoogleDirectives.includes(directive)) {
                if (otherSearchEngineDirectives.includes(directive)) {
                    notes.push({ line: lineNum, message: `Directive "${directive}" is supported by some search engines (like Bing or Yandex) but is officially ignored by Google.` });
                } else {
                    errors.push({ line: lineNum, message: `Unknown directive "${directive}".` });
                }
            } else {
                // If it's Allow or Disallow, it should start with / or *
                if ((directive === 'allow' || directive === 'disallow') && value !== '') {
                    if (!value.startsWith('/') && !value.startsWith('*')) {
                         errors.push({ line: lineNum, message: `Path "${value}" must start with a forward slash (/) or an asterisk (*).` });
                    }
                }
            }
        }

        // Apply visual highlights to CodeMirror
        errors.forEach(err => {
            if (err.line && err.line !== '-') {
                editor.addLineClass(err.line - 1, 'background', 'robots-highlight-error');
            }
        });

        notes.forEach(note => {
             if (note.line && note.line !== '-') {
                 // only add warning highlight if it doesn't already have an error highlight
                 const lineInfo = editor.lineInfo(note.line - 1);
                 if (!lineInfo.bgClass || !lineInfo.bgClass.includes('robots-highlight-error')) {
                     editor.addLineClass(note.line - 1, 'background', 'robots-highlight-warning');
                 }
             }
        });

        renderErrors(errors);
        renderNotes(notes);
    }

    function renderErrors(errors) {
        errorList.empty();

        if (errors.length === 0) {
            errorList.append('<li class="robots-no-errors">&check; All syntax checks passed!</li>');
        } else {
            errors.forEach(err => {
                const li = $('<li></li>');
                const span = $('<span class="robots-error-line"></span>').text(`Line ${err.line}: `);
                li.append(span).append(document.createTextNode(err.message));
                errorList.append(li);
            });
        }
    }

    function renderNotes(notes) {
        noteList.empty();

        if (notes.length === 0) {
            noteList.append('<li class="robots-no-notes">No warnings or notes.</li>');
        } else {
            notes.forEach(note => {
                const li = $('<li></li>');
                const span = $('<span class="robots-error-line"></span>').text(`Line ${note.line}: `);
                li.append(span).append(document.createTextNode(note.message));
                noteList.append(li);
            });
        }
    }

    // Bind change event to validate real-time
    editor.on('change', function() {
        validateContent();
        $('#robots-test-result').hide(); // Hide test result when content changes
    });

    // Handle Fetch button
    $('#robots-fetch-btn').on('click', function() {
        const url = $('#robots-fetch-url').val().trim();
        const errorDiv = $('#robots-fetch-error');

        errorDiv.hide().text('');

        if (!url) {
            errorDiv.text('Please enter a URL.').show();
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text('Fetching...');

        $.ajax({
            url: robotsValidatorConfig.restUrl,
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

    // Custom Agent Dropdown toggle
    $('#robots-test-agent').on('change', function() {
        if ($(this).val() === 'Other') {
            $('#robots-test-agent-custom').show();
        } else {
            $('#robots-test-agent-custom').hide();
        }
    });

    // URL Tester Logic
    $('#robots-test-btn').on('click', function() {
        const path = $('#robots-test-path').val().trim();
        let agent = $('#robots-test-agent').val();

        if (agent === 'Other') {
            agent = $('#robots-test-agent-custom').val().trim();
        }

        if (!path) {
            alert('Please enter a path to test.');
            return;
        }

        if (!agent) {
             alert('Please enter a custom User-Agent.');
             return;
        }

        const isAllowed = evaluateRobotsTxt(editor.getValue(), agent, path);

        const resultDiv = $('#robots-test-result');
        resultDiv.removeClass('robots-test-allowed robots-test-disallowed');

        if (isAllowed) {
            resultDiv.text('ALLOWED').addClass('robots-test-allowed').show();
        } else {
            resultDiv.text('DISALLOWED').addClass('robots-test-disallowed').show();
        }
    });

    // Google's robots.txt matching logic approximation
    function evaluateRobotsTxt(content, userAgent, path) {
        const lines = content.split('\n');

        let targetGroupRules = [];
        let anyGroupRules = [];

        let currentAgents = [];
        let currentRules = [];

        // Parse the file into groups
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            line = line.split('#')[0].trim(); // Remove comments
            if (line === '') continue;

            const colonIndex = line.indexOf(':');
            if (colonIndex === -1) continue;

            const directive = line.substring(0, colonIndex).trim().toLowerCase();
            const value = line.substring(colonIndex + 1).trim();

            if (directive === 'user-agent') {
                // If we hit a user-agent but already have rules, that means we are starting a new group
                if (currentRules.length > 0) {
                    // Save previous group
                    saveGroup(currentAgents, currentRules, targetGroupRules, anyGroupRules, userAgent);
                    currentAgents = [];
                    currentRules = [];
                }
                currentAgents.push(value.toLowerCase());
            } else if (directive === 'allow' || directive === 'disallow') {
                currentRules.push({ type: directive, pattern: value });
            }
        }

        // Save the last group
        if (currentAgents.length > 0) {
            saveGroup(currentAgents, currentRules, targetGroupRules, anyGroupRules, userAgent);
        }

        // Google logic: Use the most specific User-Agent group.
        // If there's a matching specific agent, use ONLY its rules. Otherwise, use '*' rules.
        let rulesToEvaluate = targetGroupRules.length > 0 ? targetGroupRules : anyGroupRules;

        // If no rules apply, default is ALLOWED
        if (rulesToEvaluate.length === 0) {
            return true;
        }

        // Find the rule with the longest matching pattern length
        // In case of a tie between Allow and Disallow, Allow wins.
        let longestMatchLength = -1;
        let finalDecision = true; // Default to allow

        for (const rule of rulesToEvaluate) {
            // An empty Disallow means Allow all
            if (rule.type === 'disallow' && rule.pattern === '') continue;

            // Empty allow is usually ignored, but if present it matches nothing
            if (rule.type === 'allow' && rule.pattern === '') continue;

            if (patternMatchesPath(rule.pattern, path)) {
                let matchLength = rule.pattern.length;

                if (matchLength > longestMatchLength) {
                    longestMatchLength = matchLength;
                    finalDecision = (rule.type === 'allow');
                } else if (matchLength === longestMatchLength) {
                    if (rule.type === 'allow') {
                        finalDecision = true; // Allow overrides Disallow on tie
                    }
                }
            }
        }

        return finalDecision;
    }

    function saveGroup(agents, rules, targetGroupRules, anyGroupRules, targetAgent) {
        const lowerTargetAgent = targetAgent.toLowerCase();

        let isTargetMatch = false;
        let isAnyMatch = false;

        for (const agent of agents) {
            if (agent === '*') {
                isAnyMatch = true;
            } else if (lowerTargetAgent.includes(agent)) {
                // E.g., target "Googlebot-Image" includes "googlebot"
                isTargetMatch = true;
            }
        }

        if (isTargetMatch) {
            targetGroupRules.push(...rules);
        } else if (isAnyMatch) {
            anyGroupRules.push(...rules);
        }
    }

    function patternMatchesPath(pattern, path) {
        // Standardize path
        if (!path.startsWith('/')) path = '/' + path;

        // Escape regex special chars except * and $
        let regexStr = pattern.replace(/[\-\[\]\/\{\}\(\)\+\?\.\\\^\|]/g, "\\$&");

        // Replace * with .*
        regexStr = regexStr.replace(/\*/g, '.*');

        // Handle $ at the end
        if (regexStr.endsWith('$')) {
            regexStr = regexStr.slice(0, -1) + '$';
        } else {
            // If it doesn't end with $, it's a prefix match
            // but we append .* so it matches anything after
             regexStr = regexStr + '.*';
        }

        const regex = new RegExp('^' + regexStr);
        return regex.test(path);
    }

    // Initial validation
    validateContent();
});
