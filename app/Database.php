<?php

declare(strict_types=1);

namespace PingFloodWatch;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = (string) Config::get('db.dsn', '');
        if ($dsn === '') {
            throw new \RuntimeException('Database is not configured. Set db.dsn in the local configuration.');
        }

        self::$connection = new PDO(
            $dsn,
            (string) Config::get('db.username', ''),
            (string) Config::get('db.password', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );
        self::$connection->exec("SET time_zone = '+00:00'");

        return self::$connection;
    }

    public static function resetForTests(): void
    {
        self::$connection = null;
    }
}

