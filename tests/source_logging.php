<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/SourceLogger.php';

function assert_source_logging_test(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sources = [
    [
        'name' => 'NASA 示例',
        'type' => 'rss',
        'url' => 'https://example.com/feed.xml',
        'enabled' => true,
    ],
    [
        'name' => '禁用来源',
        'type' => 'html',
        'url' => 'https://example.com/gallery',
        'enabled' => false,
    ],
];

$lines = SourceLogger::formatSources($sources);
assert_source_logging_test(count($lines) === 2, '来源日志行数不正确。');
assert_source_logging_test(strpos($lines[0], '"enabled":true') !== false, '来源日志未记录启用状态。');
assert_source_logging_test(strpos($lines[0], 'https://example.com/feed.xml') !== false, '来源日志未记录 URL。');
assert_source_logging_test(SourceLogger::enabledCount($sources) === 1, '启用来源计数不正确。');
assert_source_logging_test(strpos(SourceLogger::formatSummary(['enabled_sources' => 1]), '[actions-source-summary]') === 0, '来源汇总日志前缀不正确。');
$resultLines = SourceLogger::formatResults([[
    'index' => 0,
    'status' => 'failed',
    'fetched_items' => 0,
    'exported_items' => 0,
    'skipped_items' => 0,
    'error' => 'HTTP 429',
]]);
assert_source_logging_test(strpos($resultLines[0], '[actions-source-result]') === 0, '来源结果日志前缀不正确。');
assert_source_logging_test(strpos($resultLines[0], 'HTTP 429') !== false, '来源结果日志未记录错误信息。');

$sourceLines = SourceLogger::formatSources([[
    'enabled' => true,
    'name' => 'Bangumi 动画条目',
    'type' => 'bangumi',
    'url' => 'https://api.bgm.tv/v0/subjects',
    'params' => ['type' => 2, 'limit' => 1],
]]);
assert_source_logging_test(strpos($sourceLines[0], '"params":{"type":2,"limit":1}') !== false, 'Bangumi 请求参数未写入 Actions 配置日志。');

$nasaLines = SourceLogger::formatSources([[
    'enabled' => true,
    'name' => 'NASA 突发新闻',
    'type' => 'rss',
    'url' => 'https://www.nasa.gov/rss/dyn/breaking_news.rss',
    'request_delay' => 5,
    'retry_delays' => [30, 60, 120],
]]);
assert_source_logging_test(strpos($nasaLines[0], '"retry_delays":[30,60,120]') !== false, 'NASA 重试间隔未写入 Actions 配置日志。');

echo "source logging tests passed\n";
