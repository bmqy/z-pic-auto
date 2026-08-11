<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';
require_once __DIR__ . '/../app/Collector.php';

$config = require __DIR__ . '/../config/local.example.php';
$config['database'] = ['driver' => 'sqlite'];
$config['download_images'] = false;
$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new Repository($database, $config);
$repository->ensureSchema();

$result = (new Collector($repository, $config))->importTranslatedItems([
    [
        'source_name' => 'NASA',
        'item' => [
            'title' => '中文标题',
            'description' => '中文描述',
            'category' => '风景',
            'source_url' => 'https://example.com/gallery/1',
            'identity_source_url' => 'https://example.com/gallery/1',
            'fingerprint' => sha1('import-test'),
            'images' => [[
                'url' => 'https://example.com/image.jpg',
                'alt' => '中文替代文本',
            ]],
        ],
    ],
]);

if ($result['status'] !== 'success' || (int) $result['added'] !== 1) {
    throw new RuntimeException('翻译内容导入测试失败。');
}

echo "translation import tests passed\n";
