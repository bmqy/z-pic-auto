<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';

function assert_deletion_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = require __DIR__ . '/../config/local.example.php';
$config['database'] = ['driver' => 'sqlite'];
$config['image_dir'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'z-pic-auto-delete-' . uniqid('', true);
$config['image_url_prefix'] = 'storage/images';
mkdir($config['image_dir'], 0775, true);

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new Repository($database, $config);
$repository->ensureSchema();

$writeImage = function (string $filename) use ($config): void {
    file_put_contents($config['image_dir'] . DIRECTORY_SEPARATOR . $filename, 'test image');
};
$writeImage('shared.jpg');
$writeImage('detail.jpg');

$firstGallery = $repository->createGallery([
    'title' => '第一个图集',
    'description' => '',
    'category' => '风景',
    'source_url' => 'https://example.com/gallery/1',
    'fingerprint' => sha1('gallery-1'),
], [
    ['source_url' => 'https://example.com/shared.jpg', 'local_path' => 'storage/images/shared.jpg', 'alt_text' => '', 'width' => 10, 'height' => 10],
    ['source_url' => 'https://example.com/detail.jpg', 'local_path' => 'storage/images/detail.jpg', 'alt_text' => '', 'width' => 10, 'height' => 10],
], '测试来源');
$secondGallery = $repository->createGallery([
    'title' => '第二个图集',
    'description' => '',
    'category' => '风景',
    'source_url' => 'https://example.com/gallery/2',
    'fingerprint' => sha1('gallery-2'),
], [
    ['source_url' => 'https://example.com/shared.jpg', 'local_path' => 'storage/images/shared.jpg', 'alt_text' => '', 'width' => 10, 'height' => 10],
], '测试来源');

$adminGalleries = $repository->adminGalleries();
assert_deletion_test(count($adminGalleries) === 2, '后台图集列表数量不正确。');
assert_deletion_test(count($adminGalleries[0]['images']) === 1, '后台图片列表没有正确加载。');

$imageId = (int) $database->query('SELECT id FROM images WHERE gallery_id = ' . $firstGallery . ' ORDER BY position ASC LIMIT 1')->fetchColumn();
assert_deletion_test($repository->deleteImage($imageId), '删除图片失败。');
$first = $database->query('SELECT cover_path FROM galleries WHERE id = ' . $firstGallery)->fetch(PDO::FETCH_ASSOC);
assert_deletion_test($first['cover_path'] === 'storage/images/detail.jpg', '删除首图后封面没有更新。');
assert_deletion_test(is_file($config['image_dir'] . DIRECTORY_SEPARATOR . 'shared.jpg'), '共享图片被错误删除。');

assert_deletion_test($repository->deleteGallery($secondGallery), '删除图集失败。');
assert_deletion_test(!is_file($config['image_dir'] . DIRECTORY_SEPARATOR . 'shared.jpg'), '图集删除后未清理无引用图片。');
assert_deletion_test($repository->deleteGallery($firstGallery), '删除剩余图集失败。');
assert_deletion_test(!is_file($config['image_dir'] . DIRECTORY_SEPARATOR . 'detail.jpg'), '删除图集后未清理图片文件。');
assert_deletion_test((int) $database->query('SELECT COUNT(*) FROM galleries')->fetchColumn() === 0, '图集记录未删除。');
assert_deletion_test((int) $database->query('SELECT COUNT(*) FROM images')->fetchColumn() === 0, '图片记录未删除。');

echo "deletion tests passed\n";
