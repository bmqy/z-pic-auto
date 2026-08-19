<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';

function assert_url_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$GLOBALS['config'] = ['site_url' => 'https://example.com'];

assert_url_test(query_url(['route' => 'home']) === 'https://example.com/', '首页 URL 不正确。');
assert_url_test(query_url(['route' => 'gallery', 'slug' => 'spring-gallery']) === 'https://example.com/gallery/spring-gallery', '图集 URL 不正确。');
assert_url_test(query_url(['route' => 'category', 'slug' => 'nature', 'page' => 2]) === 'https://example.com/category/nature?page=2', '分类分页 URL 不正确。');
assert_url_test(query_url(['route' => 'sitemap.xml']) === 'https://example.com/sitemap.xml', '站点地图 URL 不正确。');
assert_url_test(query_url(['route' => 'search', 'q' => '春日 山野']) === 'https://example.com/index.php?route=search&q=%E6%98%A5%E6%97%A5%20%E5%B1%B1%E9%87%8E', '搜索 URL 不正确。');

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/gallery/spring-gallery?utm_source=test';
assert_url_test(request_path() === '/gallery/spring-gallery', '根目录伪静态路径解析不正确。');
assert_url_test(request_segments() === ['gallery', 'spring-gallery'], '根目录伪静态片段解析不正确。');

$_SERVER['SCRIPT_NAME'] = '/zpic/index.php';
$_SERVER['REQUEST_URI'] = '/zpic/category/nature/';
assert_url_test(request_path() === '/category/nature', '子目录伪静态路径解析不正确。');
assert_url_test(request_segments() === ['category', 'nature'], '子目录伪静态片段解析不正确。');

unset($_SERVER['REQUEST_URI']);
$_SERVER['UNENCODED_URL'] = '/zpic/gallery/fallback-gallery';
assert_url_test(request_path() === '/gallery/fallback-gallery', 'IIS URL 回退路径解析不正确。');
assert_url_test(request_segments() === ['gallery', 'fallback-gallery'], 'IIS URL 回退片段解析不正确。');

echo "url tests passed\n";
