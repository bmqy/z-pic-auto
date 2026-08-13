<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';
require_once __DIR__ . '/../app/Collector.php';

function assert_bangumi_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = require __DIR__ . '/../config/local.example.php';
$config['database'] = ['driver' => 'sqlite'];
$config['download_images'] = false;
$GLOBALS['config'] = $config;
$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new Repository($database, $config);
$repository->ensureSchema();
$collector = new Collector($repository, $config);

$retryDelay = new ReflectionMethod(Collector::class, 'retryDelay');
$retryDelay->setAccessible(true);
assert_bangumi_test($retryDelay->invoke($collector, [], 0, 429) === 30, '429 默认重试间隔未增加。');
assert_bangumi_test($retryDelay->invoke($collector, [], 1, 429) === 60, '429 第二次重试间隔不正确。');
assert_bangumi_test($retryDelay->invoke($collector, ['retry_delays' => [7, 11]], 1, 429) === 11, '来源自定义重试间隔未生效。');

$buildUrl = new ReflectionMethod(Collector::class, 'buildSourceRequestUrl');
$buildUrl->setAccessible(true);
$requestUrl = $buildUrl->invoke($collector, [
    'type' => 'bangumi',
    'url' => 'https://api.bgm.tv/v0/subjects',
    'params' => ['type' => 2, 'sort' => 'rank'],
    'limit' => 1,
    'offset' => 10,
], 'bangumi');
$query = [];
parse_str((string) parse_url($requestUrl, PHP_URL_QUERY), $query);
assert_bangumi_test((int) $query['type'] === 2, 'Bangumi type 参数未构造。');
assert_bangumi_test($query['sort'] === 'rank', 'Bangumi sort 参数未构造。');
assert_bangumi_test((int) $query['limit'] === 1, 'Bangumi limit 参数未构造。');
assert_bangumi_test((int) $query['offset'] === 10, 'Bangumi offset 参数未构造。');

$defaultBangumiSource = array_values(array_filter((array) $config['sources'], function (array $source): bool {
    return strtolower((string) ($source['type'] ?? '')) === 'bangumi';
}))[0] ?? [];
$defaultRequestUrl = $buildUrl->invoke($collector, $defaultBangumiSource, 'bangumi');
$defaultQuery = [];
parse_str((string) parse_url($defaultRequestUrl, PHP_URL_QUERY), $defaultQuery);
assert_bangumi_test((int) ($defaultQuery['type'] ?? 0) === 2, '默认 Bangumi 请求缺少 type 参数。');
assert_bangumi_test(($defaultQuery['sort'] ?? '') === 'rank', '默认 Bangumi 请求缺少 rank 排序。');
assert_bangumi_test((int) ($defaultQuery['limit'] ?? 0) === 1, '默认 Bangumi 请求缺少 limit 参数。');
assert_bangumi_test((int) ($defaultQuery['offset'] ?? -1) === 0, '默认 Bangumi 请求缺少 offset 参数。');

$parse = new ReflectionMethod(Collector::class, 'parseBangumi');
$parse->setAccessible(true);
$items = $parse->invoke($collector, file_get_contents(__DIR__ . '/fixtures/bangumi.json'), [
    'category' => '二次元',
]);
assert_bangumi_test(count($items) === 1, 'Bangumi data 未完整转换。');
assert_bangumi_test($items[0]['title'] === '测试动画', 'Bangumi 未优先使用中文标题。');
assert_bangumi_test($items[0]['category'] === '二次元', 'Bangumi 默认分类未设置为二次元。');
assert_bangumi_test($items[0]['source_url'] === 'https://bgm.tv/subject/12345', 'Bangumi 条目来源链接未生成。');
assert_bangumi_test($items[0]['images'][0]['url'] === 'https://lain.bgm.tv/pic/cover/l/ab/cd/12345_test.jpg', 'Bangumi 未选择大图封面。');

$calendarItems = $parse->invoke($collector, json_encode([[
    'weekday' => ['en' => 'Mon'],
    'items' => [[
        'id' => 54321,
        'name' => 'Calendar Anime',
        'name_cn' => '日历动画',
        'images' => ['large' => 'https://lain.bgm.tv/pic/cover/l/calendar.jpg'],
    ]],
]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
    'category' => '二次元',
]);
assert_bangumi_test(count($calendarItems) === 1, 'Bangumi 日历降级响应未解析。');
assert_bangumi_test($calendarItems[0]['title'] === '日历动画', 'Bangumi 日历未优先使用中文标题。');

echo "bangumi tests passed\n";
