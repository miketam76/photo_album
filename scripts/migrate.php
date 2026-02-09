<?php
declare(strict_types=1);

// Simple migration runner for SQLite migrations
$root = __DIR__ . '/..';
require_once $root . '/src/db.php';

use App\DB;

$pdo = DB::getConnection();
try {
    $files = glob($root . '/migrations/*.sql');
    sort($files, SORT_NATURAL);
    $pdo->beginTransaction();
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        echo "Applied: " . basename($file) . "\n";
    }
    $pdo->commit();
    echo "Migrations applied.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
