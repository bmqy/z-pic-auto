<?php
declare(strict_types=1);

try {
    require __DIR__ . '/app/bootstrap.php';
    $route = trim((string) ($_GET['route'] ?? ''), '/');
    if ($route === '' || $route === 'home') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) cfg('per_page', 18));
        Template::render('home', [
            'pageTitle' => cfg('site_name'),
            'galleries' => $repository->recentGalleries($perPage, ($page - 1) * $perPage),
            'page' => $page,
            'totalPages' => (int) ceil($repository->countGalleries() / $perPage),
            'categories' => $repository->categories(),
        ]);
        exit;
    }
    if ($route === 'gallery') {
        $gallery = $repository->findGallery((string) ($_GET['slug'] ?? ''));
        if (!$gallery) {
            http_response_code(404);
            Template::render('error', ['pageTitle' => '图集不存在', 'message' => '这个图集可能已被删除或尚未发布。']);
            exit;
        }
        Template::render('gallery', ['pageTitle' => $gallery['title'], 'gallery' => $gallery]);
        exit;
    }
    if ($route === 'category') {
        $category = $repository->categoryByName((string) ($_GET['slug'] ?? ''));
        if (!$category) {
            http_response_code(404);
            Template::render('error', ['pageTitle' => '分类不存在', 'message' => '请返回首页选择已有分类。']);
            exit;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) cfg('per_page', 18));
        Template::render('category', [
            'pageTitle' => $category['name'],
            'category' => $category,
            'galleries' => $repository->recentGalleries($perPage, ($page - 1) * $perPage, (int) $category['id']),
            'page' => $page,
            'totalPages' => (int) ceil($repository->countGalleries((int) $category['id']) / $perPage),
        ]);
        exit;
    }
    if ($route === 'search') {
        $query = trim((string) ($_GET['q'] ?? ''));
        Template::render('search', [
            'pageTitle' => $query !== '' ? '搜索：' . $query : '搜索图集',
            'query' => $query,
            'galleries' => $query !== '' ? $repository->search($query, 50) : [],
        ]);
        exit;
    }
    if ($route === 'image') {
        $relativePath = str_replace('\\', '/', trim((string) ($_GET['path'] ?? '')));
        $imagePrefix = trim((string) cfg('image_url_prefix', 'storage/images'), '/');
        $prefix = $imagePrefix . '/';
        if ($relativePath === '' || strpos($relativePath, $prefix) !== 0) {
            http_response_code(404);
            exit('Image not found');
        }
        $filename = substr($relativePath, strlen($prefix));
        if ($filename === '' || strpos($filename, '/') !== false || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            http_response_code(404);
            exit('Image not found');
        }
        $file = rtrim((string) cfg('image_dir'), '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($file)) {
            http_response_code(404);
            exit('Image not found');
        }
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($file));
        header('Cache-Control: public, max-age=604800');
        readfile($file);
        exit;
    }
    if ($route === 'sitemap.xml') {
        header('Content-Type: application/xml; charset=UTF-8');
        $urls = [site_url('/')];
        foreach ($repository->categories() as $category) {
            $urls[] = query_url(['route' => 'category', 'slug' => $category['slug']]);
        }
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($urls as $url) {
            $xml[] = '<url><loc>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</loc></url>';
        }
        foreach ($repository->allPublishedForSitemap() as $gallery) {
            $xml[] = '<url><loc>' . htmlspecialchars(query_url(['route' => 'gallery', 'slug' => $gallery['slug']]), ENT_XML1, 'UTF-8') . '</loc><lastmod>' . date('c', strtotime($gallery['updated_at'])) . '</lastmod></url>';
        }
        $xml[] = '</urlset>';
        echo implode('', $xml);
        exit;
    }
    if ($route === 'robots.txt') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /storage/\nSitemap: " . site_url('index.php?route=sitemap.xml') . "\n";
        exit;
    }
    if ($route === 'feed.xml') {
        header('Vary: Accept');
        header('Cache-Control: no-cache, must-revalidate');
        $items = $repository->recentGalleries(20);
        if (Feed::prefersHtml((string) ($_SERVER['HTTP_ACCEPT'] ?? ''))) {
            header('Content-Type: text/html; charset=UTF-8');
            echo Feed::renderHtml($items);
        } else {
            header('Content-Type: application/rss+xml; charset=UTF-8');
            echo Feed::render($items);
        }
        exit;
    }
    if ($route === 'feed.html') {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
        echo Feed::renderHtml($repository->recentGalleries(20));
        exit;
    }
    if ($route === 'task/import') {
        $token = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''));
        if ($token === '' || !hash_equals((string) cfg('admin_token'), $token)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
            http_response_code(400);
            echo 'Invalid import payload';
            exit;
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode((new Collector($repository, $config))->importTranslatedItems($payload['items']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($route === 'task/collect') {
        $token = (string) ($_GET['token'] ?? '');
        if ($token === '' || !hash_equals((string) cfg('admin_token'), $token)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        header('Content-Type: text/plain; charset=UTF-8');
        if ((string) ($_GET['async'] ?? '') === '1') {
            // 先结束 HTTP 响应，再让 PHP 进程继续在站点服务器本机执行采集。
            http_response_code(202);
            echo "[accepted] collection started\n";
            ignore_user_abort(true);
            set_time_limit(0);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
                flush();
            }
        }
        $results = (new Collector($repository, $config))->runAll();
        foreach ($results as $result) {
            echo '[' . $result['status'] . '] ' . $result['source'] . ': ' . $result['message'] . "\n";
        }
        exit;
    }
    http_response_code(404);
    Template::render('error', ['pageTitle' => '页面不存在', 'message' => '找不到你访问的页面。']);
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '站点初始化失败：' . $error->getMessage();
}
