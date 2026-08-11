<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '' || !hash_equals((string) cfg('admin_token'), $token)) {
    http_response_code(403);
    exit('请使用 config/local.php 中的 admin_token 访问。');
}

$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'collect') {
            $sourceIndex = isset($_POST['source_index']) && ctype_digit((string) $_POST['source_index']) ? (int) $_POST['source_index'] : null;
            $results = (new Collector($repository, $config))->runAll($sourceIndex);
        } elseif ($action === 'delete_gallery') {
            $galleryId = ctype_digit((string) ($_POST['gallery_id'] ?? '')) ? (int) $_POST['gallery_id'] : 0;
            if ($galleryId <= 0 || !$repository->deleteGallery($galleryId)) {
                throw new RuntimeException('图集不存在或已被删除。');
            }
            $results[] = ['status' => 'success', 'source' => '内容管理', 'message' => '图集及其图片已删除。'];
        } elseif ($action === 'delete_image') {
            $imageId = ctype_digit((string) ($_POST['image_id'] ?? '')) ? (int) $_POST['image_id'] : 0;
            if ($imageId <= 0 || !$repository->deleteImage($imageId)) {
                throw new RuntimeException('图片不存在或已被删除。');
            }
            $results[] = ['status' => 'success', 'source' => '内容管理', 'message' => '图片已删除，图集封面已更新。'];
        }
    } catch (Throwable $error) {
        $results[] = ['status' => 'failed', 'source' => '内容管理', 'message' => $error->getMessage()];
    }
}

$runs = $repository->recentRuns();
$galleries = $repository->adminGalleries();
$adminStyleFile = __DIR__ . '/../assets/style.css';
?><!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>采集管理 - <?= h((string) cfg('site_name')) ?></title>
    <?php if (is_file($adminStyleFile)) : ?><style><?= file_get_contents($adminStyleFile) ?></style><?php endif; ?>
</head>
<body>
<main class="container admin">
    <h1>采集管理</h1>
    <p>来源请在 <code>config/local.php</code> 中维护。老式虚拟主机建议按来源单独运行，避免单次请求超过主机执行时限。</p>

    <form method="post">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <input type="hidden" name="action" value="collect">
        <button class="button" type="submit">立即运行全部采集</button>
    </form>

    <h2>按来源运行</h2>
    <?php foreach ((array) ($config['sources'] ?? []) as $index => $source): ?>
        <?php if (!($source['enabled'] ?? false) || empty($source['url'])) { continue; } ?>
        <form method="post" class="admin-inline-form">
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="hidden" name="action" value="collect">
            <input type="hidden" name="source_index" value="<?= (int) $index ?>">
            <button class="button" type="submit">运行 <?= h((string) ($source['name'] ?? ('来源 ' . $index))) ?></button>
        </form>
    <?php endforeach; ?>

    <?php foreach ($results as $result): ?>
        <div class="notice <?= h($result['status']) ?>"><?= h($result['source'] . '：' . $result['message']) ?></div>
    <?php endforeach; ?>

    <section class="admin-section">
        <div class="admin-section-heading">
            <div>
                <p class="section-kicker">CONTENT</p>
                <h2>图集与图片</h2>
            </div>
            <span class="page-indicator">共 <?= count($galleries) ?> 个图集</span>
        </div>
        <?php if (!$galleries): ?>
            <p class="empty">暂时还没有图集。</p>
        <?php else: ?>
            <div class="admin-galleries">
                <?php foreach ($galleries as $gallery): ?>
                    <article class="admin-gallery">
                        <div class="admin-gallery-heading">
                            <div>
                                <p class="tag"><?= h((string) $gallery['category_name']) ?></p>
                                <h3><?= h((string) $gallery['title']) ?></h3>
                                <p class="admin-meta"><?= h((string) $gallery['created_at']) ?> · <?= count((array) $gallery['images']) ?> 张图片</p>
                            </div>
                            <form method="post" onsubmit="return confirm('确定删除整个图集及其图片吗？');">
                                <input type="hidden" name="token" value="<?= h($token) ?>">
                                <input type="hidden" name="action" value="delete_gallery">
                                <input type="hidden" name="gallery_id" value="<?= (int) $gallery['id'] ?>">
                                <button class="button danger" type="submit">删除图集</button>
                            </form>
                        </div>
                        <?php if (!$gallery['images']): ?>
                            <p class="empty">该图集没有图片。</p>
                        <?php else: ?>
                            <div class="admin-images">
                                <?php foreach ($gallery['images'] as $image): ?>
                                    <div class="admin-image">
                                        <a href="<?= h(display_image_url((string) $image['local_path'], (string) $image['source_url'])) ?>" target="_blank" rel="noreferrer">
                                            <img src="<?= h(display_image_url((string) $image['local_path'], (string) $image['source_url'])) ?>" alt="<?= h((string) $image['alt_text']) ?>">
                                        </a>
                                        <div class="admin-image-footer">
                                            <span>#<?= (int) $image['position'] + 1 ?></span>
                                            <form method="post" onsubmit="return confirm('确定删除这张图片吗？');">
                                                <input type="hidden" name="token" value="<?= h($token) ?>">
                                                <input type="hidden" name="action" value="delete_image">
                                                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                                                <button class="text-button danger-text" type="submit">删除图片</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-section">
        <h2>最近运行</h2>
        <table>
            <tr><th>时间</th><th>来源</th><th>状态</th><th>新增</th><th>消息</th></tr>
            <?php foreach ($runs as $run): ?>
                <tr><td><?= h($run['started_at']) ?></td><td><?= h($run['source_name']) ?></td><td><?= h($run['status']) ?></td><td><?= (int) $run['added_count'] ?></td><td><?= h($run['message']) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </section>
</main>
</body>
</html>
