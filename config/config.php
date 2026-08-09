<?php
declare(strict_types=1);

$defaults = require __DIR__ . '/local.example.php';
$localFile = __DIR__ . '/local.php';
$envFile = __DIR__ . '/../.env';
$env = [];
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        $env[trim($key)] = $value;
    }
}
if (is_file($localFile)) {
    $local = require $localFile;
} elseif (!empty($env['mysql_username']) && isset($env['mysql_password'])) {
    $siteHost = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'localhost';
    $configuredSiteUrl = trim((string) ($env['site_url'] ?? ''));
    if ($configuredSiteUrl === '' && !empty($env['domain'])) {
        // 三丰云可能把 HTTP_HOST 传成 FTP 主机名，站点 URL 必须以 .env 的业务域名为准。
        $configuredSiteUrl = 'https://' . trim((string) $env['domain'], '/');
    }
    if ($configuredSiteUrl === '') {
        $configuredSiteUrl = 'http://' . $siteHost;
    }
    $databaseName = $env['mysql_database'] ?? ($env['mysql_db'] ?? ($env['mysql_name'] ?? $env['mysql_username']));
    $adminToken = $env['admin_token'] ?? sha1($env['mysql_username'] . '|' . $env['mysql_password']);
    $local = [
        'site_url' => rtrim($configuredSiteUrl, '/'),
        'admin_token' => $adminToken,
        'database' => [
            'driver' => 'mysql',
            'host' => $env['mysql_host'] ?? 'localhost',
            'port' => isset($env['mysql_port']) ? (int) $env['mysql_port'] : 3306,
            'name' => $databaseName,
            'user' => $env['mysql_username'],
            'password' => $env['mysql_password'],
            // 兼容老式虚拟主机上的 MySQL 5.x，避免 utf8mb4 不受支持。
            'charset' => $env['mysql_charset'] ?? 'utf8',
        ],
        // 老式虚拟主机可能没有 CA 证书，由 .env 的 verify_ssl=0 显式切换兼容模式。
        'verify_ssl' => !isset($env['verify_ssl']) || !in_array(strtolower($env['verify_ssl']), ['0', 'false', 'no'], true),
    ];
} else {
    throw new RuntimeException('未找到 config/local.php，也未在根目录 .env 找到 mysql_username/mysql_password。');
}
$config = array_replace_recursive($defaults, is_array($local) ? $local : []);

date_default_timezone_set((string) $config['timezone']);

return $config;
