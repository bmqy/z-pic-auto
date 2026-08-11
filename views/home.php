<section class="hero hero-gallery">
    <div class="hero-copy"><p class="eyebrow">DAILY VISUAL ARCHIVE</p><h1>把时间，收进一张张图里</h1><p><?= h((string) cfg('site_description')) ?></p></div>
    <div class="hero-note"><span class="hero-note-dot"></span><span>持续更新<br><strong>授权图集</strong></span></div>
</section>
<div class="layout-grid">
    <section class="collection-section"><div class="section-head"><div><p class="section-kicker">CURATED SETS</p><h2>最新图集</h2></div><span class="page-indicator"><?= (int) ($page ?? 1) ?> / <?= max(1, (int) ($totalPages ?? 1)) ?></span></div>
        <?php require __DIR__ . '/partials/cards.php'; ?>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
    </section>
    <aside class="sidebar"><div class="sidebar-heading"><p class="section-kicker">EXPLORE BY</p><h3>主题分类</h3></div><?php foreach ($categories as $category): ?><a class="category-link" href="<?= h(query_url(['route' => 'category', 'slug' => $category['slug']])) ?>"><span><?= h($category['name']) ?></span><small><?= (int) $category['gallery_count'] ?> 组</small></a><?php endforeach; ?></aside>
</div>
