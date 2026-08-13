<?php
declare(strict_types=1);

final class Feed
{
    public static function render(array $items): string
    {
        $feedUrl = query_url(['route' => 'feed.xml']);
        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?xml-stylesheet type="text/xsl" href="' . self::escape(site_url('assets/rss.xsl')) . '"?>',
            '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">',
            '<channel>',
            '<title>' . self::escape((string) cfg('site_name')) . '</title>',
            '<link>' . self::escape(site_url()) . '</link>',
            '<description>' . self::escape((string) cfg('site_description')) . '</description>',
            '<atom:link href="' . self::escape($feedUrl) . '" rel="self" type="application/rss+xml"/>',
            '<lastBuildDate>' . gmdate(DATE_RSS) . '</lastBuildDate>',
        ];

        foreach ($items as $item) {
            $itemUrl = query_url(['route' => 'gallery', 'slug' => (string) $item['slug']]);
            $publishedAt = (string) ($item['updated_at'] ?? $item['created_at'] ?? '');
            $xml[] = '<item>';
            $xml[] = '<title>' . self::escape((string) $item['title']) . '</title>';
            $xml[] = '<link>' . self::escape($itemUrl) . '</link>';
            $xml[] = '<guid isPermaLink="true">' . self::escape($itemUrl) . '</guid>';
            if ($publishedAt !== '' && strtotime($publishedAt) !== false) {
                $xml[] = '<pubDate>' . gmdate(DATE_RSS, (int) strtotime($publishedAt)) . '</pubDate>';
            }
            $xml[] = '<description>' . self::escape((string) ($item['description'] ?? '')) . '</description>';
            $xml[] = '</item>';
        }

        $xml[] = '</channel>';
        $xml[] = '</rss>';
        return implode('', $xml);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
