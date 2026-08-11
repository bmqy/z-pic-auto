<?php
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    set_exception_handler(function (Throwable $error): void {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo '翻译任务失败：' . $error->getMessage();
    });
}
if (!$isCli) {
    $authConfig = require __DIR__ . '/../config/config.php';
    $requestToken = (string) ($_GET['token'] ?? '');
    if ($requestToken === '' || !hash_equals((string) ($authConfig['admin_token'] ?? ''), $requestToken)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Translator.php';

$config = require __DIR__ . '/../config/config.php';
$GLOBALS['config'] = $config;
$options = $isCli ? getopt('', ['sqlite::', 'mysql-host::', 'limit::', 'backup::', 'dry-run']) : $_GET;
$mode = $isCli ? 'translate' : strtolower(trim((string) ($_GET['mode'] ?? 'translate')));
$limit = isset($options['limit']) && $options['limit'] !== false ? max(0, (int) $options['limit']) : 0;
$applyRequested = isset($options['apply']) && in_array(strtolower((string) $options['apply']), ['1', 'true', 'yes'], true);
$dryRun = $isCli ? array_key_exists('dry-run', $options) : !$applyRequested;
$backupPath = isset($options['backup']) && $options['backup'] !== false ? (string) $options['backup'] : '';
if (!$isCli && !$dryRun && $backupPath === '') {
    $backupPath = __DIR__ . '/../storage/mysql-before-translation-' . date('YmdHis') . '.json';
}

if (isset($options['sqlite'])) {
    $databasePath = (string) $options['sqlite'];
    if (!is_file($databasePath)) {
        throw new RuntimeException('SQLite 数据库不存在：' . $databasePath);
    }
    $database = new PDO('sqlite:' . $databasePath);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} else {
    if (isset($options['mysql-host']) && $options['mysql-host'] !== false) {
        $config['database']['host'] = (string) $options['mysql-host'];
    }
    $database = Database::connect($config);
}

if (!$isCli && $mode === 'export') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'categories' => fetch_existing_rows($database, 'categories', 'id, name'),
        'galleries' => fetch_existing_rows($database, 'galleries', 'id, title, description, source_name', $limit),
        'images' => fetch_existing_rows($database, 'images', 'id, alt_text', $limit),
        'collection_runs' => fetch_existing_rows($database, 'collection_runs', 'id, source_name', $limit),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$isCli && $mode === 'apply') {
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('请求体必须是 JSON 对象。');
    }
    $counts = apply_existing_payload($database, $payload);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'success', 'updated' => $counts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$translator = new Translator($config);

function translate_existing_text(Translator $translator, string $value): string
{
    return $translator->translate($value);
}

function fetch_existing_rows(PDO $database, string $table, string $columns, int $limit = 0): array
{
    $sql = 'SELECT ' . $columns . ' FROM ' . $table . ' ORDER BY id ASC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    return $database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function apply_existing_payload(PDO $database, array $payload): array
{
    $counts = ['categories' => 0, 'galleries' => 0, 'images' => 0, 'collection_runs' => 0];
    $database->beginTransaction();
    try {
        $statement = $database->prepare('UPDATE categories SET name = ? WHERE id = ?');
        foreach ((array) ($payload['categories'] ?? []) as $row) {
            $statement->execute([(string) $row['name'], (int) $row['id']]);
            $counts['categories']++;
        }
        $statement = $database->prepare('UPDATE galleries SET title = ?, description = ?, source_name = ?, updated_at = ? WHERE id = ?');
        foreach ((array) ($payload['galleries'] ?? []) as $row) {
            $statement->execute([(string) $row['title'], (string) $row['description'], (string) $row['source_name'], now_string(), (int) $row['id']]);
            $counts['galleries']++;
        }
        $statement = $database->prepare('UPDATE images SET alt_text = ? WHERE id = ?');
        foreach ((array) ($payload['images'] ?? []) as $row) {
            $statement->execute([(string) $row['alt_text'], (int) $row['id']]);
            $counts['images']++;
        }
        $statement = $database->prepare('UPDATE collection_runs SET source_name = ? WHERE id = ?');
        foreach ((array) ($payload['collection_runs'] ?? []) as $row) {
            $statement->execute([(string) $row['source_name'], (int) $row['id']]);
            $counts['collection_runs']++;
        }
        $database->commit();
        return $counts;
    } catch (Throwable $error) {
        $database->rollBack();
        throw $error;
    }
}

$categoryUpdates = [];
$backupData = [
    'created_at' => date('c'),
    'categories' => [],
    'galleries' => [],
    'images' => [],
    'collection_runs' => [],
];
foreach (fetch_existing_rows($database, 'categories', 'id, name') as $row) {
    $translated = translate_existing_text($translator, (string) $row['name']);
    if ($translated !== (string) $row['name']) {
        $categoryUpdates[(int) $row['id']] = $translated;
        $backupData['categories'][] = $row;
    }
}

$galleryUpdates = [];
foreach (fetch_existing_rows($database, 'galleries', 'id, title, description, source_name', $limit) as $row) {
    $translated = [
        'title' => translate_existing_text($translator, (string) $row['title']),
        'description' => translate_existing_text($translator, (string) $row['description']),
        'source_name' => translate_existing_text($translator, (string) $row['source_name']),
    ];
    $original = [
        'title' => (string) $row['title'],
        'description' => (string) $row['description'],
        'source_name' => (string) $row['source_name'],
    ];
    if ($translated !== $original) {
        $galleryUpdates[(int) $row['id']] = $translated;
        $backupData['galleries'][] = $row;
    }
}

$imageUpdates = [];
foreach (fetch_existing_rows($database, 'images', 'id, alt_text', $limit) as $row) {
    $translated = translate_existing_text($translator, (string) $row['alt_text']);
    if ($translated !== (string) $row['alt_text']) {
        $imageUpdates[(int) $row['id']] = $translated;
        $backupData['images'][] = $row;
    }
}

$runUpdates = [];
foreach (fetch_existing_rows($database, 'collection_runs', 'id, source_name', $limit) as $row) {
    $translated = translate_existing_text($translator, (string) $row['source_name']);
    if ($translated !== (string) $row['source_name']) {
        $runUpdates[(int) $row['id']] = $translated;
        $backupData['collection_runs'][] = $row;
    }
}

echo '待更新：categories=' . count($categoryUpdates)
    . ' galleries=' . count($galleryUpdates)
    . ' images=' . count($imageUpdates)
    . ' collection_runs=' . count($runUpdates) . PHP_EOL;
foreach ($galleryUpdates as $id => $row) {
    echo 'gallery #' . $id . ': ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

if ($dryRun) {
    echo "仅预览，未写入数据库。\n";
    exit(0);
}

if ($backupPath !== '') {
    $backupDirectory = dirname($backupPath);
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0775, true)) {
        throw new RuntimeException('无法创建备份目录：' . $backupDirectory);
    }
    if (is_file($backupPath)) {
        throw new RuntimeException('备份文件已存在，拒绝覆盖：' . $backupPath);
    }
    $backupJson = json_encode($backupData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($backupJson === false || @file_put_contents($backupPath, $backupJson, LOCK_EX) === false) {
        throw new RuntimeException('无法写入备份文件：' . $backupPath);
    }
    echo '已写入备份：' . $backupPath . PHP_EOL;
}

$database->beginTransaction();
try {
    $categoryStatement = $database->prepare('UPDATE categories SET name = ? WHERE id = ?');
    foreach ($categoryUpdates as $id => $name) {
        $categoryStatement->execute([$name, $id]);
    }

    $galleryStatement = $database->prepare('UPDATE galleries SET title = ?, description = ?, source_name = ?, updated_at = ? WHERE id = ?');
    foreach ($galleryUpdates as $id => $row) {
        $galleryStatement->execute([$row['title'], $row['description'], $row['source_name'], now_string(), $id]);
    }

    $imageStatement = $database->prepare('UPDATE images SET alt_text = ? WHERE id = ?');
    foreach ($imageUpdates as $id => $altText) {
        $imageStatement->execute([$altText, $id]);
    }

    $runStatement = $database->prepare('UPDATE collection_runs SET source_name = ? WHERE id = ?');
    foreach ($runUpdates as $id => $sourceName) {
        $runStatement->execute([$sourceName, $id]);
    }
    $database->commit();
} catch (Throwable $error) {
    $database->rollBack();
    throw $error;
}

echo "数据库翻译更新完成。\n";
