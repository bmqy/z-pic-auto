<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$loader = __DIR__ . '/../config/loader.php';
require_once $loader;
$config = zpic_load_config(false);
$config['download_images'] = false;
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';
require_once __DIR__ . '/../app/Collector.php';

try {
    $collector = new Collector(null, $config);
    $items = $collector->exportTranslatedItems();
    foreach ($collector->getExportWarnings() as $warning) {
        fwrite(STDERR, $warning . PHP_EOL);
    }
    if ($items === []) {
        throw new RuntimeException('所有启用来源均未产生可导入内容。');
    }
    echo json_encode([
        'items' => $items,
        'warnings' => $collector->getExportWarnings(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
