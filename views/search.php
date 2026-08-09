<section class="hero compact"><p class="eyebrow">SEARCH</p><h1><?= $query !== '' ? '搜索结果：' . h($query) : '搜索图集' ?></h1></section>
<?php if ($query === ''): ?><p class="empty">请输入关键词。</p><?php else: require __DIR__ . '/partials/cards.php'; endif; ?>
