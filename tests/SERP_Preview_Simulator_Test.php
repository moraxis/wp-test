<?php

class SERP_Preview_Simulator_Test {
    public function run($runner) {
        $this->test_render_shortcode($runner);
    }

    protected function test_render_shortcode($runner) {
        $simulator = new SERP_Preview_Simulator();
        $output = $simulator->render_shortcode();

        $expected_elements = array(
            'id="serp-simulator-app"',
            'id="sim-input-url"',
            'id="sim-preview-title"',
            'id="sim-preview-desc"'
        );

        foreach ($expected_elements as $element) {
            if (strpos($output, $element) !== false) {
                $runner->recordPass("Output contains element: {$element}");
            } else {
                $runner->recordFail("Output missing element: {$element}");
            }
        }
    }
}
