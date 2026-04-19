jQuery(document).ready(function($) {

    // Cache DOM elements
    const $tabBtns = $('.serp-sim-tab-btn');
    const $tabContents = $('.serp-sim-tab-content');

    // Inputs
    const $inputUrl = $('#sim-input-url');
    const $inputSiteName = $('#sim-input-sitename');
    const $inputTitle = $('#sim-input-title');
    const $inputDesc = $('#sim-input-desc');
    const $inputBold = $('#sim-input-bold');

    // Checkboxes
    const $chkAi = $('#sim-check-ai');
    const $chkDate = $('#sim-check-date');
    const $chkRating = $('#sim-check-rating');
    const $chkAds = $('#sim-check-ads');
    const $chkMap = $('#sim-check-map');

    // Preview Elements
    const $previewUrl = $('#sim-preview-url');
    const $previewBreadcrumbs = $('#sim-preview-breadcrumbs');
    const $previewSiteName = $('#sim-preview-sitename');
    const $previewTitle = $('#sim-preview-title');
    const $previewDesc = $('#sim-preview-desc');

    const $previewAi = $('#sim-preview-ai');
    const $previewMap = $('#sim-preview-map');
    const $previewAdsLabel = $('#sim-preview-ads-label');
    const $previewDate = $('#sim-preview-date');
    const $previewRatingWrap = $('#sim-preview-rating-wrap');

    // Tab Switching Logic
    $tabBtns.on('click', function() {
        const target = $(this).data('tab');

        $tabBtns.removeClass('active');
        $(this).addClass('active');

        $tabContents.removeClass('active');
        $('#tab-' + target).addClass('active');

        // Refresh CodeMirror if it's the structured data tab
        if (target === 'structured-data' && window.simCodeMirror) {
            setTimeout(() => {
                window.simCodeMirror.refresh();
            }, 10);
        }
    });

    // Helper: Escape HTML
    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Bold Keywords Logic
    function applyBoldKeywords(text, keywordsStr) {
        if (!keywordsStr.trim()) return escapeHtml(text);

        let escapedText = escapeHtml(text);
        const keywords = keywordsStr.split(',').map(k => k.trim()).filter(k => k.length > 0);

        keywords.forEach(keyword => {
            // Escape regex special characters in keyword
            const safeKeyword = keyword.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
            const regex = new RegExp(`(${safeKeyword})`, 'gi');
            escapedText = escapedText.replace(regex, '<em>$1</em>'); // Google uses <em> for bolding in snippets
        });

        return escapedText;
    }

    // Update Basic Preview
    function updatePreview() {
        // Basic texts
        $previewSiteName.text($inputSiteName.val() || 'Example Site');
        $previewTitle.text($inputTitle.val() || 'Your Page Title Goes Here');

        // URL handling (fallback if breadcrumbs are not present)
        const urlVal = $inputUrl.val() || 'https://example.com';
        if ($previewBreadcrumbs.hasClass('sim-hidden') || $previewBreadcrumbs.is(':empty')) {
            $previewUrl.text(urlVal).removeClass('sim-hidden');
            $previewBreadcrumbs.addClass('sim-hidden');
        } else {
            $previewUrl.addClass('sim-hidden');
            $previewBreadcrumbs.removeClass('sim-hidden');
        }

        // Description with bolding
        const descText = $inputDesc.val() || 'This is an example meta description. It should be engaging and relevant to the page content.';
        const boldKeywords = $inputBold.val();
        $previewDesc.html(applyBoldKeywords(descText, boldKeywords));

        // Checkboxes toggles
        $chkAi.is(':checked') ? $previewAi.removeClass('sim-hidden') : $previewAi.addClass('sim-hidden');
        $chkMap.is(':checked') ? $previewMap.removeClass('sim-hidden') : $previewMap.addClass('sim-hidden');
        $chkAds.is(':checked') ? $previewAdsLabel.removeClass('sim-hidden') : $previewAdsLabel.addClass('sim-hidden');
        $chkDate.is(':checked') ? $previewDate.removeClass('sim-hidden') : $previewDate.addClass('sim-hidden');

        // Rating Checkbox Override
        if ($chkRating.is(':checked')) {
            $previewRatingWrap.removeClass('sim-hidden');
            // Reset to dummy if previously overridden by JSON-LD
            $previewRatingWrap.find('.sim-rating-stars').text('★★★★★');
            $previewRatingWrap.find('.sim-rating-score').text('5.0');
            $previewRatingWrap.find('.sim-rating-count').text('(99)');
        } else {
            // If not checked, we rely on JSON-LD parsing to show/hide it
            // We'll call the parse logic later. For now, just hide it if JSON hasn't shown it.
            // (The JSON parser will remove the sim-hidden class if it finds rating data)
            if (!$previewRatingWrap.data('from-json')) {
                $previewRatingWrap.addClass('sim-hidden');
            }
        }
    }

    // Bind events
    $('.sim-input-group input, .sim-input-group textarea').on('input', updatePreview);
    $('.serp-sim-checkboxes input[type="checkbox"]').on('change', updatePreview);

    // Initial update
    updatePreview();

    // --- JSON-LD Parsing & Visualization ---

    // Initialize CodeMirror
    if ($('#sim-input-jsonld').length) {
        window.simCodeMirror = CodeMirror.fromTextArea(document.getElementById('sim-input-jsonld'), {
            mode: "javascript",
            theme: "monokai",
            lineNumbers: true,
            matchBrackets: true,
            autoCloseBrackets: true
        });

        window.simCodeMirror.on('change', function(cm) {
            parseAndRenderJSONLD(cm.getValue());
        });
    }

    const $sdFeaturesContainer = $('#sim-preview-sd-features');

    function parseAndRenderJSONLD(jsonStr) {
        // Reset JSON-specific visual elements
        $previewBreadcrumbs.empty().addClass('sim-hidden');
        $sdFeaturesContainer.empty();
        $previewRatingWrap.data('from-json', false);

        // Only hide rating if the override checkbox is NOT checked
        if (!$chkRating.is(':checked')) {
            $previewRatingWrap.addClass('sim-hidden');
        }

        if (!jsonStr.trim()) {
            updatePreview(); // Re-sync basic preview based on input state
            return;
        }

        let parsedData;
        try {
            parsedData = JSON.parse(jsonStr);
        } catch (e) {
            // Invalid JSON, just return and let the user fix it
            updatePreview();
            return;
        }

        // Normalize to array to handle both single object and array of objects
        const items = Array.isArray(parsedData) ? parsedData : [parsedData];

        // Process items. A generic function to handle `@graph`
        function processItems(graphItems) {
            graphItems.forEach(item => {
                if (!item || !item['@type']) return;

                const type = item['@type'];

                // Breadcrumbs
                if (type === 'BreadcrumbList' && item.itemListElement) {
                    renderBreadcrumbs(item.itemListElement);
                }
                // FAQ
                else if (type === 'FAQPage' && item.mainEntity) {
                    renderFAQ(item.mainEntity);
                }
                // Review / AggregateRating
                else if ((type === 'Review' || type === 'Product' || type === 'Recipe' || type === 'SoftwareApplication' || type === 'Book' || type === 'Course' || type === 'CreativeWork' || type === 'Event' || type === 'LocalBusiness' || type === 'Organization') && (item.aggregateRating || item.reviewRating)) {
                    // if it has aggregate rating, show stars
                    const ratingObj = item.aggregateRating || item.reviewRating;
                    renderRating(ratingObj);

                    // Specific generic fallbacks for product/recipe if they have more info
                    if (type === 'Product' || type === 'Recipe') {
                        renderGenericFallback(item, type);
                    }
                }
                // Sitelinks Searchbox
                else if (type === 'WebSite' && item.potentialAction && item.potentialAction['@type'] === 'SearchAction') {
                    renderSitelinkSearchbox();
                }
                // Generic fallback for other common types
                else if (['Article', 'NewsArticle', 'BlogPosting', 'JobPosting', 'Event', 'VideoObject'].includes(type)) {
                    renderGenericFallback(item, type);
                }

                // Recursively check if there's a @graph inside
                if (item['@graph']) {
                    processItems(item['@graph']);
                }
            });
        }

        processItems(items);

        // After parsing, call update preview to ensure URL visibility logic is correct
        updatePreview();
    }

    function renderBreadcrumbs(itemListElement) {
        let breadcrumbHtml = '';
        itemListElement.forEach((li, index) => {
            const name = li.name || (li.item && li.item.name) || '';
            if (name) {
                breadcrumbHtml += ` › ${escapeHtml(name)}`;
            }
        });

        if (breadcrumbHtml) {
            $previewBreadcrumbs.html(breadcrumbHtml).removeClass('sim-hidden');
            $previewUrl.addClass('sim-hidden'); // Hide raw url
        }
    }

    function renderFAQ(mainEntity) {
        let faqHtml = '<div class="sim-sd-feature sim-faq-container">';
        // Google usually shows up to 2-3 in the snippet. We'll show up to 3.
        const limit = Math.min(mainEntity.length, 3);

        for (let i = 0; i < limit; i++) {
            const qItem = mainEntity[i];
            const q = qItem.name || '';
            const a = (qItem.acceptedAnswer && qItem.acceptedAnswer.text) || '';

            if (q) {
                faqHtml += `
                    <div class="sim-faq-item">
                        <div class="sim-faq-q">${escapeHtml(q)}</div>
                        <div class="sim-faq-a">${a}</div> <!-- Not escaping 'a' fully as it often contains HTML like links/lists -->
                    </div>
                `;
            }
        }
        faqHtml += '</div>';

        if (limit > 0) {
            $sdFeaturesContainer.append(faqHtml);

            // Add click event for accordion
            $('.sim-faq-item').off('click').on('click', function() {
                $(this).find('.sim-faq-a').slideToggle(200);
            });
        }
    }

    function renderRating(ratingObj) {
        // If the manual override is checked, don't overwrite it with JSON data
        if ($chkRating.is(':checked')) return;

        const ratingValue = parseFloat(ratingObj.ratingValue) || 0;
        const bestRating = parseFloat(ratingObj.bestRating) || 5;
        const ratingCount = ratingObj.ratingCount || ratingObj.reviewCount || 0;

        if (ratingValue > 0) {
            // Normalize rating to a 5-star scale for visual
            const percentage = (ratingValue / bestRating);
            const stars = Math.round(percentage * 5);
            const emptyStars = 5 - stars;
            const starText = '★'.repeat(stars) + '☆'.repeat(emptyStars);

            $previewRatingWrap.find('.sim-rating-stars').text(starText);
            $previewRatingWrap.find('.sim-rating-score').text(ratingValue.toFixed(1));

            if (ratingCount > 0) {
                $previewRatingWrap.find('.sim-rating-count').text(`(${ratingCount})`);
            } else {
                $previewRatingWrap.find('.sim-rating-count').text('');
            }

            $previewRatingWrap.removeClass('sim-hidden');
            $previewRatingWrap.data('from-json', true);
        }
    }

    function renderSitelinkSearchbox() {
        const html = `
            <div class="sim-sd-feature">
                <div style="display:flex; border:1px solid #dfe1e5; border-radius:24px; padding:8px 12px; align-items:center;">
                    <span style="color:#9aa0a6; margin-right:8px;">🔍</span>
                    <span style="color:#70757a; font-size:14px;">Search this site...</span>
                </div>
            </div>
        `;
        $sdFeaturesContainer.append(html);
    }

    function renderGenericFallback(item, type) {
        // Fallback for types that don't have a highly specific visual in our simulator yet
        // but we want to show they were detected.
        let summary = '';
        if (type === 'Product' && item.offers) {
             const price = item.offers.price || '';
             const currency = item.offers.priceCurrency || '';
             if (price) summary = `<br><b>Price:</b> ${price} ${currency}`;
        } else if (type === 'Recipe') {
             const time = item.prepTime || item.cookTime || item.totalTime || '';
             if (time) summary = `<br><b>Time:</b> ${time}`;
        } else if (type === 'Event') {
             const date = item.startDate || '';
             if (date) summary = `<br><b>Date:</b> ${date}`;
        }

        const html = `
            <div class="sim-sd-feature sim-sd-fallback">
                <b>Detected Structured Data:</b> ${type} ${summary}
            </div>
        `;
        $sdFeaturesContainer.append(html);
    }

    // Initial update
    updatePreview();

});