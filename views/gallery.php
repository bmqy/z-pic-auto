<article class="gallery-detail">
    <p class="eyebrow"><a href="<?= h(query_url(['route' => 'category', 'slug' => $gallery['category_slug']])) ?>"><?= h($gallery['category_name']) ?></a></p>
    <h1><?= h($gallery['title']) ?></h1>
    <?php if ($gallery['description'] !== ''): ?><p class="lead"><?= h($gallery['description']) ?></p><?php endif; ?>
    <div class="meta">来源：<?= h($gallery['source_name']) ?> · 更新于 <?= h($gallery['created_at']) ?></div>
    <div class="gallery-images">
        <?php foreach ($gallery['images'] as $image): ?><figure><img loading="lazy" src="<?= h(display_image_url((string) $image['local_path'], (string) $image['source_url'])) ?>" alt="<?= h($image['alt_text'] !== '' ? $image['alt_text'] : $gallery['title']) ?>"><figcaption><?= h($image['alt_text']) ?></figcaption></figure><?php endforeach; ?>
    </div>
    <?php if ($gallery['source_url'] !== ''): ?><p class="source-link">原始来源：<a rel="nofollow noopener" href="<?= h($gallery['source_url']) ?>"><?= h($gallery['source_url']) ?></a></p><?php endif; ?>
    <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'ImageGallery', 'name' => $gallery['title'], 'description' => $gallery['description'], 'url' => query_url(['route' => 'gallery', 'slug' => $gallery['slug']])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</article>
