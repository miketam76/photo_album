<?php

declare(strict_types=1);

// Simple migration runner for SQLite migrations
$root = __DIR__ . '/..';
require_once $root . '/src/db.php';

use App\DB;

$pdo = DB::getConnection();

/**
 * Ensure migration bookkeeping table exists.
 */
function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            filename TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
}

/**
 * Handle legacy DBs where width/height may already exist but migration is untracked.
 */
function shouldMarkAsAppliedWithoutExec(PDO $pdo, string $filename): bool
{
    if ($filename !== '002_add_dimensions.sql') {
        return false;
    }

    $columns = [];
    $stmt = $pdo->query('PRAGMA table_info(photos)');
    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[] = $row['name'] ?? '';
        }
    }

    return in_array('width', $columns, true) && in_array('height', $columns, true);
}

try {
    ensureMigrationsTable($pdo);

    $files = glob($root . '/migrations/*.sql');
    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
        $filename = basename($file);

        $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE filename = ?');
        $check->execute([$filename]);
        if ($check->fetchColumn()) {
            echo "Skipped (already applied): {$filename}\n";
            continue;
        }

        if (shouldMarkAsAppliedWithoutExec($pdo, $filename)) {
            $mark = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
            $mark->execute([$filename]);
            echo "Marked as applied (already present): {$filename}\n";
            continue;
        }

        $pdo->beginTransaction();
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        $mark = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
        $mark->execute([$filename]);
        $pdo->commit();
        echo "Applied: {$filename}\n";
    }

    echo "Migrations applied.\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
