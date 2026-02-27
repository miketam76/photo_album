<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

final class DB
{
    private static ?PDO $pdo = null;
    private static bool $envLoaded = false;

    private static function loadEnvFile(): void
    {
        if (self::$envLoaded) {
            return;
        }
        self::$envLoaded = true;

        $envPath = __DIR__ . '/../.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }

            // Strip matching wrapping quotes if present.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        self::loadEnvFile();

        $dsn = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=photo_album;charset=utf8mb4';
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
