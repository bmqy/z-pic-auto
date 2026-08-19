<?php
$paginationParams = ['route' => $paginationRoute ?? ($_GET['route'] ?? 'home')];
if (($paginationSlug ?? '') !== '') {
    $paginationParams['slug'] = $paginationSlug;
}
if (($totalPages ?? 1) > 1):
?><nav class="pagination"><?php if ($page > 1): ?><a href="<?= h(query_url(array_merge($paginationParams, ['page' => $page - 1]))) ?>">← 上一页</a><?php endif; ?><span>第 <?= (int) $page ?> / <?= (int) $totalPages ?> 页</span><?php if ($page < $totalPages): ?><a href="<?= h(query_url(array_merge($paginationParams, ['page' => $page + 1]))) ?>">下一页 →</a><?php endif; ?></nav><?php endif; ?>
