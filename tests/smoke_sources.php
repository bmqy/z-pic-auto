<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';

$config = require __DIR__ . '/../config/local.example.php';
$config['download_images'] = false;
$config['database'] = ['driver' => 'sqlite'];

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new Repository($database, $config);
$repository->ensureSchema();

require_once __DIR__ . '/../app/Collector.php';
$collector = new Collector($repository, $config);
foreach ((array) $config['sources'] as $source) {
    if (!($source['enabled'] ?? false)) {
        continue;
    }
    $result = $collector->runSource($source);
    echo $result['source'] . "\t" . $result['status'] . "\t" . $result['message'] . PHP_EOL;
}
