<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/functions.php';

session_name('z_pic_admin');
session_start();

$configuredToken = (string) cfg('admin_token', '');
$requestToken = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$requestTokenMatches = $requestToken !== '' && $configuredToken !== '' && hash_equals($configuredToken, $requestToken);
$isAuthenticated = !empty($_SESSION['admin_authenticated']);
$loginError = '';

if ((string) ($_POST['action'] ?? '') === 'login') {
    if ($requestTokenMatches) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $isAuthenticated = true;
    } else {
        $loginError = '登录失败，请重试。';
        $isAuthenticated = false;
    }
} elseif ($requestTokenMatches) {
    // 兼容旧版 /admin/?token=... 入口，并将成功验证转换为登录态。
    $_SESSION['admin_authenticated'] = true;
    $isAuthenticated = true;
}

if ((string) ($_POST['action'] ?? '') === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    $isAuthenticated = false;
}

// 后台采集和数据库操作可能耗时较长，认证判断完成后立即释放 session 锁，避免并发请求一直等待。
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$adminStyleFile = __DIR__ . '/../assets/style.css';
if (!$isAuthenticated) {
    ?><!doctype html>
    <html lang="zh-CN">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>后台登录 - <?= h((string) cfg('site_name')) ?></title>
        <?php if (is_file($adminStyleFile)) : ?><style><?= file_get_contents($adminStyleFile) ?></style><?php endif; ?>
    </head>
    <body class="admin-page admin-login-page">
        <main class="admin-login-card">
            <a class="admin-login-brand" href="<?= h(site_url()) ?>"><span class="brand-mark">Z</span><span><strong><?= h((string) cfg('site_name')) ?></strong><small>ADMIN CONSOLE</small></span></a>
            <p class="section-kicker">ADMIN ACCESS</p>
            <h1>进入管理后台</h1>
            <p class="admin-login-lead">请输入访问凭证以继续。</p>
            <?php if ($loginError !== ''): ?><div class="notice failed" role="alert"><?= h($loginError) ?></div><?php endif; ?>
            <form id="admin-login-form" method="post" action="" class="admin-login-form">
                <input type="hidden" name="action" value="login">
                <label for="admin-credential">访问凭证</label>
                <input id="admin-credential" name="token" type="password" autocomplete="current-password" placeholder="请输入访问凭证" required autofocus>
                <button id="admin-login-button" class="button admin-login-button" type="submit"><span data-login-label>登录后台</span> <span aria-hidden="true">→</span></button>
                <p class="admin-login-status" id="admin-login-status" role="status" aria-live="polite"></p>
            </form>
            <a class="admin-back-link" href="<?= h(site_url()) ?>">← 返回网站首页</a>
        </main>
        <script>
        (function () {
            var form = document.getElementById('admin-login-form');
            var button = document.getElementById('admin-login-button');
            var status = document.getElementById('admin-login-status');
            var label = button ? button.querySelector('[data-login-label]') : null;
            if (!form || !button || !status || !label) {
                return;
            }
            form.addEventListener('submit', function (event) {
                if (form.getAttribute('aria-busy') === 'true') {
                    event.preventDefault();
                    return;
                }
                event.preventDefault();
                form.setAttribute('aria-busy', 'true');
                button.disabled = true;
                label.textContent = '正在验证';
                status.textContent = '正在验证，请稍候…';
                window.setTimeout(function () {
                    HTMLFormElement.prototype.submit.call(form);
                }, 0);
            });
        }());
        </script>
    </body>
    </html><?php
    exit;
}

// 登录校验不依赖数据库；认证成功后再初始化后台模块，避免登录请求被数据库初始化阻塞。
require __DIR__ . '/../app/bootstrap.php';

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
        $results[] = ['status' => 'failed', 'source' => '后台操作', 'message' => $error->getMessage()];
    }
}

$tab = (string) ($_GET['tab'] ?? 'dashboard');
if (!in_array($tab, ['dashboard', 'content', 'runs'], true)) {
    $tab = 'dashboard';
}
$contentPageSize = 12;
$runsPageSize = 10;
$contentTotal = $repository->countAdminGalleries();
$runsTotal = $repository->countRuns();
$contentTotalPages = max(1, (int) ceil($contentTotal / $contentPageSize));
$runsTotalPages = max(1, (int) ceil($runsTotal / $runsPageSize));
$contentPage = min($contentTotalPages, max(1, (int) ($_GET['content_page'] ?? 1)));
$runsPage = min($runsTotalPages, max(1, (int) ($_GET['runs_page'] ?? 1)));
$runs = $repository->recentRuns($tab === 'runs' ? $runsPageSize : 20, $tab === 'runs' ? ($runsPage - 1) * $runsPageSize : 0);
$galleries = $repository->adminGalleries($tab === 'content', $tab === 'content' ? $contentPageSize : null, $tab === 'content' ? ($contentPage - 1) * $contentPageSize : 0);
$totalImages = 0;
foreach ($galleries as $gallery) {
    $totalImages += (int) ($gallery['image_count'] ?? count((array) ($gallery['images'] ?? [])));
}
$enabledSources = 0;
foreach ((array) ($config['sources'] ?? []) as $source) {
    if (($source['enabled'] ?? false) && !empty($source['url'])) {
        $enabledSources++;
    }
}
$adminPageUrl = function (string $targetTab, string $pageKey, int $page): string {
    return '?' . http_build_query(['tab' => $targetTab, $pageKey => $page]);
};
?><!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h(['dashboard' => '概览', 'content' => '内容管理', 'runs' => '运行记录'][$tab]) ?> - <?= h((string) cfg('site_name')) ?></title>
    <?php if (is_file($adminStyleFile)) : ?><style><?= file_get_contents($adminStyleFile) ?></style><?php endif; ?>
</head>
<body class="admin-page">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="<?= h(site_url()) ?>"><span class="brand-mark">Z</span><span><strong><?= h((string) cfg('site_name')) ?></strong><small>ADMIN CONSOLE</small></span></a>
        <div class="admin-sidebar-label">工作台</div>
        <nav class="admin-nav" aria-label="后台导航">
            <a class="admin-nav-link<?= $tab === 'dashboard' ? ' active' : '' ?>" href="?tab=dashboard"><span>⌂</span>概览</a>
            <a class="admin-nav-link<?= $tab === 'content' ? ' active' : '' ?>" href="?tab=content"><span>▦</span>内容管理</a>
            <a class="admin-nav-link<?= $tab === 'runs' ? ' active' : '' ?>" href="?tab=runs"><span>◷</span>运行记录</a>
        </nav>
        <div class="admin-sidebar-footer">
            <a href="<?= h(site_url()) ?>">← 返回网站</a>
            <form method="post"><input type="hidden" name="action" value="logout"><button type="submit">退出登录</button></form>
        </div>
    </aside>

    <main class="admin-main admin">
        <header class="admin-topbar">
            <div><p class="section-kicker">CONTROL CENTER</p><h1><?= h(['dashboard' => '后台概览', 'content' => '内容管理', 'runs' => '运行记录'][$tab]) ?></h1></div>
            <div class="admin-topbar-status"><span class="status-dot"></span>已登录</div>
        </header>

        <?php foreach ($results as $result): ?>
            <div class="notice <?= h($result['status']) ?>"><?= h((string) $result['source'] . '：' . (string) $result['message']) ?></div>
        <?php endforeach; ?>

        <?php if ($tab === 'dashboard'): ?>
            <section class="admin-stats" aria-label="数据概览">
                <article class="admin-stat-card"><span class="admin-stat-label">图集总数</span><strong><?= count($galleries) ?></strong><small>当前已入库的图集</small></article>
                <article class="admin-stat-card"><span class="admin-stat-label">图片总数</span><strong><?= $totalImages ?></strong><small>来自全部图集的图片</small></article>
                <article class="admin-stat-card"><span class="admin-stat-label">采集来源</span><strong><?= $enabledSources ?></strong><small>当前启用的来源</small></article>
                <article class="admin-stat-card"><span class="admin-stat-label">运行记录</span><strong><?= $runsTotal ?></strong><small>累计采集运行记录</small></article>
            </section>
            <section class="admin-panel admin-quick-panel">
                <div class="admin-panel-heading"><div><p class="section-kicker">QUICK ACTIONS</p><h2>快速操作</h2></div><span class="page-indicator">无需离开当前页面</span></div>
                <div class="admin-action-grid">
                    <form method="post" class="admin-action-card js-collect-form"><input type="hidden" name="action" value="collect"><span class="admin-action-icon">↻</span><span><strong>运行全部采集</strong><small>按配置顺序执行全部启用来源</small></span><button class="button" type="submit" data-running-label="采集中…">立即运行</button></form>
                    <a class="admin-action-card" href="?tab=content"><span class="admin-action-icon">▦</span><span><strong>管理图集内容</strong><small>查看、删除图集或单张图片</small></span><span class="admin-action-arrow">→</span></a>
                    <a class="admin-action-card" href="?tab=runs"><span class="admin-action-icon">◷</span><span><strong>查看运行记录</strong><small>检查采集成功、失败与新增数量</small></span><span class="admin-action-arrow">→</span></a>
                </div>
            </section>
            <section class="admin-panel"><div class="admin-panel-heading"><div><p class="section-kicker">LATEST RUNS</p><h2>最近运行</h2></div><a class="admin-panel-link" href="?tab=runs">查看全部 →</a></div><?php if (!$runs): ?><p class="empty">还没有运行记录。</p><?php else: ?><div class="admin-run-list"><?php foreach (array_slice($runs, 0, 5) as $run): ?><div class="admin-run-row"><span class="run-status <?= h((string) $run['status']) ?>"></span><div><strong><?= h((string) $run['source_name']) ?></strong><small><?= h((string) $run['started_at']) ?></small></div><span class="admin-run-message"><?= h((string) $run['message']) ?></span><b><?= (int) $run['added_count'] ?> 新增</b></div><?php endforeach; ?></div><?php endif; ?></section>
        <?php elseif ($tab === 'content'): ?>
            <section class="admin-panel admin-collect-panel">
                <div class="admin-panel-heading"><div><p class="section-kicker">COLLECT</p><h2>运行采集</h2></div><span class="page-indicator"><?= $enabledSources ?> 个来源已启用</span></div>
                <form method="post" class="admin-collect-all js-collect-form"><input type="hidden" name="action" value="collect"><div><strong>运行全部采集</strong><small>依次执行所有已启用来源，适合日常更新。</small></div><button class="button" type="submit" data-running-label="采集中…">立即运行全部</button></form>
                <div class="admin-source-list"><?php foreach ((array) ($config['sources'] ?? []) as $index => $source): ?><?php if (!($source['enabled'] ?? false) || empty($source['url'])) { continue; } ?><form method="post" class="admin-source-row js-collect-form"><input type="hidden" name="action" value="collect"><input type="hidden" name="source_index" value="<?= (int) $index ?>"><span class="status-dot"></span><div><strong><?= h((string) ($source['name'] ?? ('来源 ' . $index))) ?></strong><small><?= h((string) ($source['type'] ?? 'source')) ?><span class="admin-source-status" data-collect-status>就绪</span></small></div><button class="text-button" type="submit" data-running-label="采集中…">运行 →</button></form><?php endforeach; ?></div>
            </section>
            <section class="admin-panel">
                <div class="admin-panel-heading"><div><p class="section-kicker">CONTENT LIBRARY</p><h2>图集与图片</h2></div><span class="page-indicator">共 <?= $contentTotal ?> 个图集</span></div>
                <?php if (!$galleries): ?>
                    <p class="empty">暂时还没有图集。</p>
                <?php else: ?>
                    <div class="admin-galleries">
                        <?php foreach ($galleries as $gallery): ?>
                            <article class="admin-gallery">
                                <span class="admin-gallery-cover"><img src="<?= h(display_image_url((string) $gallery['cover_path'], (string) $gallery['cover_path'])) ?>" alt="<?= h((string) $gallery['title']) ?>"></span>
                                <div class="admin-gallery-heading"><div><p class="tag"><?= h((string) $gallery['category_name']) ?></p><h3><?= h((string) $gallery['title']) ?></h3><p class="admin-meta"><?= h((string) $gallery['created_at']) ?> · <?= (int) $gallery['image_count'] ?> 张图片</p></div></div>
                                <form method="post" class="admin-gallery-delete js-confirm-form" data-confirm-title="删除图集" data-confirm-message="确定删除整个图集及其全部图片吗？"><input type="hidden" name="action" value="delete_gallery"><input type="hidden" name="gallery_id" value="<?= (int) $gallery['id'] ?>"><button class="button danger" type="submit">删除图集</button></form>
                                <?php if ($gallery['images']): ?>
                                    <button class="admin-images-toggle button js-images-toggle" type="button" aria-expanded="false" aria-controls="admin-images-<?= (int) $gallery['id'] ?>" data-label-open="展开图片列表" data-label-close="收起图片列表">展开图片列表</button>
                                    <div class="admin-images-list" id="admin-images-<?= (int) $gallery['id'] ?>" hidden>
                                        <?php foreach ($gallery['images'] as $imageIndex => $image): ?><div class="admin-image-row"><a class="admin-image-thumb" href="<?= h(display_image_url((string) $image['local_path'], (string) $image['source_url'])) ?>" target="_blank" rel="noreferrer"><img src="<?= h(display_image_url((string) $image['local_path'], (string) $image['source_url'])) ?>" alt="<?= h((string) $image['alt_text']) ?>"></a><div class="admin-image-info"><strong>#<?= $imageIndex + 1 ?> 图片</strong><small><?= h((string) ($image['alt_text'] ?: '无描述')) ?></small></div><form method="post" class="js-confirm-form" data-confirm-title="删除图片" data-confirm-message="确定删除这张图片吗？"><input type="hidden" name="action" value="delete_image"><input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>"><button class="text-button danger-text" type="submit">删除图片</button></form></div><?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($contentTotalPages > 1): ?><nav class="admin-pagination" aria-label="内容分页"><a class="admin-pagination-link<?= $contentPage <= 1 ? ' disabled' : '' ?>" href="<?= h($contentPage <= 1 ? '#' : $adminPageUrl('content', 'content_page', $contentPage - 1)) ?>">← 上一页</a><span>第 <?= $contentPage ?> / <?= $contentTotalPages ?> 页</span><a class="admin-pagination-link<?= $contentPage >= $contentTotalPages ? ' disabled' : '' ?>" href="<?= h($contentPage >= $contentTotalPages ? '#' : $adminPageUrl('content', 'content_page', $contentPage + 1)) ?>">下一页 →</a></nav><?php endif; ?>
            </section>
        <?php else: ?>
            <section class="admin-panel"><div class="admin-panel-heading"><div><p class="section-kicker">ACTIVITY LOG</p><h2>最近运行记录</h2></div><span class="page-indicator">共 <?= $runsTotal ?> 条</span></div><?php if (!$runs): ?><p class="empty">还没有运行记录。</p><?php else: ?><div class="admin-table-wrap"><table><thead><tr><th>时间</th><th>来源</th><th>状态</th><th>新增</th><th>消息</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><?= h((string) $run['started_at']) ?></td><td><?= h((string) $run['source_name']) ?></td><td><span class="run-badge <?= h((string) $run['status']) ?>"><?= h((string) $run['status']) ?></span></td><td><?= (int) $run['added_count'] ?></td><td><?= h((string) $run['message']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php if ($runsTotalPages > 1): ?><nav class="admin-pagination" aria-label="运行记录分页"><a class="admin-pagination-link<?= $runsPage <= 1 ? ' disabled' : '' ?>" href="<?= h($runsPage <= 1 ? '#' : $adminPageUrl('runs', 'runs_page', $runsPage - 1)) ?>">← 上一页</a><span>第 <?= $runsPage ?> / <?= $runsTotalPages ?> 页</span><a class="admin-pagination-link<?= $runsPage >= $runsTotalPages ? ' disabled' : '' ?>" href="<?= h($runsPage >= $runsTotalPages ? '#' : $adminPageUrl('runs', 'runs_page', $runsPage + 1)) ?>">下一页 →</a></nav><?php endif; ?></section>
        <?php endif; ?>
    </main>
</div>

<div class="admin-modal" id="admin-confirm-modal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <section class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title" aria-describedby="admin-modal-message">
        <div class="admin-modal-icon" aria-hidden="true">!</div>
        <div class="admin-modal-copy">
            <p class="section-kicker">请确认操作</p>
            <h2 id="admin-modal-title">确认删除</h2>
            <p id="admin-modal-message">删除后内容将无法恢复。</p>
        </div>
        <div class="admin-modal-actions">
            <button class="admin-modal-cancel" type="button" data-modal-close>取消</button>
            <button class="admin-modal-confirm" type="button" data-modal-confirm>确认删除</button>
        </div>
    </section>
</div>
<script>
(function () {
    var toggles = document.querySelectorAll('.js-images-toggle');
    if (!toggles.length) {
        return;
    }

    Array.prototype.forEach.call(toggles, function (toggle) {
        toggle.addEventListener('click', function () {
            var list = document.getElementById(toggle.getAttribute('aria-controls'));
            if (!list) {
                return;
            }
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            toggle.textContent = expanded ? toggle.getAttribute('data-label-open') : toggle.getAttribute('data-label-close');
            list.hidden = expanded;
        });
    });
}());

(function () {
    var modal = document.getElementById('admin-confirm-modal');
    if (!modal) {
        return;
    }
    var title = document.getElementById('admin-modal-title');
    var message = document.getElementById('admin-modal-message');
    var confirmButton = modal.querySelector('[data-modal-confirm]');
    var pendingForm = null;
    var lastFocused = null;

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        pendingForm = null;
        window.setTimeout(function () {
            modal.hidden = true;
        }, 180);
        if (lastFocused) {
            lastFocused.focus();
            lastFocused = null;
        }
    }

    function openModal(form) {
        pendingForm = form;
        lastFocused = document.activeElement;
        title.textContent = form.getAttribute('data-confirm-title') || '确认操作';
        message.textContent = form.getAttribute('data-confirm-message') || '确定继续吗？';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-open');
        });
        confirmButton.focus();
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches || !form.matches('.js-confirm-form')) {
            return;
        }
        event.preventDefault();
        openModal(form);
    });

    modal.addEventListener('click', function (event) {
        if (event.target.hasAttribute('data-modal-close')) {
            closeModal();
        }
    });

    confirmButton.addEventListener('click', function () {
        if (pendingForm) {
            pendingForm.submit();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
}());

(function () {
    var collectForms = document.querySelectorAll('.js-collect-form');
    if (!collectForms.length) {
        return;
    }

    Array.prototype.forEach.call(collectForms, function (form) {
        form.addEventListener('submit', function () {
            if (form.getAttribute('aria-busy') === 'true') {
                return;
            }
            form.setAttribute('aria-busy', 'true');
            form.classList.add('is-running');

            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = button.getAttribute('data-running-label') || '运行中…';
            }

            var status = form.querySelector('[data-collect-status]');
            if (status) {
                status.textContent = '运行中…';
            }
        });
    });
}());
</script>
</body>
</html>
