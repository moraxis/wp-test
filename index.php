<?php
// Mock setup for frontend verification
define('ABSPATH', true);
require_once __DIR__ . '/tests/mocks/wordpress.php';
require_once __DIR__ . '/live-header-checker/live-header-checker.php';

// Serve API requests locally
if ($_SERVER['REQUEST_URI'] === '/wp-json/live-header-checker/v1/check') {
    $request = new WP_REST_Request($_POST);
    $response = live_header_checker_api_callback($request);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Frontend Verification</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="/live-header-checker/assets/style.css">
    <script>
        var liveHeaderCheckerData = {
            restUrl: '/wp-json/live-header-checker/v1/check',
            nonce: 'mock_nonce'
        };
    </script>
    <script src="/live-header-checker/assets/script.js"></script>
    <style>
        body { background: #f0f0f0; padding: 50px; }
    </style>
</head>
<body>
    <?php echo live_header_checker_shortcode(array()); ?>
</body>
</html>
