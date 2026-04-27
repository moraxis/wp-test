<?php
// Bootstrap WordPress environment mock for Playwright
require_once __DIR__ . '/tests/mocks/wordpress.php';

function plugin_dir_path($file) {
    return dirname($file) . '/';
}

function plugin_dir_url($file) {
    return '/hreflang-tags-tester/';
}

require_once __DIR__ . '/hreflang-tags-tester/hreflang-tags-tester.php';

// Generate template
?>
<!DOCTYPE html>
<html>
<head>
    <title>Frontend Verification</title>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include Plugin Assets -->
    <link rel="stylesheet" href="/hreflang-tags-tester/assets/style.css">
    <script src="/hreflang-tags-tester/assets/script.js"></script>
    <script>
        // Mock localization variables
        var hreflangTesterParams = {
            restUrl: '/mock-rest-url',
            nonce: 'mock_nonce'
        };

        // Mock AJAX calls
        var originalAjax = $.ajax;
        $.ajax = function(options) {
            if (options.url.indexOf('/mock-rest-url/parse') !== -1) {
                setTimeout(function() {
                    options.success({
                        type: 'page',
                        urls: [
                            {
                                url: 'https://example.com/en',
                                alternates: [
                                    { href: 'https://example.com/en', hreflang: 'en' },
                                    { href: 'https://example.com/fr', hreflang: 'fr' }
                                ]
                            }
                        ]
                    });
                }, 500);
                return;
            } else if (options.url.indexOf('/mock-rest-url/validate') !== -1) {
                setTimeout(function() {
                    options.success({
                        status: 200,
                        errors: [],
                        warnings: [],
                        lang_valid: true,
                        has_return: true,
                        is_self: options.data.source_url === options.data.target_url
                    });
                }, 1000);
                return;
            }
            return originalAjax.apply(this, arguments);
        };
    </script>
</head>
<body>
    <div style="padding: 50px;">
        <?php echo hreflang_tester_shortcode(); ?>
    </div>
</body>
</html>