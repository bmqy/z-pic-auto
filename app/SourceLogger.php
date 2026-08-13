<?php
declare(strict_types=1);

final class SourceLogger
{
    /**
     * 将运行时实际加载的来源配置格式化为便于 Actions 日志检索的 JSON 行。
     */
    public static function formatSources(array $sources): array
    {
        $lines = [];
        foreach ($sources as $index => $source) {
            $payload = [
                'index' => $index,
                'enabled' => (bool) ($source['enabled'] ?? false),
                'name' => trim((string) ($source['name'] ?? '')),
                'type' => strtolower((string) ($source['type'] ?? 'json')),
                'url' => trim((string) ($source['url'] ?? '')),
            ];
            if ($payload['type'] === 'bangumi' && !empty($source['params']) && is_array($source['params'])) {
                $payload['params'] = $source['params'];
            }
            foreach (['request_delay', 'retry_delays', 'fallback_urls'] as $key) {
                if (array_key_exists($key, $source)) {
                    $payload[$key] = $source[$key];
                }
            }
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                $encoded = '{}';
            }
            $lines[] = '[actions-source-config] ' . $encoded;
        }
        return $lines;
    }

    /**
     * 输出来源配置到指定日志流，避免污染 Actions 导出的标准输出 JSON。
     */
    public static function writeSources(array $sources, $stream): int
    {
        foreach (self::formatSources($sources) as $line) {
            fwrite($stream, $line . PHP_EOL);
        }
        return count($sources);
    }

    public static function enabledCount(array $sources): int
    {
        $count = 0;
        foreach ($sources as $source) {
            if (($source['enabled'] ?? false) && !empty($source['url'])) {
                $count++;
            }
        }
        return $count;
    }

    public static function formatSummary(array $summary): string
    {
        $encoded = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return '[actions-source-summary] ' . ($encoded === false ? '{}' : $encoded);
    }

    public static function formatResults(array $results): array
    {
        $lines = [];
        foreach ($results as $result) {
            $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $lines[] = '[actions-source-result] ' . ($encoded === false ? '{}' : $encoded);
        }
        return $lines;
    }
}
