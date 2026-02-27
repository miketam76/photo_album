<?php

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$php = PHP_BINARY;
$steps = [
    'DB bootstrap and migrations' => $php . ' scripts/test_db_bootstrap.php',
    'Validation tests' => $php . ' scripts/validation_test.php',
    'Upload integration test' => $php . ' scripts/upload_test.php',
];

foreach ($steps as $label => $command) {
    echo "\n=== {$label} ===\n";
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "\nFAILED: {$label} (exit code {$exitCode})\n");
        exit($exitCode);
    }
}

echo "\nAll tests passed.\n";
