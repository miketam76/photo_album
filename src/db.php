<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = getenv('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../storage/db.sqlite';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            self::$pdo = new PDO($dsn, null, null, $options);
            // Apply recommended PRAGMAs for SQLite
            self::$pdo->exec("PRAGMA foreign_keys = ON;");
            self::$pdo->exec("PRAGMA journal_mode = WAL;");
            self::$pdo->exec("PRAGMA synchronous = NORMAL;");
            self::$pdo->exec("PRAGMA busy_timeout = 5000;");
            return self::$pdo;
        } catch (PDOException $e) {
            throw $e;
        }
    }
}
