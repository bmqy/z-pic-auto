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
assert_rss_test(Feed::prefersHtml('text/html,application/xhtml+xml') === true, '普通浏览器请求未识别为 HTML。');
assert_rss_test(Feed::prefersHtml('application/rss+xml,application/xml') === false, 'RSS 阅读器请求被错误识别为 HTML。');
$html = Feed::renderHtml([
    [
        'title' => 'HTML 测试',
        'slug' => 'html-test',
        'description' => '手机浏览器展示测试。',
        'created_at' => '2026-08-10 12:00:00',
    ],
]);
assert_rss_test(strpos($html, '<!doctype html>') === 0, 'HTML Feed 未输出文档结构。');
assert_rss_test(strpos($html, 'HTML 测试') !== false, 'HTML Feed 缺少图集内容。');

echo "rss tests passed\n";
