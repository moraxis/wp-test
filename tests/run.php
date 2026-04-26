<?php
/**
 * Test runner for LLMS.txt Validator.
 */

require_once __DIR__ . '/mocks/wordpress.php';
require_once __DIR__ . '/../llms-txt-validator/llms-txt-validator.php';
require_once __DIR__ . '/LLMS_Txt_Validator_Test.php';

try {
    $tester = new LLMS_Txt_Validator_Test();
    $tester->run();
    echo "\nAll tests passed successfully!\n";
    exit(0);
} catch (Exception $e) {
    echo "\nTest failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
