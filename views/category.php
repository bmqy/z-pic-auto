<section class="hero hero-gallery category-hero">
    <div class="hero-copy">
        <nav class="breadcrumbs" aria-label="面包屑导航">
            <a href="<?= h(site_url()) ?>">首页</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= h($category['name']) ?></span>
        </nav>
        <p class="eyebrow">COLLECTION / <?= h(strtoupper((string) $category['name'])) ?></p>
        <h1><?= h($category['name']) ?></h1>
        <p><?= (int) ($galleryCount ?? 0) ?> 组图集，按时间持续归档</p>
    </div>
    <div class="hero-note"><span class="hero-note-dot"></span><span>持续更新<br><strong>主题归档</strong></span></div>
</section>
<?php require __DIR__ . '/partials/cards.php'; ?>
<?php require __DIR__ . '/partials/pagination.php'; ?>
