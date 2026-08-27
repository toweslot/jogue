<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function conn(array $config): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver = $config['db']['driver'] ?? 'sqlite';
        if ($driver === 'sqlite') {
            return self::connectSqlite($config);
        }
        if ($driver === 'mysql') {
            try {
                return self::connectMysql($config);
            } catch (PDOException $e) {
                // Se o MySQL estiver indisponivel ou com credenciais erradas, o site
                // inteiro caia em erro 500 (login/cadastro inclusos). Para evitar isso,
                // voltamos para o SQLite local, que ja contem os dados do site.
                error_log('[DB] MySQL indisponivel, usando SQLite: ' . $e->getMessage());
                return self::connectSqlite($config);
            }
        }

        throw new PDOException('DB_DRIVER invalido. Use sqlite ou mysql.');
    }

    private static function connectSqlite(array $config): PDO
    {
        $dbPath = (string)($config['db']['path'] ?? '');
        if ($dbPath === '') {
            throw new PDOException('SQLite requer DB_PATH no .env.');
        }

        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        self::$pdo = new PDO('sqlite:' . $dbPath);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        return self::$pdo;
    }

    private static function connectMysql(array $config): PDO
    {
        $host = (string)($config['db']['host'] ?? '127.0.0.1');
        $port = (int)($config['db']['port'] ?? 3306);
        $name = (string)($config['db']['name'] ?? '');
        $user = (string)($config['db']['user'] ?? '');
        $pass = (string)($config['db']['pass'] ?? '');
        $charset = (string)($config['db']['charset'] ?? 'utf8mb4');

        if ($name === '' || $user === '') {
            throw new PDOException('MySQL requer DB_NAME e DB_USER no .env.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }
}
