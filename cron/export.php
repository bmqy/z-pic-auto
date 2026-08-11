<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$config = require __DIR__ . '/../config/local.example.php';
$config['download_images'] = false;
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';
require_once __DIR__ . '/../app/Collector.php';

try {
    $collector = new Collector(null, $config);
    echo json_encode([
        'items' => $collector->exportTranslatedItems(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
