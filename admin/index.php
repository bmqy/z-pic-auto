<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '' || !hash_equals((string) cfg('admin_token'), $token)) {
    http_response_code(403);
    exit('请使用 config/local.php 中的 admin_token 访问。');
}
$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'collect') {
    $sourceIndex = isset($_POST['source_index']) && ctype_digit((string) $_POST['source_index']) ? (int) $_POST['source_index'] : null;
    $results = (new Collector($repository, $config))->runAll($sourceIndex);
}
$runs = $repository->recentRuns();
$adminStyleFile = __DIR__ . '/../assets/style.css';
?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>采集管理 - <?= h((string) cfg('site_name')) ?></title><?php if (is_file($adminStyleFile)) : ?><style><?= file_get_contents($adminStyleFile) ?></style><?php endif; ?></head><body><main class="container admin"><h1>采集管理</h1><p>来源请在 <code>config/local.php</code> 中维护。老式虚拟主机建议按来源单独运行，避免单次请求超过主机执行时限。</p><form method="post"><input type="hidden" name="token" value="<?= h($token) ?>"><input type="hidden" name="action" value="collect"><button class="button" type="submit">立即运行全部采集</button></form><h2>按来源运行</h2><?php foreach ((array) ($config['sources'] ?? []) as $index => $source): ?><?php if (!($source['enabled'] ?? false) || empty($source['url'])) { continue; } ?><form method="post" style="display:inline-block;margin:0 8px 8px 0"><input type="hidden" name="token" value="<?= h($token) ?>"><input type="hidden" name="action" value="collect"><input type="hidden" name="source_index" value="<?= (int) $index ?>"><button class="button" type="submit">运行 <?= h((string) ($source['name'] ?? ('来源 ' . $index))) ?></button></form><?php endforeach; ?><?php foreach ($results as $result): ?><div class="notice <?= h($result['status']) ?>"><?= h($result['source'] . '：' . $result['message']) ?></div><?php endforeach; ?><h2>最近运行</h2><table><tr><th>时间</th><th>来源</th><th>状态</th><th>新增</th><th>消息</th></tr><?php foreach ($runs as $run): ?><tr><td><?= h($run['started_at']) ?></td><td><?= h($run['source_name']) ?></td><td><?= h($run['status']) ?></td><td><?= (int) $run['added_count'] ?></td><td><?= h($run['message']) ?></td></tr><?php endforeach; ?></table></main></body></html>
