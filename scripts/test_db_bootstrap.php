<?php
declare(strict_types=1);

// Create file DB and run migrations (for integration tests)
$root = __DIR__ . '/..';
chdir($root);

if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0755, true);
}

// Ensure DB file exists
$dbFile = __DIR__ . '/../storage/db.sqlite';
if (!file_exists($dbFile)) {
    touch($dbFile);
}

// Run migrations
passthru(PHP_BINARY . ' ' . __DIR__ . '/migrate.php', $ret);
exit($ret);
