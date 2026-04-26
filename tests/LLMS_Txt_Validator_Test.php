<?php

class LLMS_Txt_Validator_Test {
    private $validator;

    public function __construct() {
        $this->validator = new LLMS_Txt_Validator();
    }

    public function run() {
        $this->test_initialization();
        $this->test_shortcode_registration();
        $this->test_rest_route_registration();
        $this->test_fetch_remote_txt_success();
        $this->test_fetch_remote_txt_error();
        $this->test_fetch_remote_txt_http_error();
    }

    private function test_initialization() {
        global $wp_actions;
        $this->assert(isset($wp_actions['init']), 'init action should be registered');
        $this->assert(isset($wp_actions['rest_api_init']), 'rest_api_init action should be registered');
        echo "✓ test_initialization passed\n";
    }

    private function test_shortcode_registration() {
        global $wp_shortcodes;
        $this->validator->init();
        $this->assert(isset($wp_shortcodes['llms_txt_validator']), 'llms_txt_validator shortcode should be registered');
        echo "✓ test_shortcode_registration passed\n";
    }

    private function test_rest_route_registration() {
        global $wp_rest_routes;
        $this->validator->register_rest_routes();
        $this->assert(isset($wp_rest_routes['llms-validator/v1/fetch']), 'REST route should be registered');
        echo "✓ test_rest_route_registration passed\n";
    }

    private function test_fetch_remote_txt_success() {
        global $wp_safe_remote_get_response;

        $wp_safe_remote_get_response = [
            'response' => ['code' => 200],
            'body' => 'llms.txt content'
        ];

        $request = new class {
            public function get_param($name) {
                return 'http://example.com/llms.txt';
            }
        };

        $response = $this->validator->fetch_remote_txt($request);

        $this->assert($response['success'] === true, 'Response should be successful');
        $this->assert($response['content'] === 'llms.txt content', 'Response content should match');
        echo "✓ test_fetch_remote_txt_success passed\n";
    }

    private function test_fetch_remote_txt_error() {
        global $wp_safe_remote_get_response;

        $wp_safe_remote_get_response = new WP_Error('fetch_failed', 'Connection error');

        $request = new class {
            public function get_param($name) {
                return 'http://example.com/llms.txt';
            }
        };

        $response = $this->validator->fetch_remote_txt($request);

        $this->assert($response instanceof WP_Error, 'Response should be a WP_Error');
        $this->assert($response->get_error_message() === 'Failed to fetch the URL: Connection error', 'Error message should match');
        echo "✓ test_fetch_remote_txt_error passed\n";
    }

    private function test_fetch_remote_txt_http_error() {
        global $wp_safe_remote_get_response;

        $wp_safe_remote_get_response = [
            'response' => ['code' => 404],
            'body' => 'Not Found'
        ];

        $request = new class {
            public function get_param($name) {
                return 'http://example.com/llms.txt';
            }
        };

        $response = $this->validator->fetch_remote_txt($request);

        $this->assert($response instanceof WP_Error, 'Response should be a WP_Error on 404');
        $this->assert(strpos($response->get_error_message(), '404') !== false, 'Error message should contain status code');
        echo "✓ test_fetch_remote_txt_http_error passed\n";
    }

    private function assert($condition, $message) {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }
}
