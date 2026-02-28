<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

use function App\validateUserText;

/**
 * Lightweight assertion helper for CLI tests.
 */
function assertSameValue(string $label, mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$label}\n");
        fwrite(STDERR, '  Expected: ' . var_export($expected, true) . "\n");
        fwrite(STDERR, '  Actual:   ' . var_export($actual, true) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$label}\n");
}

$caption5000 = str_repeat('a', 5000);
$caption5001 = str_repeat('a', 5001);
$album120 = str_repeat('b', 120);
$album121 = str_repeat('b', 121);

$cases = [
    [
        'label' => 'Caption accepts empty string',
        'value' => '',
        'max' => 5000,
        'field' => 'Caption',
        'expected' => null,
    ],
    [
        'label' => 'Caption accepts punctuation and whitespace',
        'value' => "Summer trip! #1\nDay 2\t(Beach)",
        'max' => 5000,
        'field' => 'Caption',
        'expected' => null,
    ],
    [
        'label' => 'Caption allows exactly 5000 chars',
        'value' => $caption5000,
        'max' => 5000,
        'field' => 'Caption',
        'expected' => null,
    ],
    [
        'label' => 'Caption rejects over 5000 chars',
        'value' => $caption5001,
        'max' => 5000,
        'field' => 'Caption',
        'expected' => 'Caption must be 5000 characters or fewer.',
    ],
    [
        'label' => 'Caption rejects unsupported control chars',
        'value' => "hello" . chr(0),
        'max' => 5000,
        'field' => 'Caption',
        'expected' => 'Caption contains unsupported characters.',
    ],
    [
        'label' => 'Album name allows exactly 120 chars',
        'value' => $album120,
        'max' => 120,
        'field' => 'Album name',
        'expected' => null,
    ],
    [
        'label' => 'Album name rejects over 120 chars',
        'value' => $album121,
        'max' => 120,
        'field' => 'Album name',
        'expected' => 'Album name must be 120 characters or fewer.',
    ],
    [
        'label' => 'Album name rejects unsupported control chars',
        'value' => "My album" . chr(7),
        'max' => 120,
        'field' => 'Album name',
        'expected' => 'Album name contains unsupported characters.',
    ],
];

foreach ($cases as $case) {
    $actual = validateUserText($case['value'], $case['max'], $case['field']);
    assertSameValue($case['label'], $actual, $case['expected']);
}

fwrite(STDOUT, "All validation tests passed.\n");
