<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';
require_once __DIR__ . '/../app/Collector.php';

$config = require __DIR__ . '/../config/local.example.php';
$config['database'] = ['driver' => 'sqlite'];
$config['download_images'] = false;
$config['sources'] = [[
    'name' => '本地测试来源',
    'type' => 'json',
    'url' => 'http://127.0.0.1/tests/fixtures/galleries.json',
    'enabled' => true,
]];
$GLOBALS['config'] = $config;
$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new Repository($database, $config);
$repository->ensureSchema();
$result = (new Collector($repository, $config))->runSource($config['sources'][0]);
if ($result['status'] !== 'success' || (int) $result['added'] !== 1) {
    throw new RuntimeException('本地翻译采集冒烟失败：' . $result['message']);
}
$gallery = $database->query('SELECT title, description FROM galleries LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$gallery || !preg_match('/\p{Han}/u', (string) $gallery['title']) || !preg_match('/\p{Han}/u', (string) $gallery['description'])) {
    throw new RuntimeException('本地采集入库内容未保持中文。');
}
echo "translation smoke passed\n";
