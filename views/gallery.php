<article class="gallery-detail">
    <header class="gallery-heading">
        <div><p class="eyebrow"><a href="<?= h(query_url(['route' => 'category', 'slug' => $gallery['category_slug']])) ?>">← <?= h($gallery['category_name']) ?></a></p><h1><?= h($gallery['title']) ?></h1><?php if ($gallery['description'] !== ''): ?><p class="lead"><?= h($gallery['description']) ?></p><?php endif; ?></div>
        <div class="gallery-meta"><span><?= count($gallery['images']) ?> 张图片</span><span><?= h($gallery['created_at']) ?></span></div>
    </header>
    <div class="gallery-images">
        <?php foreach ($gallery['images'] as $image): ?><figure class="gallery-frame"><img loading="lazy" src="<?= h(display_image_url((string) $image['local_path'], (string) $image['source_url'])) ?>" alt="<?= h($image['alt_text'] !== '' ? $image['alt_text'] : $gallery['title']) ?>"><?php if ($image['alt_text'] !== ''): ?><figcaption><?= h($image['alt_text']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?>
    </div>
    <?php if ($gallery['source_url'] !== ''): ?><p class="source-link">图片来源：<a rel="nofollow noopener" href="<?= h($gallery['source_url']) ?>"><?= h($gallery['source_name']) ?></a></p><?php endif; ?>
    <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'ImageGallery', 'name' => $gallery['title'], 'description' => $gallery['description'], 'url' => query_url(['route' => 'gallery', 'slug' => $gallery['slug']])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</article>
