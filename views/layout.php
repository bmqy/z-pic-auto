<?php
declare(strict_types=1);
$description = $description ?? (string) cfg('site_description', '');
$canonical = $canonical ?? query_url(array_filter(['route' => $_GET['route'] ?? 'home', 'slug' => $_GET['slug'] ?? null]));
$styleFile = __DIR__ . '/../assets/style.css';
?><!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h((string) $pageTitle) ?> - <?= h((string) cfg('site_name')) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <meta property="og:title" content="<?= h((string) $pageTitle) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <meta property="og:type" content="website">
    <link rel="alternate" type="application/rss+xml" title="RSS" href="<?= h(query_url(['route' => 'feed.xml'])) ?>">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-0YZ4XY57DC"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-0YZ4XY57DC');
    </script>
    <?php if (is_file($styleFile)) : ?>
    <style><?= file_get_contents($styleFile) ?></style>
    <?php endif; ?>
</head>
<body>
<header class="site-header"><div class="container nav">
    <a class="brand" href="<?= h(site_url()) ?>"><span class="brand-mark">Z</span><span><strong><?= h((string) cfg('site_name')) ?></strong><small>视觉档案 · IMAGE COLLECTIONS</small></span></a>
    <form class="search" method="get" action="<?= h(site_url('index.php')) ?>">
        <input type="hidden" name="route" value="search"><input name="q" placeholder="搜索图集、主题或关键词" value="<?= h((string) ($_GET['q'] ?? '')) ?>"><button>搜索</button>
    </form>
</div></header>
<main class="container">
<?php require $viewFile; ?>
</main>
<footer class="site-footer"><div class="container"><span>© <?= date('Y') ?> <?= h((string) cfg('site_name')) ?></span><span><a href="<?= h(query_url(['route' => 'feed.xml'])) ?>">订阅 RSS</a><a class="site-admin-link" href="<?= h(site_url('admin/')) ?>">后台管理</a></span></div></footer>
</body>
</html>
