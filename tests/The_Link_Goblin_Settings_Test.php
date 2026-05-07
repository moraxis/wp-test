<?php
/**
 * Test for The_Link_Goblin_Settings
 */

class The_Link_Goblin_Settings_Test {
    public function run($runner) {
        $this->test_settings_registration($runner);
        $this->test_api_key_sanitization($runner);
        $this->test_api_model_sanitization($runner);
    }

    protected function test_settings_registration($runner) {
        global $mock_settings;

        if (isset($mock_settings['the_link_goblin_api_key'])) {
            $runner->recordPass("the_link_goblin_api_key is registered.");
        } else {
            $runner->recordFail("the_link_goblin_api_key is NOT registered.");
        }

        if (isset($mock_settings['the_link_goblin_api_model'])) {
            $runner->recordPass("the_link_goblin_api_model is registered.");
        } else {
            $runner->recordFail("the_link_goblin_api_model is NOT registered.");
        }
    }

    protected function test_api_key_sanitization($runner) {
        global $mock_settings;

        $args = isset($mock_settings['the_link_goblin_api_key']) ? $mock_settings['the_link_goblin_api_key'] : array();

        if (isset($args['sanitize_callback'])) {
            $callback = $args['sanitize_callback'];
            $dirty = "  some-key-with-tags <script>alert(1)</script>  ";
            $clean = call_user_func($callback, $dirty);

            if ($clean === "some-key-with-tags alert(1)") {
                $runner->recordPass("API key sanitization works correctly.");
            } else {
                $runner->recordFail("API key sanitization failed. Got: '$clean'");
            }
        } else {
            $runner->recordFail("API key has no sanitization callback.");
        }
    }

    protected function test_api_model_sanitization($runner) {
        global $mock_settings;

        $callback = isset($mock_settings['the_link_goblin_api_model']) ? $mock_settings['the_link_goblin_api_model'] : null;

        if ($callback) {

            // Valid values
            if (call_user_func($callback, 'deepseek-chat') === 'deepseek-chat' &&
                call_user_func($callback, 'deepseek-reasoner') === 'deepseek-reasoner') {
                $runner->recordPass("API model accepts valid values.");
            } else {
                $runner->recordFail("API model rejected valid values.");
            }

            // Invalid value
            $invalid = 'invalid-model';
            $sanitized = call_user_func($callback, $invalid);
            if ($sanitized === 'deepseek-chat') {
                $runner->recordPass("API model rejects invalid value and defaults correctly.");
            } else {
                $runner->recordFail("API model failed to handle invalid value correctly. Got: '$sanitized'");
            }
        } else {
            $runner->recordFail("API model has no sanitization callback.");
        }
    }
}
