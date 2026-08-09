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
    <?php if (is_file($styleFile)) : ?>
    <style><?= file_get_contents($styleFile) ?></style>
    <?php endif; ?>
</head>
<body>
<header class="site-header"><div class="container nav">
    <a class="brand" href="<?= h(site_url()) ?>"><?= h((string) cfg('site_name')) ?></a>
    <form class="search" method="get" action="<?= h(site_url('index.php')) ?>">
        <input type="hidden" name="route" value="search"><input name="q" placeholder="搜索图集" value="<?= h((string) ($_GET['q'] ?? '')) ?>"><button>搜索</button>
    </form>
</div></header>
<main class="container">
<?php require $viewFile; ?>
</main>
<footer class="site-footer"><div class="container">© <?= date('Y') ?> <?= h((string) cfg('site_name')) ?> · <a href="<?= h(query_url(['route' => 'feed.xml'])) ?>">RSS</a></div></footer>
</body>
</html>
