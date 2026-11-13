<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * One shared PDO connection. All SQL lives in the Models; they call Database::pdo().
 */
final class Database
{
    private static array $config = [];
    private static ?PDO $pdo = null;

    public static function configure(array $dbConfig): void
    {
        self::$config = $dbConfig;
        self::$pdo = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = self::$config;
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // real server-side prepared statements
                // Store and read every timestamp in UTC.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ]);
        }
        return self::$pdo;
    }
}
