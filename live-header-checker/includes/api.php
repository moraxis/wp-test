<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('live-header-checker/v1', '/check', array(
        'methods' => 'POST',
        'callback' => 'live_header_checker_api_callback',
        'permission_callback' => '__return_true' // Public API since we want it publicly available
    ));
});

function live_header_checker_get_user_agent($ua_key) {
    switch ($ua_key) {
        case 'googlebot-desktop':
            return 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        case 'googlebot-smartphone':
            return 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/W.X.Y.Z Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        case 'bingbot':
            return 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
        case 'default':
        default:
            return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    }
}

function live_header_checker_api_callback(WP_REST_Request $request) {
    $url = $request->get_param('url');
    $ua_key = $request->get_param('user_agent');

    if (empty($url)) {
        return new WP_Error('missing_url', 'URL is required', array('status' => 400));
    }

    // Simple basic validation
    $url = esc_url_raw($url);
    if (!wp_http_validate_url($url)) {
        return new WP_Error('invalid_url', 'Invalid URL provided', array('status' => 400));
    }

    $user_agent = live_header_checker_get_user_agent($ua_key);
    $max_redirects = 5;
    $chain = array();
    $current_url = $url;

    for ($i = 0; $i < $max_redirects + 1; $i++) {
        $args = array(
            'redirection' => 0, // We handle redirection manually to capture headers
            'user-agent' => $user_agent,
            'limit_response_size' => 1048576, // 1MB limit to prevent DoS
            'timeout' => 15
        );

        $response = wp_safe_remote_get($current_url, $args);

        if (is_wp_error($response)) {
            $chain[] = array(
                'url' => $current_url,
                'status' => 'Error',
                'error' => $response->get_error_message(),
                'headers' => array()
            );
            break;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);

        // Format headers into a simple key-value array for frontend
        $formatted_headers = array();
        if (is_object($headers) && method_exists($headers, 'getAll')) {
            $formatted_headers = $headers->getAll();
        } elseif (is_array($headers) || is_object($headers)) {
            foreach ($headers as $key => $value) {
                if (is_array($value)) {
                    $formatted_headers[$key] = implode(', ', $value);
                } else {
                    $formatted_headers[$key] = $value;
                }
            }
        }

        $chain[] = array(
            'url' => $current_url,
            'status' => $status_code,
            'headers' => $formatted_headers
        );

        // Check if we need to redirect
        $location = wp_remote_retrieve_header($response, 'location');
        if (in_array($status_code, array(301, 302, 303, 307, 308)) && !empty($location)) {
            // Handle relative locations
            if (strpos($location, 'http') !== 0) {
                $parsed_url = wp_parse_url($current_url);
                $base = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                if (isset($parsed_url['port'])) {
                    $base .= ':' . $parsed_url['port'];
                }

                if (strpos($location, '/') === 0) {
                    $location = $base . $location;
                } else {
                    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '/';
                    $dir = dirname($path);
                    if ($dir === '/' || $dir === '\\') {
                        $dir = '';
                    }
                    $location = $base . $dir . '/' . $location;
                }
            }
            $current_url = $location;

            if ($i == $max_redirects - 1) {
                // Next iteration will be the max + 1 (the final one), let's mark it as too many redirects
                $chain[] = array(
                    'url' => $current_url,
                    'status' => 'Error',
                    'error' => 'Too many redirects (max 5)',
                    'headers' => array()
                );
                break;
            }
        } else {
            // No redirect, stop loop
            break;
        }
    }

    return rest_ensure_response(array('success' => true, 'chain' => $chain));
}
