<section class="hero compact"><p class="eyebrow">CATEGORY</p><h1><?= h($category['name']) ?></h1><p>浏览 <?= (int) $category['gallery_count'] ?> 个图集</p></section>
<?php require __DIR__ . '/partials/cards.php'; ?>
<?php require __DIR__ . '/partials/pagination.php'; ?>
