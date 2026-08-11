<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/Repository.php';
require_once __DIR__ . '/../app/Translator.php';
require_once __DIR__ . '/../app/Collector.php';

function assert_translation_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class RecordingTranslator implements TranslatorInterface
{
    public $calls = [];

    public function translate(string $text): string
    {
        $this->calls[] = $text;
        $translations = [
            'English title' => '中文标题',
            'English description' => '中文描述',
            'English category' => '中文分类',
            'English alt' => '中文替代文本',
        ];
        return $translations[$text] ?? $text;
    }
}

$config = require __DIR__ . '/../config/local.example.php';
$config['database'] = ['driver' => 'sqlite'];
$config['download_images'] = false;
$GLOBALS['config'] = $config;
$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new Repository($database, $config);
$repository->ensureSchema();
$translator = new RecordingTranslator();
$collector = new Collector($repository, $config, $translator);

$method = new ReflectionMethod(Collector::class, 'normalizeItem');
$method->setAccessible(true);
$normalized = $method->invoke($collector, [
    'title' => 'English title',
    'description' => 'English description',
    'category' => 'English category',
    'source_url' => 'https://example.com/gallery/1',
    'images' => [['url' => 'https://example.com/image.jpg', 'alt' => 'English alt']],
], ['url' => 'https://example.com/feed']);

assert_translation_test($normalized['title'] === '中文标题', '标题未在入库前翻译。');
assert_translation_test($normalized['description'] === '中文描述', '描述未在入库前翻译。');
assert_translation_test($normalized['category'] === '中文分类', '分类未在入库前翻译。');
assert_translation_test($normalized['images'][0]['alt'] === '中文替代文本', '图片 alt 未在入库前翻译。');
assert_translation_test(in_array('English title', $translator->calls, true), '翻译器没有收到标题。');
assert_translation_test(in_array('English description', $translator->calls, true), '翻译器没有收到描述。');
assert_translation_test(in_array('English category', $translator->calls, true), '翻译器没有收到分类。');
assert_translation_test(in_array('English alt', $translator->calls, true), '翻译器没有收到图片 alt。');

$translatorService = new Translator($config);
assert_translation_test($translatorService->translate('中文内容') === '中文内容', '中文旁路处理失败。');

echo "translation tests passed\n";
