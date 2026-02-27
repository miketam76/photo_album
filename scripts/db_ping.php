<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

use App\DB;

try {
    $pdo = DB::getConnection();
    $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $stmt = $pdo->query('SELECT DATABASE() AS db_name, VERSION() AS db_version');
        $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        $dbName = (string)($row['db_name'] ?? '');
        $dbVersion = (string)($row['db_version'] ?? '');
        echo "OK: Connected to MySQL database '{$dbName}'\n";
        echo "Version: {$dbVersion}\n";
    } elseif ($driver === 'sqlite') {
        echo "OK: Connected to SQLite database.\n";
    } else {
        echo "OK: Connected using driver '{$driver}'.\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'DB ping failed: ' . $e->getMessage() . "\n");
    exit(1);
}
