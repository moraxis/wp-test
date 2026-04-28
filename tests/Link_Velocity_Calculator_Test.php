<?php

class Link_Velocity_Calculator_Test {
    public function run($runner) {
        $this->test_shortcode_exists($runner);
        $this->test_update_check_disabled($runner);
    }

    private function test_shortcode_exists($runner) {
        global $shortcodes;

        try {
            if (!isset($shortcodes['link-velocity-calculator'])) {
                throw new Exception("Shortcode 'link-velocity-calculator' was not registered.");
            }

            // Call the shortcode
            $calculator = new Link_Velocity_Calculator();
            $output = $calculator->render_shortcode();

            if (strpos($output, 'Link Velocity Calculator') === false) {
                throw new Exception("Shortcode output does not contain expected HTML.");
            }
            if (strpos($output, 'lvc-my-links') === false) {
                throw new Exception("Shortcode output does not contain expected input fields.");
            }

            $runner->recordPass("Link Velocity Calculator: Shortcode registered and output is correct");
        } catch (Exception $e) {
            $runner->recordFail("Link Velocity Calculator: " . $e->getMessage());
        }
    }

    private function test_update_check_disabled($runner) {
        try {
            $calculator = new Link_Velocity_Calculator();

            // Mock transient object
            $transient = new stdClass();
            $transient->response = array(
                'link-velocity-calculator/link-velocity-calculator.php' => 'update_available',
                'some-other-plugin/plugin.php' => 'update_available'
            );

            $result = $calculator->disable_update_check($transient);

            if (isset($result->response['link-velocity-calculator/link-velocity-calculator.php'])) {
                throw new Exception("Update check was not disabled for the plugin.");
            }

            if (!isset($result->response['some-other-plugin/plugin.php'])) {
                throw new Exception("Update check modified other plugins.");
            }

            $runner->recordPass("Link Velocity Calculator: Update check properly disabled");
        } catch (Exception $e) {
            $runner->recordFail("Link Velocity Calculator: " . $e->getMessage());
        }
    }
}
