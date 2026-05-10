<?php

class Robots_Txt_Validator_Test {
    public function run($runner) {
        $this->test_permission_callback($runner);
        $this->test_fetch_remote_txt_size_limit($runner);
        $this->test_fetch_remote_txt_success($runner);
    }

    protected function test_permission_callback($runner) {
        global $mock_rest_routes, $current_user_can_return;

        $route = $mock_rest_routes['robots-validator/v1/fetch'];
        $callback = $route['permission_callback'];

        // Initially it's __return_true, which is a function name as a string.
        // After fix it will be array($this, 'check_permission')

        // Test with current_user_can returning false
        $current_user_can_return = false;

        $is_allowed = false;
        if (is_string($callback) && function_exists($callback)) {
            $is_allowed = call_user_func($callback);
        } elseif (is_array($callback)) {
             $is_allowed = call_user_func($callback);
        }

        // We WANT it to be false when $current_user_can_return is false.
        if ($is_allowed === false) {
            $runner->recordPass("Permission callback denies user without edit_posts.");
        } else {
            $runner->recordFail("Permission callback allowed user without edit_posts (Currently Public).");
        }
    }

    protected function test_fetch_remote_txt_size_limit($runner) {
        global $mock_remote_get_args;

        $validator = new Robots_Txt_Validator();
        $request = new WP_REST_Request(array('url' => 'https://example.com/robots.txt'));
        $validator->fetch_remote_txt($request);

        if (isset($mock_remote_get_args['limit']) && $mock_remote_get_args['limit'] === 1048576) {
            $runner->recordPass("wp_safe_remote_get includes limit of 1MB.");
        } else {
            $runner->recordFail("wp_safe_remote_get missing or incorrect limit.");
        }
    }

    protected function test_fetch_remote_txt_success($runner) {
        $validator = new Robots_Txt_Validator();
        $request = new WP_REST_Request(array('url' => 'https://example.com/robots.txt'));
        $response = $validator->fetch_remote_txt($request);

        if (isset($response['success']) && $response['success'] === true && $response['content'] === 'mock content') {
            $runner->recordPass("fetch_remote_txt returns success and content.");
        } else {
            $runner->recordFail("fetch_remote_txt failed to return success or content.");
        }
    }
}
