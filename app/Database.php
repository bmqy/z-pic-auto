<?php
declare(strict_types=1);

final class Database
{
    public static function connect(array $config): PDO
    {
        $database = (array) ($config['database'] ?? []);
        $driver = strtolower((string) ($database['driver'] ?? 'sqlite'));
        if ($driver === 'mysql') {
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('服务器未启用 PDO MySQL，请在虚拟主机 PHP 扩展中启用 pdo_mysql。');
            }
            $host = (string) ($database['host'] ?? 'localhost');
            $port = (int) ($database['port'] ?? 3306);
            $name = (string) ($database['name'] ?? '');
            $charset = trim((string) ($database['charset'] ?? ''));
            if ($charset === '') {
                throw new RuntimeException('MySQL 数据库缺少 charset 配置，请设置 DB_CHARSET 环境变量。');
            }
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=' . $charset;
            $pdo = new PDO($dsn, (string) ($database['user'] ?? ''), (string) ($database['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        }
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('服务器未启用 PDO SQLite，请改用 MySQL 配置或启用 sqlite3 和 pdo_sqlite。');
        }
        $path = (string) ($database['path'] ?? __DIR__ . '/../storage/zpic.sqlite');
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }
}
