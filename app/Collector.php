<?php
declare(strict_types=1);

final class Collector
{
    private $repository;
    private $config;
    private $translator;
    private $exportWarnings = [];
    private $exportSourceResults = [];
    private $lastRequestUrl = '';
    private $lastImageDownloadFailure = '';

    public function __construct(?Repository $repository, array $config, ?TranslatorInterface $translator = null)
    {
        $this->repository = $repository;
        $this->config = $config;
        $this->translator = $translator ?: new Translator($config);
    }

    /**
     * 在无数据库环境中抓取并翻译内容，供 Actions 导出后提交给线上站点入库。
     */
    public function exportTranslatedItems(): array
    {
        $exported = [];
        $this->exportWarnings = [];
        $this->exportSourceResults = [];
        foreach ((array) $this->config['sources'] as $sourceIndex => $source) {
            $sourceLabel = trim((string) ($source['name'] ?? $source['url'] ?? ''));
            $sourceResult = [
                'index' => (int) $sourceIndex,
                'name' => $sourceLabel,
                'type' => strtolower((string) ($source['type'] ?? 'json')),
                'url' => trim((string) ($source['url'] ?? '')),
            ];
            if (!($source['enabled'] ?? false) || empty($source['url'])) {
                $this->exportSourceResults[] = array_merge($sourceResult, [
                    'status' => 'disabled',
                    'fetched_items' => 0,
                    'exported_items' => 0,
                    'skipped_items' => 0,
                    'message' => '来源未启用或缺少 URL。',
                ]);
                continue;
            }
            $fetchedCount = 0;
            $exportedCount = 0;
            $skippedCount = 0;
            $sourceName = $sourceLabel;
            try {
                $sourceName = $this->translator->translate(trim((string) ($source['name'] ?? $source['url'])));
                $items = $this->fetchItems($source);
                $fetchedCount = count($items);
                foreach ($items as $item) {
                    try {
                        $normalized = $this->normalizeItem($item, $source);
                    } catch (Throwable $error) {
                        $skippedCount++;
                        $this->exportWarnings[] = '来源 [' . trim((string) ($source['name'] ?? $source['url'])) . '] 单项跳过：' . $error->getMessage();
                        continue;
                    }
                    if ($normalized === null) {
                        $skippedCount++;
                        continue;
                    }
                    $normalized = $this->embedSourceImages($normalized, $source);
                    $exported[] = [
                        'source_name' => $sourceName,
                        'item' => $normalized,
                    ];
                    $exportedCount++;
                }
                $status = $fetchedCount === 0 ? 'empty' : ($exportedCount === 0 ? 'skipped' : 'success');
                $message = $status === 'empty'
                    ? '请求成功，但没有返回项目。'
                    : ($status === 'skipped' ? '抓取成功，但所有项目均被跳过。' : '抓取并规范化成功。');
                $this->exportSourceResults[] = array_merge($sourceResult, [
                    'run_source_name' => $sourceName,
                    'status' => $status,
                    'fetched_items' => $fetchedCount,
                    'exported_items' => $exportedCount,
                    'skipped_items' => $skippedCount,
                    'message' => $message,
                ], $this->lastRequestUrl !== '' ? ['request_url' => $this->lastRequestUrl] : []);
            } catch (Throwable $error) {
                $this->exportWarnings[] = '来源 [' . trim((string) ($source['name'] ?? $source['url'])) . '] 跳过：' . $error->getMessage();
                $this->exportSourceResults[] = array_merge($sourceResult, [
                    'run_source_name' => $sourceName,
                    'status' => 'failed',
                    'fetched_items' => $fetchedCount,
                    'exported_items' => $exportedCount,
                    'skipped_items' => $skippedCount,
                    'error' => $error->getMessage(),
                ]);
            }
        }
        return $exported;
    }

    public function getExportWarnings(): array
    {
        return $this->exportWarnings;
    }

    public function getExportSourceResults(): array
    {
        return $this->exportSourceResults;
    }

    /**
     * 接收 Actions 已翻译内容，在站点服务器本机下载图片并写入数据库。
     */
    public function importTranslatedItems(array $entries, array $sourceResults = []): array
    {
        $added = 0;
        $skipped = 0;
        $failed = 0;
        $started = now_string();
        $sourceStats = [];
        foreach ($sourceResults as $sourceResult) {
            if (!is_array($sourceResult) || strtolower((string) ($sourceResult['status'] ?? '')) === 'disabled') {
                continue;
            }
            $sourceName = trim((string) ($sourceResult['run_source_name'] ?? $sourceResult['name'] ?? ''));
            if ($sourceName === '') {
                continue;
            }
            $sourceStats[$sourceName] = [
                'source_name' => $sourceName,
                'status' => strtolower((string) ($sourceResult['status'] ?? 'success')),
                'message' => trim((string) ($sourceResult['message'] ?? $sourceResult['error'] ?? '')),
                'added' => 0,
                'skipped' => 0,
                'failed' => 0,
                'skip_reasons' => [],
                'failure_reasons' => [],
            ];
        }
        foreach ($entries as $entry) {
            $entrySourceName = trim((string) ($entry['source_name'] ?? 'Actions 导入')) ?: 'Actions 导入';
            if (!isset($sourceStats[$entrySourceName])) {
                $sourceStats[$entrySourceName] = [
                    'source_name' => $entrySourceName,
                    'status' => 'success',
                    'message' => '',
                    'added' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'skip_reasons' => [],
                    'failure_reasons' => [],
                ];
            }
            try {
                $item = (array) ($entry['item'] ?? []);
                $images = array_values(array_filter((array) ($item['images'] ?? []), function ($image) {
                    return is_array($image) && !empty($image['url']);
                }));
                $item['title'] = trim((string) ($item['title'] ?? ''));
                $item['description'] = trim((string) ($item['description'] ?? ''));
                $item['category'] = trim((string) ($item['category'] ?? '未分类')) ?: '未分类';
                $item['source_url'] = trim((string) ($item['source_url'] ?? ''));
                $item['identity_source_url'] = trim((string) ($item['identity_source_url'] ?? ''));
                $item['fingerprint'] = trim((string) ($item['fingerprint'] ?? ''));
                if ($item['title'] === '' || $item['fingerprint'] === '' || $images === []) {
                    $failed++;
                    $sourceStats[$entrySourceName]['failed']++;
                    $sourceStats[$entrySourceName]['failure_reasons'][] = '条目缺少标题、指纹或图片。';
                    continue;
                }
                if ($this->repository->galleryExistsByIdentity($item['fingerprint'], $item['identity_source_url'])) {
                    $skipped++;
                    $sourceStats[$entrySourceName]['skipped']++;
                    $sourceStats[$entrySourceName]['skip_reasons'][] = '数据库已存在相同指纹或来源 URL。';
                    continue;
                }
                $preparedImages = $this->prepareImages($images);
                if ($preparedImages === []) {
                    $skipped++;
                    $sourceStats[$entrySourceName]['skipped']++;
                    $validImageUrls = array_filter($images, function (array $image): bool {
                        return preg_match('#^https?://#i', (string) ($image['url'] ?? '')) === 1;
                    });
                    $imageUrls = array_values(array_map(function (array $image): string {
                        return trim((string) ($image['url'] ?? ''));
                    }, $images));
                    $imageReason = $validImageUrls === []
                        ? '图片 URL 为空或协议无效。'
                        : '图片下载失败、响应不是支持的图片格式或超过大小限制。';
                    if ($this->lastImageDownloadFailure !== '') {
                        $imageReason .= ' ' . $this->lastImageDownloadFailure;
                    }
                    $sourceStats[$entrySourceName]['skip_reasons'][] = $imageReason . ' URL：' . implode('，', array_slice($imageUrls, 0, 3));
                    continue;
                }
                $item['images'] = $images;
                $this->repository->createGallery($item, $preparedImages, trim((string) ($entry['source_name'] ?? '采集来源')) ?: '采集来源');
                $added++;
                $sourceStats[$entrySourceName]['added']++;
            } catch (Throwable $error) {
                $failed++;
                $sourceStats[$entrySourceName]['failed']++;
                $sourceStats[$entrySourceName]['failure_reasons'][] = $error->getMessage();
            }
        }
        $finished = now_string();
        $sourceRuns = [];
        foreach ($sourceStats as $sourceStat) {
            $runStatus = $sourceStat['status'] === 'failed' || $sourceStat['failed'] > 0 ? 'failed' : 'success';
            $message = $sourceStat['message'] !== '' ? $sourceStat['message'] . ' ' : '';
            $message .= 'Actions 导入：新增 ' . $sourceStat['added'] . '，跳过 ' . $sourceStat['skipped'] . '，失败 ' . $sourceStat['failed'] . '。';
            $skipReasons = array_values(array_unique(array_filter($sourceStat['skip_reasons'])));
            $failureReasons = array_values(array_unique(array_filter($sourceStat['failure_reasons'])));
            if ($skipReasons !== []) {
                $message .= ' 跳过原因：' . implode('；', $skipReasons);
            }
            if ($failureReasons !== []) {
                $message .= ' 失败原因：' . implode('；', $failureReasons);
            }
            $this->repository->recordRun($sourceStat['source_name'], $runStatus, $sourceStat['added'], $message, $started, $finished);
            $sourceRuns[] = [
                'source_name' => $sourceStat['source_name'],
                'status' => $runStatus,
                'added' => $sourceStat['added'],
                'skipped' => $sourceStat['skipped'],
                'failed' => $sourceStat['failed'],
                'message' => $message,
            ];
        }
        return [
            'status' => $failed > 0 ? 'failed' : 'success',
            'added' => $added,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => 'Actions 已翻译内容导入完成。',
            'source_runs' => $sourceRuns,
        ];
    }

    public function runAll(?int $onlyIndex = null): array
    {
        $results = [];
        $enabledCount = 0;
        foreach ((array) $this->config['sources'] as $index => $source) {
            if ($onlyIndex !== null && (int) $index !== $onlyIndex) {
                continue;
            }
            if (!($source['enabled'] ?? false) || empty($source['url'])) {
                continue;
            }
            $enabledCount++;
            $results[] = $this->runSource($source);
        }
        if ($enabledCount === 0) {
            $started = now_string();
            $message = '没有启用的采集来源，请在 config/local.php 配置有授权的 JSON、RSS 或 HTML 来源。';
            $this->repository->recordRun('系统', 'failed', 0, $message, $started, $started);
            $results[] = [
                'source' => '系统',
                'status' => 'failed',
                'added' => 0,
                'message' => $message,
            ];
        }
        return $results;
    }

    public function runSource(array $source): array
    {
        $sourceLabel = trim((string) ($source['name'] ?? $source['url']));
        $name = '采集来源（翻译失败）';
        $translationWarning = false;
        $started = now_string();
        try {
            try {
                $name = $this->translator->translate($sourceLabel);
            } catch (TranslationException $error) {
                $name = '采集来源';
                $translationWarning = true;
            }
            if ($name === '') {
                $name = '未命名来源';
            }
            $items = $this->fetchItems($source);
            $added = 0;
            $skippedForTranslation = 0;
            foreach ($items as $item) {
                try {
                    $normalized = $this->normalizeItem($item, $source);
                } catch (TranslationException $error) {
                    $skippedForTranslation++;
                    $translationWarning = true;
                    continue;
                }
                if ($normalized === null || $this->repository->galleryExistsByIdentity($normalized['fingerprint'], $normalized['identity_source_url'])) {
                    continue;
                }
                $images = $this->prepareImages($normalized['images']);
                if ($images === []) {
                    continue;
                }
                $this->repository->createGallery($normalized, $images, $name);
                $added++;
            }
            $finished = now_string();
            $message = '读取 ' . count($items) . ' 项，新增 ' . $added . ' 个图集。';
            if ($skippedForTranslation > 0) {
                $message .= ' 跳过 ' . $skippedForTranslation . ' 项未完成中文翻译。';
            }
            if ($translationWarning) {
                $message .= ' 翻译服务暂时不可用，未翻译原文不会入库。';
            }
            $this->repository->recordRun($name, 'success', $added, $message, $started, $finished);
            return ['source' => $name, 'status' => 'success', 'added' => $added, 'message' => $message];
        } catch (Throwable $error) {
            $finished = now_string();
            $this->repository->recordRun($name, 'failed', 0, $error->getMessage(), $started, $finished);
            return ['source' => $name, 'status' => 'failed', 'added' => 0, 'message' => $error->getMessage()];
        }
    }

    private function fetchItems(array $source): array
    {
        $timeout = isset($source['request_timeout']) ? (int) $source['request_timeout'] : (int) $this->config['request_timeout'];
        $type = strtolower((string) ($source['type'] ?? 'json'));
        $requestUrl = $this->buildSourceRequestUrl($source, $type);
        $this->lastRequestUrl = '';
        $requestDelay = max(0, (int) ($source['request_delay'] ?? 0));
        if ($requestDelay > 0) {
            sleep($requestDelay);
        }
        $requestHeaders = [];
        if ($type === 'pexels') {
            $apiKey = trim((string) ($source['api_key'] ?? ($this->config['pexels_api_key'] ?? '')));
            if ($apiKey === '') {
                throw new RuntimeException('Pexels 来源缺少 API Key，请配置 PEXELS_API_KEY 或 config/local.php。');
            }
            $requestHeaders[] = 'Authorization: ' . $apiKey;
            $requestHeaders[] = 'Accept: application/json';
        } elseif ($type === 'bangumi') {
            // Bangumi 是 JSON API，显式声明响应格式，避免网关按通用请求处理。
            $requestHeaders[] = 'Accept: application/json';
        } elseif ($type === 'rss' || $type === 'xml') {
            $requestHeaders[] = 'Accept: application/rss+xml, application/xml;q=0.9, text/xml;q=0.8';
        }
        $requestUrls = [$requestUrl];
        foreach ((array) ($source['fallback_urls'] ?? []) as $fallbackUrl) {
            $fallbackUrl = trim((string) $fallbackUrl);
            if ($fallbackUrl !== '' && !in_array($fallbackUrl, $requestUrls, true)) {
                $requestUrls[] = $fallbackUrl;
            }
        }
        if ($type === 'bangumi') {
            $fallbackUrl = trim((string) ($source['fallback_url'] ?? 'https://api.bgm.tv/calendar'));
            if ($fallbackUrl !== '' && !in_array($fallbackUrl, $requestUrls, true)) {
                $requestUrls[] = $fallbackUrl;
            }
        }
        $body = '';
        $status = 0;
        $error = '';
        foreach ($requestUrls as $candidateUrl) {
            [$candidateBody, $candidateStatus, $candidateError] = $this->requestSourceWithRetry(
                $source,
                $candidateUrl,
                max(5, $timeout),
                $requestHeaders
            );
            $body = $candidateBody;
            $status = $candidateStatus;
            $error = $candidateError;
            if ($body !== '' && ($status === 0 || $status < 400)) {
                $requestUrl = $candidateUrl;
                $this->lastRequestUrl = $candidateUrl;
                break;
            }
        }
        if ($body === '' || ($status >= 400 && $status !== 0)) {
            $sourceName = trim((string) ($source['name'] ?? $source['url']));
            $responseSummary = $this->summarizeResponseBody($body);
            $details = trim($error . ($responseSummary !== '' ? '响应：' . $responseSummary : ''));
            throw new RuntimeException('来源请求失败 [' . $sourceName . '] ' . (string) $source['url'] . '，HTTP ' . $status . ' ' . $details);
        }
        if ($type === 'json') {
            $items = $this->parseJson($body, $requestUrl);
        } elseif ($type === 'pexels') {
            $items = $this->parsePexels($body, $source);
        } elseif ($type === 'bangumi') {
            $items = $this->parseBangumi($body, $source);
        } elseif ($type === 'rss' || $type === 'xml') {
            $items = $this->parseRss($body, $requestUrl);
        } elseif ($type === 'html') {
            $items = $this->parseHtml($body, $requestUrl, (array) ($source['selectors'] ?? []));
        } else {
            throw new InvalidArgumentException('不支持的来源类型：' . $source['type']);
        }
        if (isset($source['max_items']) && (int) $source['max_items'] > 0) {
            $items = array_slice($items, 0, (int) $source['max_items']);
        }
        if (isset($source['max_images']) && (int) $source['max_images'] > 0) {
            foreach ($items as &$item) {
                $item['images'] = array_slice((array) ($item['images'] ?? []), 0, (int) $source['max_images']);
            }
            unset($item);
        }
        return $items;
    }

    /**
     * 请求来源并按配置执行重试；配置的三个退避值分别对应三次重试。
     */
    private function requestSourceWithRetry(array $source, string $url, int $timeout, array $requestHeaders): array
    {
        $body = '';
        $status = 0;
        $error = '';
        for ($attempt = 0; $attempt < 4; $attempt++) {
            [$body, $status, , $error] = request_url($url, $timeout, $requestHeaders);
            if ($body !== '' && ($status === 0 || $status < 400)) {
                break;
            }
            $retryable = $status === 0 || $status === 429 || $status >= 500;
            if (!$retryable || $attempt >= 3) {
                break;
            }
            sleep($this->retryDelay($source, $attempt, $status));
        }
        return [$body, $status, $error];
    }

    /**
     * 计算来源重试等待时间。429 默认使用更长退避，避免持续撞击限流窗口。
     */
    private function retryDelay(array $source, int $attempt, int $status): int
    {
        $configured = array_values(array_filter((array) ($source['retry_delays'] ?? []), function ($delay): bool {
            return is_numeric($delay) && (int) $delay >= 0;
        }));
        if ($configured === []) {
            $configured = $status === 429 ? [30, 60, 120] : [5, 10, 20];
        }
        $index = min(max(0, $attempt), count($configured) - 1);
        return (int) $configured[$index];
    }

    /**
     * 根据来源配置构造请求地址，Pexels 的参数通过查询字符串发送。
     */
    private function buildSourceRequestUrl(array $source, string $type): string
    {
        $url = trim((string) ($source['url'] ?? ''));
        if ($type !== 'pexels' && $type !== 'bangumi') {
            return $url;
        }
        $params = (array) ($source['params'] ?? []);
        $keys = $type === 'pexels'
            ? ['query', 'orientation', 'size', 'color', 'locale', 'page', 'per_page']
            : ['type', 'cat', 'platform', 'sort', 'year', 'month', 'limit', 'offset'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== '' && $source[$key] !== null) {
                $params[$key] = $source[$key];
            }
        }
        if ($params === []) {
            return $url;
        }
        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }

    /**
     * 截取远程接口错误响应，避免 Actions 日志丢失真正的校验失败原因。
     */
    private function summarizeResponseBody(string $body): string
    {
        $summary = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));
        return text_slice($summary, 500);
    }

    /**
     * 将 Bangumi 条目列表转换为项目通用的图集条目。
     */
    private function parseBangumi(string $body, array $source): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Bangumi JSON 解析失败：' . json_last_error_msg());
        }
        $subjects = $data['data'] ?? null;
        if ($subjects === null && is_array($data)) {
            $subjects = [];
            foreach ($data as $weekday) {
                if (!is_array($weekday) || !is_array($weekday['items'] ?? null)) {
                    continue;
                }
                foreach ($weekday['items'] as $item) {
                    if (is_array($item)) {
                        $subjects[] = $item;
                    }
                }
            }
        }
        if (!is_array($subjects)) {
            throw new RuntimeException('Bangumi 响应缺少 data 数组。');
        }

        $category = trim((string) ($source['category'] ?? '二次元')) ?: '二次元';
        $result = [];
        foreach ($subjects as $subject) {
            if (!is_array($subject)) {
                continue;
            }
            $subjectId = (int) ($subject['id'] ?? 0);
            $title = trim((string) ($subject['name_cn'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($subject['name'] ?? ''));
            }
            $images = (array) ($subject['images'] ?? []);
            $imageUrl = '';
            foreach (['large', 'medium', 'common', 'grid', 'small'] as $imageSize) {
                $candidate = trim((string) ($images[$imageSize] ?? ''));
                if ($candidate !== '') {
                    $imageUrl = $this->normalizeBangumiImageUrl($candidate);
                    break;
                }
            }
            if ($title === '' || $imageUrl === '') {
                continue;
            }
            $sourceUrl = $subjectId > 0
                ? 'https://bgm.tv/subject/' . $subjectId
                : '';
            $result[] = [
                'title' => $title,
                'description' => trim((string) ($subject['summary'] ?? '')),
                'category' => $category,
                'source_url' => $sourceUrl,
                'images' => [[
                    'url' => $imageUrl,
                    'alt' => $title,
                ] + [
                    'fallback_urls' => array_values(array_unique(array_merge(
                        $this->bangumiImageFallbackUrls($imageUrl),
                        $subjectId > 0 ? [
                            'https://api.bgm.tv/v0/subjects/' . $subjectId . '/image?type=large',
                        ] : []
                    ))),
                ]],
            ];
        }
        return $result;
    }

    /**
     * Bangumi 封面接口仍可能返回 HTTP 地址，统一升级为 HTTPS 后再交给线上服务器下载。
     */
    private function normalizeBangumiImageUrl(string $url): string
    {
        $url = trim($url);
        if (stripos($url, 'http://lain.bgm.tv/') === 0) {
            return 'https://' . substr($url, strlen('http://'));
        }
        return $url;
    }

    /**
     * 生成 Bangumi 封面的备用图片域名；这些地址保持同一图片路径，不依赖 API 302 到 lain.bgm.tv。
     */
    private function bangumiImageFallbackUrls(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host !== 'lain.bgm.tv' || empty($parts['path'])) {
            return [];
        }
        $path = (string) $parts['path'];
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return [
            'https://navi.bgm.tv' . $path . $query,
            'https://fast.bgm.tv' . $path . $query,
            'https://chii.in' . $path . $query,
        ];
    }

    private function parseJson(string $body, string $baseUrl): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON 解析失败：' . json_last_error_msg());
        }
        $items = $data['items'] ?? $data['galleries'] ?? $data;
        if (!is_array($items)) {
            throw new RuntimeException('JSON 数据不是数组或 items/galleries 对象。');
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $images = $item['images'] ?? $item['photos'] ?? $item['items'] ?? [];
            if (is_string($images)) {
                $images = [$images];
            }
            $normalizedImages = array_map(function ($image) use ($baseUrl) {
                if (is_array($image)) {
                    return [
                        'url' => normalize_url((string) ($image['url'] ?? $image['src'] ?? ''), $baseUrl),
                        'alt' => (string) ($image['alt'] ?? $image['title'] ?? ''),
                    ];
                }
                return ['url' => normalize_url((string) $image, $baseUrl), 'alt' => ''];
            }, (array) $images);
            $result[] = [
                'title' => (string) ($item['title'] ?? $item['name'] ?? ''),
                'description' => (string) ($item['description'] ?? $item['summary'] ?? ''),
                'category' => (string) ($item['category'] ?? $item['tag'] ?? ''),
                'source_url' => normalize_url((string) ($item['source_url'] ?? $item['link'] ?? ''), $baseUrl),
                'images' => $normalizedImages,
            ];
        }
        return $result;
    }

    /**
     * 将 Pexels Photo 资源转换为项目通用的图集条目。
     */
    private function parsePexels(string $body, array $source): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Pexels JSON 解析失败：' . json_last_error_msg());
        }
        $photos = $data['photos'] ?? [];
        if (!is_array($photos)) {
            throw new RuntimeException('Pexels 响应缺少 photos 数组。');
        }
        $imageSize = trim((string) ($source['image_size'] ?? 'large'));
        $result = [];
        foreach ($photos as $photo) {
            if (!is_array($photo)) {
                continue;
            }
            $photoId = trim((string) ($photo['id'] ?? ''));
            $photoUrl = trim((string) ($photo['url'] ?? ''));
            $alt = trim((string) ($photo['alt'] ?? ''));
            $title = $alt !== '' ? $alt : ($photoId !== '' ? 'Pexels Photo ' . $photoId : 'Pexels Photo');
            $photographer = trim((string) ($photo['photographer'] ?? ''));
            $description = $photographer !== ''
                ? 'Photo by ' . $photographer . ' on Pexels.'
                : 'Photo provided by Pexels.';
            $src = (array) ($photo['src'] ?? []);
            $imageUrl = trim((string) ($src[$imageSize] ?? $src['large'] ?? $src['medium'] ?? $src['original'] ?? ''));
            if ($imageUrl === '') {
                continue;
            }
            $result[] = [
                'title' => $title,
                'description' => $description,
                'category' => (string) ($source['category'] ?? $source['query'] ?? ''),
                'source_url' => $photoUrl,
                'images' => [[
                    'url' => $imageUrl,
                    'alt' => $alt !== '' ? $alt : $title,
                ]],
            ];
        }
        return $result;
    }

    private function parseRss(string $body, string $baseUrl): array
    {
        libxml_use_internal_errors(true);
        // 部分公开 RSS 在属性 URL 中直接使用 &，先补齐 XML 实体以兼容 PHP 7.2 的解析器。
        $xmlBody = preg_replace('/&(?!#\d+;|#x[0-9a-f]+;|[a-z][a-z0-9]+;)/i', '&amp;', $body);
        $xml = simplexml_load_string($xmlBody === null ? $body : $xmlBody);
        if ($xml === false) {
            throw new RuntimeException('RSS/XML 解析失败。');
        }
        $result = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $description = trim((string) ($item->description ?? ''));
            $contentNamespace = $item->children('content', true);
            $encoded = isset($contentNamespace->encoded) ? trim((string) $contentNamespace->encoded) : '';
            $hiresImage = trim((string) ($item->hiresJpeg ?? ''));
            $images = [];
            if ($hiresImage !== '') {
                $images[] = ['url' => normalize_url($hiresImage, $baseUrl), 'alt' => (string) ($item->title ?? '')];
            }
            $mediaNamespace = $item->children('media', true);
            foreach ($mediaNamespace->content as $media) {
                $mediaUrl = trim((string) ($media['url'] ?? ''));
                if ($mediaUrl !== '') {
                    $images[] = ['url' => normalize_url($mediaUrl, $baseUrl), 'alt' => (string) ($item->title ?? '')];
                }
            }
            // 标准 RSS 图片通常放在 enclosure 节点中，例如 NASA Image of the Day。
            $enclosureUrl = trim((string) ($item->enclosure['url'] ?? ''));
            if ($enclosureUrl !== '') {
                $images[] = ['url' => normalize_url($enclosureUrl, $baseUrl), 'alt' => (string) ($item->title ?? '')];
            }
            $imageHtml = $encoded !== '' ? $encoded : $description;
            if ($imageHtml !== '') {
                $dom = new DOMDocument();
                @$dom->loadHTML('<?xml encoding="UTF-8">' . $imageHtml);
                foreach ($dom->getElementsByTagName('img') as $img) {
                    $images[] = ['url' => image_source_from_node($img, $baseUrl), 'alt' => $img->getAttribute('alt')];
                }
            }
            $result[] = [
                'title' => (string) ($item->title ?? ''),
                'description' => trim(strip_tags($description)),
                'category' => (string) ($item->category ?? ''),
                'source_url' => normalize_url((string) ($item->link ?? ''), $baseUrl),
                'images' => $images,
            ];
        }
        return $result;
    }

    private function parseHtml(string $body, string $baseUrl, array $selectors): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!@$dom->loadHTML('<?xml encoding="UTF-8">' . $body)) {
            throw new RuntimeException('HTML 解析失败。');
        }
        $xpath = new DOMXPath($dom);
        $galleryExpression = (string) ($selectors['gallery'] ?? '//article | //main//*[self::section or self::article]');
        $nodes = @$xpath->query($galleryExpression);
        if (!$nodes || $nodes->length === 0) {
            throw new RuntimeException('HTML 来源没有匹配到 gallery 选择器。');
        }
        $result = [];
        foreach ($nodes as $node) {
            $titleNode = first_xpath($xpath, $node, (string) ($selectors['title'] ?? './/h2 | .//h3 | .//a'));
            $descriptionNode = first_xpath($xpath, $node, (string) ($selectors['description'] ?? './/p'));
            $categoryNode = first_xpath($xpath, $node, (string) ($selectors['category'] ?? './/*[contains(@class,"category")]'));
            $linkNode = first_xpath($xpath, $node, './/a[@href]');
            $images = [];
            $imageNodes = @$xpath->query((string) ($selectors['image'] ?? './/img'), $node);
            if ($imageNodes) {
                foreach ($imageNodes as $imageNode) {
                    if ($imageNode instanceof DOMElement) {
                        $images[] = ['url' => image_source_from_node($imageNode, $baseUrl), 'alt' => $imageNode->getAttribute('alt')];
                    }
                }
            }
            $title = extract_text($titleNode);
            if ($titleNode instanceof DOMElement && $title === '' && $titleNode->hasAttribute('title')) {
                $title = trim($titleNode->getAttribute('title'));
            }
            $result[] = [
                'title' => $title,
                'description' => extract_text($descriptionNode),
                'category' => extract_text($categoryNode),
                'source_url' => $linkNode instanceof DOMElement ? normalize_url($linkNode->getAttribute('href'), $baseUrl) : $baseUrl,
                'images' => $images,
            ];
        }
        return $result;
    }

    private function normalizeItem(array $item, array $source): ?array
    {
        $originalTitle = trim((string) ($item['title'] ?? ''));
        $images = array_values(array_filter((array) ($item['images'] ?? []), function ($image) {
            return !empty($image['url']);
        }));
        if ($originalTitle === '' || $images === []) {
            return null;
        }
        $originalDescription = trim(strip_tags((string) ($item['description'] ?? '')));
        $originalCategory = trim((string) ($item['category'] ?? ''));
        $category = $originalCategory;
        if ($category === '') {
            $category = $this->classify($originalTitle . ' ' . $originalDescription);
        }
        $title = $this->translator->translate($originalTitle);
        $description = $this->translator->translate($originalDescription);
        $category = $this->translator->translate($category !== '' ? $category : '未分类');
        $images = array_map(function (array $image) {
            $image['alt'] = $this->translator->translate((string) ($image['alt'] ?? ''));
            return $image;
        }, $images);
        $urls = array_map(function (array $image) {
            return (string) $image['url'];
        }, $images);
        $identitySourceUrl = trim((string) ($item['source_url'] ?? ''));
        $sourceUrl = $identitySourceUrl;
        if ($sourceUrl === '') {
            $sourceUrl = trim((string) ($source['url'] ?? ''));
        }
        return [
            'title' => text_slice($title, 180),
            'description' => text_slice($description, 1000),
            'category' => $category !== '' ? $category : '未分类',
            'source_url' => $sourceUrl,
            'identity_source_url' => $identitySourceUrl,
            'images' => $images,
            'fingerprint' => sha1($identitySourceUrl . '|' . $originalTitle . '|' . implode('|', $urls)),
        ];
    }

    private function classify(string $text): string
    {
        foreach ((array) $this->config['category_rules'] as $category => $keywords) {
            foreach ((array) $keywords as $keyword) {
                if ($keyword !== '' && text_contains($text, (string) $keyword)) {
                    return (string) $category;
                }
            }
        }
        return '未分类';
    }

    /**
     * Actions 端先下载图片并随 JSON 传输，避免生产虚拟主机再次连接 Bangumi 图片域名。
     */
    private function embedSourceImages(array $item, array $source): array
    {
        if (strtolower((string) ($source['type'] ?? '')) !== 'bangumi') {
            return $item;
        }
        $images = [];
        foreach ((array) ($item['images'] ?? []) as $image) {
            if (!is_array($image) || trim((string) ($image['url'] ?? '')) === '') {
                continue;
            }
            $downloadUrls = [(string) $image['url']];
            foreach ((array) ($image['fallback_urls'] ?? []) as $fallbackUrl) {
                $fallbackUrl = trim((string) $fallbackUrl);
                if ($fallbackUrl !== '' && !in_array($fallbackUrl, $downloadUrls, true)) {
                    $downloadUrls[] = $fallbackUrl;
                }
            }
            foreach ($downloadUrls as $downloadUrl) {
                $download = $this->downloadImageData($downloadUrl);
                if ($download === null) {
                    continue;
                }
                if (strlen($download['body']) > (int) ($this->config['max_embedded_image_bytes'] ?? 4 * 1024 * 1024)) {
                    $this->lastImageDownloadFailure = 'Actions 图片数据超过传输大小限制。';
                    continue;
                }
                $image['embedded'] = [
                    'data' => base64_encode($download['body']),
                    'mime' => $download['mime'],
                    'width' => $download['width'],
                    'height' => $download['height'],
                ];
                break;
            }
            $images[] = $image;
        }
        $item['images'] = $images;
        return $item;
    }

    private function prepareImages(array $images): array
    {
        $this->lastImageDownloadFailure = '';
        $prepared = [];
        foreach ($images as $image) {
            $url = (string) ($image['url'] ?? '');
            if (!preg_match('#^https?://#i', $url)) {
                continue;
            }
            $localPath = '';
            $width = 0;
            $height = 0;
            if ($this->config['download_images']) {
                $download = null;
                if (is_array($image['embedded'] ?? null)) {
                    $download = $this->downloadEmbeddedImage($image['embedded'], $url);
                }
                $downloadUrls = [$url];
                $fallbackUrls = (array) ($image['fallback_urls'] ?? []);
                if (!empty($image['fallback_url'])) {
                    $fallbackUrls[] = $image['fallback_url'];
                }
                foreach ($fallbackUrls as $fallbackUrl) {
                    $fallbackUrl = trim((string) $fallbackUrl);
                    if ($fallbackUrl !== '' && preg_match('#^https?://#i', $fallbackUrl) && !in_array($fallbackUrl, $downloadUrls, true)) {
                        $downloadUrls[] = $fallbackUrl;
                    }
                }
                if ($download === null) {
                    foreach ($downloadUrls as $downloadUrl) {
                        $download = $this->downloadImage($downloadUrl);
                        if ($download !== null) {
                            break;
                        }
                    }
                }
                if ($download === null) {
                    continue;
                }
                $localPath = $download['path'];
                $width = $download['width'];
                $height = $download['height'];
            }
            $prepared[] = [
                'source_url' => $url,
                'local_path' => $localPath,
                'alt_text' => trim((string) ($image['alt'] ?? '')),
                'width' => $width,
                'height' => $height,
            ];
        }
        return $prepared;
    }

    private function downloadImage(string $url): ?array
    {
        $data = $this->downloadImageData($url);
        if ($data === null) {
            return null;
        }
        $extension = $this->imageExtension($data['mime']);
        $filename = sha1($url) . '.' . $extension;
        $absolute = rtrim((string) $this->config['image_dir'], '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($absolute) && @file_put_contents($absolute, $data['body'], LOCK_EX) === false) {
            $this->lastImageDownloadFailure = '图片文件写入失败。';
            return null;
        }
        $this->lastImageDownloadFailure = '';
        return [
            'path' => trim((string) $this->config['image_url_prefix'], '/') . '/' . $filename,
            'width' => $data['width'],
            'height' => $data['height'],
        ];
    }

    /**
     * Actions 和生产端共用的图片下载及格式校验逻辑。
     */
    private function downloadImageData(string $url): ?array
    {
        [$body, $status, $contentType, $error] = request_url(
            $url,
            (int) $this->config['request_timeout'],
            $this->imageRequestHeaders($url)
        );
        if ($body === '' || ($status >= 400 && $status !== 0) || strlen($body) > (int) $this->config['max_image_bytes']) {
            $this->lastImageDownloadFailure = '图片请求失败：HTTP ' . $status . ($error !== '' ? '，' . $error : '') . '。';
            return null;
        }
        $info = @getimagesizefromstring($body);
        if ($info === false || empty($info['mime']) || substr((string) $info['mime'], 0, 6) !== 'image/') {
            $this->lastImageDownloadFailure = '图片响应不是有效图片（Content-Type：' . ($contentType !== '' ? $contentType : '未知') . '）。';
            return null;
        }
        $mime = (string) $info['mime'];
        if ($this->imageExtension($mime) === '') {
            $this->lastImageDownloadFailure = '图片格式不受支持（' . $mime . '）。';
            return null;
        }
        return [
            'body' => $body,
            'mime' => $mime,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }

    private function downloadEmbeddedImage(array $embedded, string $url): ?array
    {
        $encoded = trim((string) ($embedded['data'] ?? ''));
        if ($encoded === '') {
            return null;
        }
        $body = base64_decode($encoded, true);
        if ($body === false || strlen($body) > (int) $this->config['max_image_bytes']) {
            $this->lastImageDownloadFailure = 'Actions 图片数据无效或超过大小限制。';
            return null;
        }
        $info = @getimagesizefromstring($body);
        if ($info === false || empty($info['mime']) || $this->imageExtension((string) $info['mime']) === '') {
            $this->lastImageDownloadFailure = 'Actions 图片数据不是支持的图片格式。';
            return null;
        }
        $extension = $this->imageExtension((string) $info['mime']);
        $filename = sha1($url) . '.' . $extension;
        $absolute = rtrim((string) $this->config['image_dir'], '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($absolute) && @file_put_contents($absolute, $body, LOCK_EX) === false) {
            $this->lastImageDownloadFailure = '图片文件写入失败。';
            return null;
        }
        return [
            'path' => trim((string) $this->config['image_url_prefix'], '/') . '/' . $filename,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }

    private function imageExtension(string $mime): string
    {
        if ($mime === 'image/jpeg') {
            return 'jpg';
        }
        if ($mime === 'image/png') {
            return 'png';
        }
        if ($mime === 'image/gif') {
            return 'gif';
        }
        if ($mime === 'image/webp') {
            return 'webp';
        }
        return '';
    }

    /**
     * 为 Bangumi 图片补充站点要求的请求头，减少 CDN 将服务器请求判定为无效热链。
     */
    private function imageRequestHeaders(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $headers = ['Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'];
        if (in_array($host, ['lain.bgm.tv', 'api.bgm.tv', 'navi.bgm.tv', 'fast.bgm.tv', 'chii.in'], true)) {
            $headers[] = 'Referer: https://bgm.tv/';
        }
        return $headers;
    }
}
