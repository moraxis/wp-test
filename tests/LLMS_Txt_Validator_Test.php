<?php

class LLMS_Txt_Validator_Test {
    public function run($runner) {
        $this->test_permission_callback($runner);
        $this->test_fetch_remote_txt_size_limit($runner);
        $this->test_fetch_remote_txt_success($runner);
        $this->test_disable_plugin_updates($runner);
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

    protected function test_disable_plugin_updates($runner) {
        $validator = new LLMS_Txt_Validator();

        // Scenario 1: Plugin is in the transient response
        $transient = new stdClass();
        $transient->response = array(
            'llms-txt-validator/llms-txt-validator.php' => (object)array('new_version' => '2.0.0'),
            'other-plugin/other-plugin.php' => (object)array('new_version' => '1.5.0')
        );

        $result = $validator->disable_plugin_updates($transient);
        if (!isset($result->response['llms-txt-validator/llms-txt-validator.php']) && isset($result->response['other-plugin/other-plugin.php'])) {
            $runner->recordPass("disable_plugin_updates successfully removes plugin from transient.");
        } else {
            $runner->recordFail("disable_plugin_updates failed to remove plugin from transient.");
        }

        // Scenario 2: Plugin is not in the transient response
        $transient2 = new stdClass();
        $transient2->response = array(
            'other-plugin/other-plugin.php' => (object)array('new_version' => '1.5.0')
        );
        $result2 = $validator->disable_plugin_updates($transient2);
        if (isset($result2->response['other-plugin/other-plugin.php']) && count($result2->response) === 1) {
            $runner->recordPass("disable_plugin_updates leaves transient unchanged when plugin is not present.");
        } else {
            $runner->recordFail("disable_plugin_updates incorrectly modified transient when plugin was not present.");
        }

        // Scenario 3: Transient object does not have a response property
        $transient3 = new stdClass();
        $transient3->foo = 'bar'; // some other property

        // This will issue a warning/notice in PHP 8.x when trying to access missing property as array
        // if not careful, but the code checks isset($transient->response['...']) which handles it gracefully.
        // Let's verify it doesn't throw and returns the object.
        $error_thrown = false;
        try {
            $result3 = $validator->disable_plugin_updates($transient3);
        } catch (Exception $e) {
            $error_thrown = true;
        } catch (Error $e) {
            $error_thrown = true;
        }

        if (!$error_thrown && !isset($result3->response) && isset($result3->foo)) {
            $runner->recordPass("disable_plugin_updates gracefully handles transient without response property.");
        } else {
            $runner->recordFail("disable_plugin_updates failed to handle transient without response property.");
        }
    }
}
