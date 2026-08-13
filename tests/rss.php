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
        'title' => 'Test & Gallery',
        'slug' => 'test-gallery',
        'description' => 'Description with <XML> characters.',
        'created_at' => '2026-08-10 12:00:00',
    ],
]);

$feed = simplexml_load_string($xml);
assert_rss_test($feed !== false, 'RSS XML cannot be parsed.');
assert_rss_test((string) $feed->channel->title !== '', 'RSS channel title is missing.');
assert_rss_test(count($feed->channel->item) === 1, 'RSS item count is incorrect.');
assert_rss_test((string) $feed->channel->item->guid !== '', 'RSS item guid is missing.');
assert_rss_test((string) $feed->channel->item->pubDate !== '', 'RSS item pubDate is missing.');
assert_rss_test(strpos($xml, '<?xml-stylesheet type="text/xsl" href="http://example.com/assets/rss.xsl"?>') !== false, 'RSS stylesheet path is not absolute.');

echo "rss tests passed\n";
