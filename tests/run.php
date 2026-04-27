<?php
/**
 * Test Runner
 */

require_once __DIR__ . '/mocks/wordpress.php';
require_once __DIR__ . '/../llms-txt-validator/llms-txt-validator.php';

// Trigger the rest_api_init action to register routes
do_action('rest_api_init');

class TestRunner {
    protected $passed = 0;
    protected $failed = 0;

    public function run() {
        // Explicitly include test files
        require_once __DIR__ . '/LLMS_Txt_Validator_Test.php';
        $test1 = new LLMS_Txt_Validator_Test();
        $test1->run($this);

        try {
            require_once __DIR__ . '/test-image-alt-auditor.php';
        } catch (Exception $e) {
            $this->recordFail($e->getMessage());
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
