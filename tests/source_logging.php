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

echo "source logging tests passed\n";
