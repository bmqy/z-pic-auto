<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/loader.php';

function assert_config_sources_test(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$previousPexelsKey = getenv('PEXELS_API_KEY');
putenv('PEXELS_API_KEY=config-source-test-key');

try {
    $config = zpic_load_config(false);
    $sources = (array) ($config['sources'] ?? []);
    $pexelsSources = array_values(array_filter($sources, function (array $source): bool {
        return strtolower((string) ($source['type'] ?? '')) === 'pexels';
    }));

    assert_config_sources_test($pexelsSources !== [], '配置加载器未保留 Pexels 来源。');
    assert_config_sources_test(($pexelsSources[0]['enabled'] ?? false) === true, 'PEXELS_API_KEY 未激活 Pexels 来源。');
    assert_config_sources_test(($sources[1]['url'] ?? '') === 'https://science.nasa.gov/feed/photojournal/latest-content/', '默认 NASA 来源与线上模板不一致。');
    $bangumiSources = array_values(array_filter($sources, function (array $source): bool {
        return strtolower((string) ($source['type'] ?? '')) === 'bangumi';
    }));
    assert_config_sources_test($bangumiSources !== [], '配置加载器未保留 Bangumi 来源。');
    assert_config_sources_test(($bangumiSources[0]['enabled'] ?? false) === true, 'Bangumi 来源默认未启用。');
    assert_config_sources_test(($bangumiSources[0]['category'] ?? '') === '二次元', 'Bangumi 来源默认分类不是二次元。');
    echo "config sources tests passed\n";
} finally {
    if ($previousPexelsKey === false) {
        putenv('PEXELS_API_KEY');
    } else {
        putenv('PEXELS_API_KEY=' . $previousPexelsKey);
    }
}
