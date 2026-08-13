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
], [[
    'name' => 'NASA',
    'run_source_name' => 'NASA',
    'status' => 'success',
    'message' => '抓取并规范化成功。',
]]);

if ($result['status'] !== 'success' || (int) $result['added'] !== 1) {
    throw new RuntimeException('翻译内容导入测试失败。');
}
if ($repository->countRuns() !== 1) {
    throw new RuntimeException('Actions 导入未写入来源运行记录。');
}
$run = $repository->recentRuns(1)[0] ?? [];
if (($run['source_name'] ?? '') !== 'NASA' || ($run['status'] ?? '') !== 'success' || (int) ($run['added_count'] ?? 0) !== 1) {
    throw new RuntimeException('Actions 来源运行记录内容不正确。');
}
if (!isset($result['source_runs'][0]['message']) || strpos($result['source_runs'][0]['message'], '新增 1') === false) {
    throw new RuntimeException('Actions 来源导入明细未返回。');
}
$duplicateResult = (new Collector($repository, $config))->importTranslatedItems([
    [
        'source_name' => 'NASA',
        'item' => [
            'title' => '中文标题',
            'description' => '中文描述',
            'category' => '风景',
            'source_url' => 'https://example.com/gallery/1',
            'identity_source_url' => 'https://example.com/gallery/1',
            'fingerprint' => sha1('import-test'),
            'images' => [['url' => 'https://example.com/image.jpg', 'alt' => '中文替代文本']],
        ],
    ],
], [[
    'name' => 'NASA',
    'run_source_name' => 'NASA',
    'status' => 'success',
]]);
if ((int) $duplicateResult['skipped'] !== 1 || strpos($duplicateResult['source_runs'][0]['message'] ?? '', '数据库已存在') === false) {
    throw new RuntimeException('Actions 重复图集跳过原因未记录。');
}

echo "translation import tests passed\n";
