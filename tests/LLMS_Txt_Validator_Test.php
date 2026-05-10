<?php

class LLMS_Txt_Validator_Test {
    public function run($runner) {
        $this->test_permission_callback($runner);
        $this->test_fetch_remote_txt_size_limit($runner);
        $this->test_fetch_remote_txt_success($runner);
        $this->test_rest_route_registration($runner);
        $this->test_fetch_remote_txt_wp_error($runner);
        $this->test_fetch_remote_txt_non_200($runner);
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

        if (isset($mock_remote_get_args['limit']) && $mock_remote_get_args['limit'] === 1048576) {
            $runner->recordPass("wp_safe_remote_get includes limit of 1MB.");
        } else {
            $runner->recordFail("wp_safe_remote_get missing or incorrect limit.");
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

    protected function test_rest_route_registration($runner) {
        global $mock_rest_routes;

        // Clear existing routes to ensure we test this specific call
        $mock_rest_routes = array();
        $validator = new LLMS_Txt_Validator();
        $validator->register_rest_routes();

        $key = 'llms-validator/v1/fetch';
        if (!isset($mock_rest_routes[$key])) {
            $runner->recordFail("REST route $key not registered.");
            return;
        }

        $route = $mock_rest_routes[$key];
        $passed = true;

        if ($route['methods'] !== 'GET') {
            $runner->recordFail("REST route method is not GET.");
            $passed = false;
        }

        $expected_args = array(
            'url' => array(
                'required' => true,
                'type'     => 'string',
                'format'   => 'uri',
                'sanitize_callback' => 'esc_url_raw'
            )
        );

        if ($route['args'] !== $expected_args) {
            $runner->recordFail("REST route arguments do not match expected configuration.");
            $passed = false;
        }

        if ($passed) {
            $runner->recordPass("REST route registration correctly verified.");
        }
    }

    protected function test_fetch_remote_txt_wp_error($runner) {
        $validator = new LLMS_Txt_Validator();
        $request = new WP_REST_Request(array('url' => 'https://example.com/error'));
        $response = $validator->fetch_remote_txt($request);

        if (is_wp_error($response) && $response->code === 'fetch_error' && $response->data['status'] === 500) {
            $runner->recordPass("fetch_remote_txt handles WP_Error correctly with 500 status.");
        } else {
            $runner->recordFail("fetch_remote_txt failed to handle WP_Error correctly.");
        }
    }

    protected function test_fetch_remote_txt_non_200($runner) {
        $validator = new LLMS_Txt_Validator();
        $request = new WP_REST_Request(array('url' => 'https://example.com/?status=404'));
        $response = $validator->fetch_remote_txt($request);

        if (is_wp_error($response) && $response->code === 'fetch_error' && $response->data['status'] === 404) {
            $runner->recordPass("fetch_remote_txt handles non-200 status correctly.");
        } else {
            $runner->recordFail("fetch_remote_txt failed to handle non-200 status correctly.");
        }
    }
}
