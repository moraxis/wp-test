<?php
// tests/run.php

require_once 'test-llms-txt-validator.php';

echo "Running Plugin Tests...\n";
echo "----------------------\n";

$test_suite = new Test_LLMS_Txt_Validator();
$test_suite->run_tests();

echo "----------------------\n";
echo "All tests passed!\n";
