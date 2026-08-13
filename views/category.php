<section class="hero compact">
    <nav class="breadcrumbs" aria-label="面包屑导航">
        <a href="<?= h(site_url()) ?>">首页</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= h($category['name']) ?></span>
    </nav>
    <p class="eyebrow">COLLECTION / <?= h(strtoupper((string) $category['name'])) ?></p><h1><?= h($category['name']) ?></h1><p><?= (int) $category['gallery_count'] ?> 组图集，按时间持续归档</p>
</section>
<?php require __DIR__ . '/partials/cards.php'; ?>
<?php require __DIR__ . '/partials/pagination.php'; ?>
