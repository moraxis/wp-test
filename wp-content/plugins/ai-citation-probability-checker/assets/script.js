document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.getElementById('ai-citation-url');
    const fetchBtn = document.getElementById('ai-citation-fetch-btn');
    const editor = document.getElementById('ai-citation-editor');
    const testAgainBtn = document.getElementById('ai-citation-test-again-btn');
    const loadingEl = document.getElementById('ai-citation-loading');
    const resultsEl = document.getElementById('ai-citation-results');
    const scoreEl = document.getElementById('ai-citation-score');
    const suggestionsEl = document.getElementById('ai-citation-suggestions');

    // Hoisted Regular Expressions for performance
    const sentenceSplitRegex = /[.?!]+(?:\s+|$)/;
    const paragraphSplitRegex = /\n\s*\n/;
    const listRegex = /^[-*•]|\d+\.\s/m; // Detects list items in a line
    const numberDateRegex = /\b\d{1,4}\b|january|february|march|april|may|june|july|august|september|october|november|december/i;
    const headingRegex = /^#{1,6}\s|[A-Z][^a-z]*[A-Z]$/m; // Markdown headings or all-caps lines

    let originalText = '';

    // Initial analysis if text is present
    if (editor.value.trim() !== '') {
        analyzeText(editor.value);
    }

    // Fetch URL Handler
    fetchBtn.addEventListener('click', async function() {
        const url = urlInput.value.trim();
        if (!url) {
            alert('Please enter a URL.');
            return;
        }

        try {
            fetchBtn.disabled = true;
            fetchBtn.textContent = 'Fetching...';
            editor.value = '';
            hideResults();
            loadingEl.style.display = 'block';
            testAgainBtn.classList.remove('active');

            const response = await fetch(aiCitationData.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiCitationData.nonce
                },
                body: JSON.stringify({ url: url })
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.code === 'rate_limit_exceeded') {
                    alert('You reached your limit, try again in an hour.');
                } else {
                    alert(data.message || 'An error occurred while fetching the URL.');
                }
                loadingEl.style.display = 'none';
                return;
            }

            if (data.success && data.text) {
                editor.value = data.text;
                originalText = data.text;
                analyzeText(data.text);
            } else {
                alert('Could not extract text from the URL.');
                loadingEl.style.display = 'none';
            }

        } catch (error) {
            console.error('Fetch error:', error);
            alert('A network error occurred.');
            loadingEl.style.display = 'none';
        } finally {
            fetchBtn.disabled = false;
            fetchBtn.textContent = 'Fetch & Analyze';
        }
    });

    // Editor Event Listener for "Test Again" button
    editor.addEventListener('input', function() {
        if (editor.value !== originalText) {
            testAgainBtn.classList.add('active');
        } else {
            testAgainBtn.classList.remove('active');
        }
    });

    // Test Again Handler
    testAgainBtn.addEventListener('click', function() {
        testAgainBtn.classList.remove('active');
        originalText = editor.value;
        analyzeText(editor.value);
    });

    function hideResults() {
        resultsEl.style.display = 'none';
        scoreEl.textContent = '0%';
        suggestionsEl.innerHTML = '';
    }

    function analyzeText(text) {
        loadingEl.style.display = 'none';

        if (!text.trim()) {
            hideResults();
            return;
        }

        resultsEl.style.display = 'block';

        const paragraphs = text.split(paragraphSplitRegex).filter(p => p.trim() !== '');
        const sentences = text.split(sentenceSplitRegex).filter(s => s.trim() !== '');
        const words = text.match(/\b\w+\b/g) || [];

        let score = 100;
        let suggestions = [];

        // 1. Sentence Length (AI prefers concise sentences)
        const avgSentenceLength = words.length / (sentences.length || 1);
        if (avgSentenceLength > 20) {
            score -= 15;
            suggestions.push({
                type: 'bad',
                text: `Sentences are too long (avg ${Math.round(avgSentenceLength)} words). Break them down into shorter sentences (aim for under 20 words) for better AI comprehension.`
            });
        } else if (avgSentenceLength > 0) {
            suggestions.push({
                type: 'good',
                text: 'Good sentence length! Concise sentences make it easier for AI to parse facts.'
            });
        }

        // 2. Paragraph Length (AI parses short chunks better)
        let overlyLongParagraphs = 0;
        paragraphs.forEach(p => {
            const pSentences = p.split(sentenceSplitRegex).filter(s => s.trim() !== '');
            if (pSentences.length > 5) overlyLongParagraphs++;
        });

        if (overlyLongParagraphs > 0) {
            score -= (10 + (overlyLongParagraphs * 2));
            suggestions.push({
                type: 'bad',
                text: `Found ${overlyLongParagraphs} long paragraph(s). Try keeping paragraphs under 4-5 sentences to create distinct factual chunks.`
            });
        } else if (paragraphs.length > 0) {
            suggestions.push({
                type: 'good',
                text: 'Paragraph structure is good. Short paragraphs help AI isolate concepts.'
            });
        }

        // 3. Structure: Lists and Bullet Points
        const hasLists = listRegex.test(text);
        if (!hasLists) {
            score -= 15;
            suggestions.push({
                type: 'bad',
                text: 'No bulleted or numbered lists found. AI engines excel at extracting facts from structured lists. Consider formatting key takeaways as a list.'
            });
        } else {
            score += 5; // Bonus
            suggestions.push({
                type: 'good',
                text: 'Contains structured lists! This highly improves fact extraction probability.'
            });
        }

        // 4. Fact Density: Numbers and Dates
        const hasFacts = numberDateRegex.test(text);
        if (!hasFacts) {
            score -= 10;
            suggestions.push({
                type: 'bad',
                text: 'Low density of specific numbers or dates. Adding concrete data points makes your content more likely to be cited as a source of truth.'
            });
        } else {
            suggestions.push({
                type: 'good',
                text: 'Contains specific data points (numbers/dates). AI looks for concrete facts to cite.'
            });
        }

        // 5. Structure: Headings (Approximated if plain text)
        const hasHeadings = headingRegex.test(text);
        if (!hasHeadings && paragraphs.length > 3) {
            score -= 10;
            suggestions.push({
                type: 'bad',
                text: 'Consider adding subheadings. They act as signposts that help AI understand the hierarchy and context of the information.'
            });
        }

        // Finalize Score
        score = Math.max(0, Math.min(100, score));

        // Update UI
        scoreEl.textContent = `${score}%`;

        // Color coding score
        if (score >= 80) {
            scoreEl.style.borderColor = '#4CAF50'; // Green
            scoreEl.style.color = '#4CAF50';
        } else if (score >= 50) {
            scoreEl.style.borderColor = '#f99417'; // Orange
            scoreEl.style.color = '#f99417';
        } else {
            scoreEl.style.borderColor = '#f44336'; // Red
            scoreEl.style.color = '#f44336';
        }

        // Render Suggestions
        suggestionsEl.innerHTML = '';
        suggestions.forEach(s => {
            const div = document.createElement('div');
            div.className = `ai-citation-suggestion-item ${s.type}`;
            div.textContent = s.text;
            suggestionsEl.appendChild(div);
        });
    }
});
