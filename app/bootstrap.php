<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Collector.php';
require_once __DIR__ . '/Template.php';

$databaseConfig = (array) ($config['database'] ?? []);
$databaseDriver = strtolower((string) ($databaseConfig['driver'] ?? 'sqlite'));
if ($databaseDriver !== 'mysql') {
    $databasePath = (string) ($databaseConfig['path'] ?? __DIR__ . '/../storage/zpic.sqlite');
    if (!is_dir(dirname($databasePath))) {
        mkdir(dirname($databasePath), 0775, true);
    }
}
if (!is_dir($config['image_dir'])) {
    mkdir($config['image_dir'], 0775, true);
}

$db = Database::connect($config);
$repository = new Repository($db, $config);
$repository->ensureSchema();
