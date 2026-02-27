<?php

declare(strict_types=1);

namespace App;

function uuid(): string
{
    // 32-char hex UUID (not RFC4122) sufficient for filenames
    return bin2hex(random_bytes(16));
}

function validateUserText(string $value, int $maxLength, string $fieldLabel = 'Field'): ?string
{
    if (mb_strlen($value) > $maxLength) {
        return sprintf('%s must be %d characters or fewer.', $fieldLabel, $maxLength);
    }

    // Allow letters, numbers, whitespace, punctuation, tabs and newlines.
    if ($value !== '' && !preg_match('/\A[\p{L}\p{N}\p{Zs}\p{P}\r\n\t]*\z/u', $value)) {
        return sprintf('%s contains unsupported characters.', $fieldLabel);
    }

    return null;
}
