<?php
/**
 * Test Runner
 */

require_once __DIR__ . '/mocks/wordpress.php';
require_once __DIR__ . '/../llms-txt-validator/llms-txt-validator.php';
require_once __DIR__ . '/../the-link-goblin/includes/class-settings.php';
require_once __DIR__ . '/../link-velocity-calculator/link-velocity-calculator.php';
require_once __DIR__ . '/../serp-preview-simulator/serp-preview-simulator.php';

// Trigger the rest_api_init action to register routes
do_action('rest_api_init');
// Trigger admin_init to register settings
do_action('admin_init');

class TestRunner {
    protected $passed = 0;
    protected $failed = 0;

    public function run() {
        $testClasses = array(
            'LLMS_Txt_Validator_Test',
            'The_Link_Goblin_Settings_Test',
            'Link_Velocity_Calculator_Test',
            'The_Link_Goblin_Test'
        );

        foreach ($testClasses as $testClass) {
            require_once __DIR__ . '/' . $testClass . '.php';
            $testInstance = new $testClass();
            $testInstance->run($this);
        }

        echo "\nTests completed. Passed: {$this->passed}, Failed: {$this->failed}\n";
        exit($this->failed > 0 ? 1 : 0);
    }

    public function recordPass($message) {
        $this->passed++;
        echo "✓ {$message}\n";
    }

    public function recordFail($message) {
        $this->failed++;
        echo "✗ {$message}\n";
    }
}

$runner = new TestRunner();
$runner->run();
