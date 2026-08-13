<?php
$images = array_values((array) $gallery['images']);
$heroImage = $images[0] ?? null;
$detailImages = $heroImage === null ? [] : array_slice($images, 1);
$imageCount = count($images);
?>
<article class="gallery-detail">
    <?php if ($heroImage !== null): ?>
    <section class="gallery-spotlight" aria-label="图集主图">
        <figure class="gallery-hero-frame">
            <img fetchpriority="high" src="<?= h(display_image_url((string) $heroImage['local_path'], (string) $heroImage['source_url'])) ?>" alt="<?= h($heroImage['alt_text'] !== '' ? $heroImage['alt_text'] : $gallery['title']) ?>">
            <?php if ($heroImage['alt_text'] !== ''): ?><figcaption><?= h($heroImage['alt_text']) ?></figcaption><?php endif; ?>
        </figure>
        <aside class="gallery-summary" aria-label="图集信息">
            <p class="eyebrow"><a href="<?= h(query_url(['route' => 'category', 'slug' => $gallery['category_slug']])) ?>">← <?= h($gallery['category_name']) ?></a></p>
            <h1><?= h($gallery['title']) ?></h1>
            <?php if ($gallery['description'] !== ''): ?><p class="lead"><?= h($gallery['description']) ?></p><?php endif; ?>
            <div class="gallery-meta"><span><?= $imageCount ?> 张图片</span><span><?= h($gallery['created_at']) ?></span></div>
        </aside>
    </section>
    <?php else: ?>
    <header class="gallery-empty-heading">
        <p class="eyebrow"><a href="<?= h(query_url(['route' => 'category', 'slug' => $gallery['category_slug']])) ?>">← <?= h($gallery['category_name']) ?></a></p>
        <h1><?= h($gallery['title']) ?></h1>
        <p class="lead">这个图集暂时没有可展示的图片。</p>
    </header>
    <?php endif; ?>

    <?php if ($detailImages): ?>
    <section class="gallery-stream" aria-label="更多图片">
        <div class="gallery-stream-head">
            <div><p class="section-kicker">BROWSE SET</p><h2>继续浏览</h2></div>
            <span><?= count($detailImages) ?> 张图片</span>
        </div>
        <div class="gallery-images">
            <?php foreach ($detailImages as $image): ?><figure class="gallery-frame"><img loading="lazy" src="<?= h(display_image_url((string) $image['local_path'], (string) $image['source_url'])) ?>" alt="<?= h($image['alt_text'] !== '' ? $image['alt_text'] : $gallery['title']) ?>"><?php if ($image['alt_text'] !== ''): ?><figcaption><?= h($image['alt_text']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($gallery['source_url'] !== ''): ?><p class="source-link">图片来源：<a rel="nofollow noopener" href="<?= h($gallery['source_url']) ?>"><?= h($gallery['source_name']) ?></a></p><?php endif; ?>
    <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'ImageGallery', 'name' => $gallery['title'], 'description' => $gallery['description'], 'url' => query_url(['route' => 'gallery', 'slug' => $gallery['slug']])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</article>
