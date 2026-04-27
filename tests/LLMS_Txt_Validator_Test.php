<?php

class LLMS_Txt_Validator_Test {
    public function run($runner) {
        $this->test_permission_callback($runner);
        $this->test_fetch_remote_txt_size_limit($runner);
        $this->test_fetch_remote_txt_success($runner);
    }

    protected function test_permission_callback($runner) {
        global $mock_rest_routes, $current_user_can_return;

        $route = $mock_rest_routes['llms-validator/v1/fetch'];
        $callback = $route['permission_callback'];

        $validator = new LLMS_Txt_Validator();

        // Test authorized
        $current_user_can_return = true;
        if (call_user_func($callback) === true) {
            $runner->recordPass("Permission callback allows edit_posts user.");
        } else {
            $runner->recordFail("Permission callback denied edit_posts user.");
        }

        // Test unauthorized
        $current_user_can_return = false;
        if (call_user_func($callback) === false) {
            $runner->recordPass("Permission callback denies user without edit_posts.");
        } else {
            $runner->recordFail("Permission callback allowed user without edit_posts.");
        }
    }

    protected function test_fetch_remote_txt_size_limit($runner) {
        global $mock_remote_get_args;

        $validator = new LLMS_Txt_Validator();
        $request = new WP_REST_Request(array('url' => 'https://example.com/llms.txt'));
        $validator->fetch_remote_txt($request);

        if (isset($mock_remote_get_args['limit_response_size']) && $mock_remote_get_args['limit_response_size'] === 1048576) {
            $runner->recordPass("wp_safe_remote_get includes limit_response_size of 1MB.");
        } else {
            $runner->recordFail("wp_safe_remote_get missing or incorrect limit_response_size.");
        }
    }

    protected function test_fetch_remote_txt_success($runner) {
        $validator = new LLMS_Txt_Validator();
        $request = new WP_REST_Request(array('url' => 'https://example.com/llms.txt'));
        $response = $validator->fetch_remote_txt($request);

        if (isset($response['success']) && $response['success'] === true && $response['content'] === 'mock content') {
            $runner->recordPass("fetch_remote_txt returns success and content.");
        } else {
            $runner->recordFail("fetch_remote_txt failed to return success or content.");
        }
    }
}
