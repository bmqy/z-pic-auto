<?php
declare(strict_types=1);

/**
 * 读取项目配置，并统一处理本地配置与环境变量覆盖。
 *
 * Actions 不需要连接数据库，因此可以关闭数据库配置必需校验；线上站点
 * 则继续要求存在 config/local.php 或完整的数据库环境变量。
 */
function zpic_load_config(bool $requireDatabase = true): array
{
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

    $environmentKeys = [
        'SITE_URL',
        'ADMIN_TOKEN',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_CHARSET',
        'VERIFY_SSL',
        'PEXELS_API_KEY',
    ];
    foreach ($environmentKeys as $environmentKey) {
        $environmentValue = getenv($environmentKey);
        if ($environmentValue !== false) {
            $env[$environmentKey] = (string) $environmentValue;
        }
    }

    if (is_file($localFile) && filesize($localFile) > 0) {
        $local = require $localFile;
    } elseif (!empty($env['DB_USERNAME']) && isset($env['DB_PASSWORD'])) {
        $siteHost = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '' ? $_SERVER['HTTP_HOST'] : 'localhost';
        $configuredSiteUrl = trim((string) ($env['SITE_URL'] ?? ''));
        if ($configuredSiteUrl === '') {
            $configuredSiteUrl = 'http://' . $siteHost;
        }
        $databaseName = $env['DB_NAME'] ?? $env['DB_USERNAME'];
        $adminToken = $env['ADMIN_TOKEN'] ?? sha1($env['DB_USERNAME'] . '|' . $env['DB_PASSWORD']);
        $local = [
            'site_url' => rtrim($configuredSiteUrl, '/'),
            'admin_token' => $adminToken,
            'database' => [
                'driver' => 'mysql',
                'host' => $env['DB_HOST'] ?? 'localhost',
                'port' => isset($env['DB_PORT']) ? (int) $env['DB_PORT'] : 3306,
                'name' => $databaseName,
                'user' => $env['DB_USERNAME'],
                'password' => $env['DB_PASSWORD'],
                // 兼容老式虚拟主机上的 MySQL 5.x，避免 utf8mb4 不受支持。
                'charset' => $env['DB_CHARSET'] ?? 'utf8',
            ],
            // 老式虚拟主机可能没有 CA 证书，由 .env 的 VERIFY_SSL=0 显式切换兼容模式。
            'verify_ssl' => !isset($env['VERIFY_SSL']) || !in_array(strtolower($env['VERIFY_SSL']), ['0', 'false', 'no'], true),
        ];
    } elseif ($requireDatabase) {
        throw new RuntimeException('未找到 config/local.php，也未在根目录 .env 找到 DB_USERNAME/DB_PASSWORD。');
    } else {
        // Actions 只抓取和翻译，不需要站点数据库连接信息。
        $local = [];
    }

    $config = array_replace_recursive($defaults, is_array($local) ? $local : []);

    if (isset($env['PEXELS_API_KEY']) && trim((string) $env['PEXELS_API_KEY']) !== '') {
        $config['pexels_api_key'] = trim((string) $env['PEXELS_API_KEY']);
    }

    if (trim((string) ($config['pexels_api_key'] ?? '')) !== '') {
        foreach ((array) ($config['sources'] ?? []) as $sourceIndex => $source) {
            if (strtolower((string) ($source['type'] ?? '')) === 'pexels') {
                $config['sources'][$sourceIndex]['enabled'] = true;
            }
        }
    }

    date_default_timezone_set((string) $config['timezone']);

    return $config;
}
