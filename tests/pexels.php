<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';
require_once __DIR__ . '/../app/Collector.php';

function assert_pexels_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = require __DIR__ . '/../config/local.example.php';
$config['database'] = ['driver' => 'sqlite'];
$config['download_images'] = false;
$config['pexels_api_key'] = '';
$GLOBALS['config'] = $config;
$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new Repository($database, $config);
$repository->ensureSchema();
$collector = new Collector($repository, $config);

$buildUrl = new ReflectionMethod(Collector::class, 'buildSourceRequestUrl');
$buildUrl->setAccessible(true);
$requestUrl = $buildUrl->invoke($collector, [
    'type' => 'pexels',
    'url' => 'https://api.pexels.com/v1/search',
    'query' => 'nature',
    'locale' => 'zh-CN',
    'per_page' => 2,
], 'pexels');
$query = [];
parse_str((string) parse_url($requestUrl, PHP_URL_QUERY), $query);
assert_pexels_test($query['query'] === 'nature', 'Pexels query 参数未构造。');
assert_pexels_test($query['locale'] === 'zh-CN', 'Pexels locale 参数未构造。');
assert_pexels_test((int) $query['per_page'] === 2, 'Pexels per_page 参数未构造。');

$parse = new ReflectionMethod(Collector::class, 'parsePexels');
$parse->setAccessible(true);
$items = $parse->invoke($collector, file_get_contents(__DIR__ . '/fixtures/pexels.json'), [
    'image_size' => 'medium',
    'query' => 'nature',
]);
assert_pexels_test(count($items) === 2, 'Pexels photos 未完整转换。');
assert_pexels_test($items[0]['title'] === 'Green mountain landscape', 'Pexels alt 未映射为标题。');
assert_pexels_test(strpos($items[0]['description'], 'Test Photographer') !== false, '摄影师署名未写入描述。');
assert_pexels_test($items[0]['images'][0]['url'] === 'https://images.pexels.com/photos/12345/medium.jpeg', 'Pexels image_size 未生效。');
assert_pexels_test($items[1]['title'] === 'Pexels Photo 67890', 'Pexels 缺失 alt 时未生成回退标题。');
assert_pexels_test($items[1]['images'][0]['url'] === 'https://images.pexels.com/photos/67890/large.jpeg', 'Pexels 图片回退地址未生效。');

$result = $collector->runSource([
    'name' => 'Pexels 精选图片',
    'type' => 'pexels',
    'url' => 'https://api.pexels.com/v1/curated',
    'enabled' => true,
]);
assert_pexels_test($result['status'] === 'failed', '缺少 Pexels API Key 时应明确失败。');
assert_pexels_test(strpos($result['message'], 'API Key') !== false, '缺少 API Key 时错误信息不明确。');

echo "pexels tests passed\n";
