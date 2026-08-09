<section class="hero"><p class="eyebrow">DAILY COLLECTION</p><h1>每天发现新的图集</h1><p><?= h((string) cfg('site_description')) ?></p></section>
<div class="layout-grid">
    <section><div class="section-head"><h2>最新图集</h2><span><?= (int) ($page ?? 1) ?> / <?= max(1, (int) ($totalPages ?? 1)) ?></span></div>
        <?php require __DIR__ . '/partials/cards.php'; ?>
        <?php require __DIR__ . '/partials/pagination.php'; ?>
    </section>
    <aside class="sidebar"><h3>分类</h3><?php foreach ($categories as $category): ?><a class="category-link" href="<?= h(query_url(['route' => 'category', 'slug' => $category['slug']])) ?>"><?= h($category['name']) ?><small><?= (int) $category['gallery_count'] ?></small></a><?php endforeach; ?></aside>
</div>
