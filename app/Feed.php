<?php
declare(strict_types=1);

final class Feed
{
    /**
     * 判断当前请求是否来自需要网页展示的浏览器。
     */
    public static function prefersHtml(string $accept): bool
    {
        return stripos($accept, 'text/html') !== false && stripos($accept, 'application/rss+xml') === false;
    }

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

    /**
     * 输出供手机浏览器直接阅读的 HTML 版本，避免移动浏览器不支持 RSS XSLT 时显示空白。
     */
    public static function renderHtml(array $items): string
    {
        $title = self::escapeHtml((string) cfg('site_name'));
        $description = self::escapeHtml((string) cfg('site_description'));
        $html = [
            '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">',
            '<title>' . $title . ' RSS</title>',
            '<style>body{margin:0;padding:24px 16px 48px;background:#f3f1ed;color:#18201d;font:16px/1.6 system-ui,sans-serif}main{max-width:860px;margin:auto}header{border-bottom:1px solid #dfe2dc;margin-bottom:20px;padding-bottom:16px}h1{margin:0 0 6px;font-size:1.8rem}p{margin:8px 0}.muted,time{color:#77807b}article{background:#fbfaf7;border:1px solid #dfe2dc;border-radius:12px;margin:14px 0;padding:16px 18px}h2{margin:0 0 4px;font-size:1.15rem}a{color:#b9402d}</style>',
            '</head><body><main><header><h1>' . $title . ' RSS</h1><p class="muted">' . $description . '</p></header>',
        ];

        foreach ($items as $item) {
            $itemUrl = self::escapeHtml(query_url(['route' => 'gallery', 'slug' => (string) $item['slug']]));
            $publishedAt = (string) ($item['updated_at'] ?? $item['created_at'] ?? '');
            $published = $publishedAt !== '' && strtotime($publishedAt) !== false
                ? gmdate(DATE_RSS, (int) strtotime($publishedAt))
                : '';
            $html[] = '<article><h2><a href="' . $itemUrl . '">' . self::escapeHtml((string) $item['title']) . '</a></h2>';
            if ($published !== '') {
                $html[] = '<time datetime="' . self::escapeHtml($published) . '">' . self::escapeHtml($published) . '</time>';
            }
            $html[] = '<p>' . self::escapeHtml((string) ($item['description'] ?? '')) . '</p></article>';
        }

        $html[] = '</main></body></html>';
        return implode('', $html);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
