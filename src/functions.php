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

/**
 * Generate a signed URL for serving an image via `image.php`.
 * Uses APP_KEY from environment as HMAC secret.
 */
function generate_signed_url(string $photoUuid, string $size = 'original', int $ttl = 300): string
{
    $exp = (string)(time() + $ttl);
    $payload = 'photo=' . $photoUuid . '&size=' . $size . '&exp=' . $exp;
    $key = getenv('APP_KEY') ?: '';
    $sig = hash_hmac('sha256', $payload, $key);
    return '/image.php?photo=' . urlencode($photoUuid) . '&size=' . urlencode($size) . '&exp=' . $exp . '&sig=' . $sig;
}

/**
 * Validate a signed image request.
 */
function validate_signed_sig(string $photoUuid, string $size, string $exp, string $sig): bool
{
    if (!ctype_digit($exp)) {
        return false;
    }
    if ((int)$exp < time()) {
        return false;
    }
    $payload = 'photo=' . $photoUuid . '&size=' . $size . '&exp=' . $exp;
    $key = getenv('APP_KEY') ?: '';
    $expected = hash_hmac('sha256', $payload, $key);
    return hash_equals($expected, $sig);
}
