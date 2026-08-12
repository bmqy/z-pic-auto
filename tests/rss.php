<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Feed.php';

function assert_rss_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$GLOBALS['config'] = require __DIR__ . '/../config/local.example.php';
$xml = Feed::render([
    [
        'title' => '测试 & 图集',
        'slug' => 'test-gallery',
        'description' => '包含 <XML> 字符的描述。',
        'created_at' => '2026-08-10 12:00:00',
    ],
]);

$feed = simplexml_load_string($xml);
assert_rss_test($feed !== false, 'RSS XML 无法解析。');
assert_rss_test((string) $feed->channel->title === 'Z-Pic Auto 图集站', 'RSS 频道标题缺失。');
assert_rss_test(count($feed->channel->item) === 1, 'RSS 图集条目数量不正确。');
assert_rss_test((string) $feed->channel->item->guid !== '', 'RSS 条目缺少 guid。');
assert_rss_test((string) $feed->channel->item->pubDate !== '', 'RSS 条目缺少发布时间。');
assert_rss_test(strpos($xml, '<?xml-stylesheet type="text/xsl" href="http://example.com/assets/rss.xsl"?>') !== false, 'RSS 缺少绝对路径的浏览器展示样式。');

echo "rss tests passed\n";
