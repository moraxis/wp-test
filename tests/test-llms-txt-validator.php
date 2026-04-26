<?php
// tests/test-llms-txt-validator.php

require_once 'mocks/wordpress.php';
require_once '../llms-txt-validator/llms-txt-validator.php';

class Test_LLMS_Txt_Validator {
    private $validator;

    public function __construct() {
        $this->validator = new LLMS_Txt_Validator();
    }

    public function run_tests() {
        $this->test_shortcode_registration();
        $this->test_render_shortcode_output();
        $this->test_render_shortcode_enqueues();
    }

    private function test_shortcode_registration() {
        echo "Testing shortcode registration... ";
        $this->validator->init();
        if (isset(WordPressMock::$shortcodes['llms_txt_validator'])) {
            echo "PASSED\n";
        } else {
            echo "FAILED (Shortcode not registered)\n";
            exit(1);
        }
    }

    private function test_render_shortcode_output() {
        echo "Testing render_shortcode output... ";
        $output = $this->validator->render_shortcode([]);

        $expected_elements = [
            'llms-validator-container',
            'llms-fetch-url',
            'llms-fetch-btn',
            'llms-editor',
            'llms-validation-errors'
        ];

        foreach ($expected_elements as $element) {
            if (strpos($output, $element) === false) {
                echo "FAILED (Element $element not found in output)\n";
                exit(1);
            }
        }
        echo "PASSED\n";
    }

    private function test_render_shortcode_enqueues() {
        echo "Testing render_shortcode enqueues... ";
        WordPressMock::reset();
        $this->validator->render_shortcode([]);

        $expected_styles = ['codemirror-css', 'llms-validator-style'];
        $expected_scripts = ['codemirror-js', 'codemirror-markdown', 'llms-validator-script'];

        foreach ($expected_styles as $style) {
            if (!in_array($style, WordPressMock::$enqueued_styles)) {
                echo "FAILED (Style $style not enqueued)\n";
                exit(1);
            }
        }

        foreach ($expected_scripts as $script) {
            if (!in_array($script, WordPressMock::$enqueued_scripts)) {
                echo "FAILED (Script $script not enqueued)\n";
                exit(1);
            }
        }
        echo "PASSED\n";
    }
}
