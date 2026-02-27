<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = getenv('DB_DSN');
        if ($dsn === false || $dsn === '') {
            throw new RuntimeException('DB_DSN environment variable is required.');
        }
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        $username = $username === false || $username === '' ? null : $username;
        $password = $password === false || $password === '' ? null : $password;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            self::$pdo = new PDO($dsn, $username, $password, $options);
            if (self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                // Apply recommended PRAGMAs for SQLite only.
                self::$pdo->exec("PRAGMA foreign_keys = ON;");
                self::$pdo->exec("PRAGMA journal_mode = WAL;");
                self::$pdo->exec("PRAGMA synchronous = NORMAL;");
                self::$pdo->exec("PRAGMA busy_timeout = 5000;");
            }
            return self::$pdo;
        } catch (PDOException $e) {
            throw $e;
        }
    }
}
