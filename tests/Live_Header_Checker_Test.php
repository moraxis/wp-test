<?php

class Live_Header_Checker_Test {
    public function run($runner) {
        $this->test_successful_fetch($runner);
        $this->test_redirect_chain($runner);
        $this->test_too_many_redirects($runner);
        $this->test_invalid_url($runner);
    }

    protected function assert($condition, $message) {
        if (!$condition) {
            throw new Exception($message);
        }
    }

    private function test_successful_fetch($runner) {
        global $mock_remote_get_responses;

        $mock_remote_get_responses['http://example.com'] = array(
            'response' => array('code' => 200),
            'headers' => array(
                'content-type' => 'text/html',
                'x-robots-tag' => 'noindex'
            )
        );

        $request = new WP_REST_Request(array('url' => 'http://example.com', 'user_agent' => 'default'));
        $response = live_header_checker_api_callback($request);

        try {
            $this->assert(isset($response['success']) && $response['success'] === true, 'Response should be successful');
            $this->assert(count($response['chain']) === 1, 'Chain should have 1 item');
            $this->assert($response['chain'][0]['status'] === 200, 'Status should be 200');
            $this->assert(isset($response['chain'][0]['headers']['content-type']), 'Should have content-type header');

            $runner->recordPass('live_header_checker_api_callback handles successful 200 fetch');
        } catch (Exception $e) {
            $runner->recordFail('live_header_checker_api_callback failed: ' . $e->getMessage());
        }
    }

    private function test_redirect_chain($runner) {
        global $mock_remote_get_responses;

        $mock_remote_get_responses['http://redirect.com'] = array(
            'response' => array('code' => 301),
            'headers' => array(
                'location' => 'http://redirect.com/step2',
                'cache-control' => 'no-cache'
            )
        );

        $mock_remote_get_responses['http://redirect.com/step2'] = array(
            'response' => array('code' => 302),
            'headers' => array(
                'location' => 'http://final.com',
                'vary' => 'User-Agent'
            )
        );

        $mock_remote_get_responses['http://final.com'] = array(
            'response' => array('code' => 200),
            'headers' => array(
                'content-type' => 'text/html'
            )
        );

        $request = new WP_REST_Request(array('url' => 'http://redirect.com', 'user_agent' => 'default'));
        $response = live_header_checker_api_callback($request);

        try {
            $this->assert(isset($response['success']) && $response['success'] === true, 'Response should be successful');
            $this->assert(count($response['chain']) === 3, 'Chain should have 3 items');

            $this->assert($response['chain'][0]['status'] === 301, 'Step 1 should be 301');
            $this->assert($response['chain'][0]['url'] === 'http://redirect.com', 'Step 1 URL mismatch');

            $this->assert($response['chain'][1]['status'] === 302, 'Step 2 should be 302');
            $this->assert($response['chain'][1]['url'] === 'http://redirect.com/step2', 'Step 2 URL mismatch');

            $this->assert($response['chain'][2]['status'] === 200, 'Step 3 should be 200');
            $this->assert($response['chain'][2]['url'] === 'http://final.com', 'Step 3 URL mismatch');

            $runner->recordPass('live_header_checker_api_callback handles redirect chains properly');
        } catch (Exception $e) {
            $runner->recordFail('live_header_checker_api_callback failed on redirect chain: ' . $e->getMessage());
        }
    }

    private function test_too_many_redirects($runner) {
        global $mock_remote_get_responses;

        // Setup an infinite redirect loop
        $mock_remote_get_responses['http://loop.com'] = array(
            'response' => array('code' => 301),
            'headers' => array(
                'location' => 'http://loop.com'
            )
        );

        $request = new WP_REST_Request(array('url' => 'http://loop.com', 'user_agent' => 'default'));
        $response = live_header_checker_api_callback($request);

        try {
            $this->assert(isset($response['success']) && $response['success'] === true, 'Response should be returned');
            // We expect max 5 redirects + 1 final error entry = 6 entries, or maybe just 5 entries with the last one being error.
            // Let's verify the last entry is an error.
            $last_entry = end($response['chain']);
            $this->assert($last_entry['status'] === 'Error', 'Last step should indicate an error');
            $this->assert(strpos($last_entry['error'], 'Too many redirects') !== false, 'Error message should indicate too many redirects');

            $runner->recordPass('live_header_checker_api_callback stops after 5 redirects');
        } catch (Exception $e) {
            $runner->recordFail('live_header_checker_api_callback failed on redirect loop: ' . $e->getMessage());
        }
    }

    private function test_invalid_url($runner) {
        $request = new WP_REST_Request(array('url' => 'not-a-valid-url', 'user_agent' => 'default'));
        $response = live_header_checker_api_callback($request);

        try {
            $this->assert($response instanceof WP_Error, 'Should return WP_Error for invalid URL');
            $this->assert($response->get_error_message() === 'Invalid URL provided', 'Error message mismatch');

            $runner->recordPass('live_header_checker_api_callback handles invalid URLs');
        } catch (Exception $e) {
            $runner->recordFail('live_header_checker_api_callback failed on invalid URL: ' . $e->getMessage());
        }
    }
}
