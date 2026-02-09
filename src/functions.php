<?php
declare(strict_types=1);

namespace App;

function uuid(): string
{
    // 32-char hex UUID (not RFC4122) sufficient for filenames
    return bin2hex(random_bytes(16));
}
