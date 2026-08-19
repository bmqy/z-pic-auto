<?php
declare(strict_types=1);

function cfg(string $key, $default = null)
{
    global $config;
    return $config[$key] ?? $default;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url(string $path = ''): string
{
    $base = rtrim((string) cfg('site_url', ''), '/');
    return $base . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

/**
 * 获取当前请求去掉站点安装目录后的路径。
 */
function request_path(): string
{
    $requestUri = (string) (
        $_SERVER['REQUEST_URI']
        ?? $_SERVER['UNENCODED_URL']
        ?? $_SERVER['ORIG_PATH_INFO']
        ?? $_SERVER['PATH_INFO']
        ?? '/'
    );
    $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
    if ($scriptDirectory === '.') {
        $scriptDirectory = '';
    }
    if ($scriptDirectory !== '' && $scriptDirectory !== '/' && ($path === $scriptDirectory || strpos($path, $scriptDirectory . '/') === 0)) {
        $path = substr($path, strlen($scriptDirectory));
    }
    $scriptFile = '/' . basename($scriptName);
    if ($path === $scriptFile) {
        $path = '/';
    }
    return '/' . trim($path, '/');
}

/**
 * 获取当前请求的伪静态路径片段。
 */
function request_segments(): array
{
    $path = trim(request_path(), '/');
    if ($path === '') {
        return [];
    }
    return array_values(array_filter(array_map('rawurldecode', explode('/', $path)), 'strlen'));
}

function query_url(array $params): string
{
    $route = trim((string) ($params['route'] ?? 'home'), '/');
    $path = null;
    $pathParams = $params;
    unset($pathParams['route']);

    if ($route === 'home') {
        $path = '';
    } elseif ($route === 'gallery' && trim((string) ($params['slug'] ?? '')) !== '') {
        $path = 'gallery/' . rawurlencode(trim((string) $params['slug'], '/'));
        unset($pathParams['slug']);
    } elseif ($route === 'category' && trim((string) ($params['slug'] ?? '')) !== '') {
        $path = 'category/' . rawurlencode(trim((string) $params['slug'], '/'));
        unset($pathParams['slug']);
    } elseif (in_array($route, ['sitemap.xml', 'robots.txt', 'feed.xml'], true)) {
        $path = $route;
    }

    if ($path !== null) {
        if (isset($pathParams['page'])) {
            $pathParams['page'] = max(1, (int) $pathParams['page']);
            if ($pathParams['page'] === 1) {
                unset($pathParams['page']);
            }
        }
        $url = site_url($path);
        return $pathParams === [] ? $url : $url . '?' . http_build_query($pathParams, '', '&', PHP_QUERY_RFC3986);
    }

    return site_url('index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

function image_url(string $path): string
{
    if ($path === '') {
        return site_url('assets/placeholder.svg');
    }
    return site_url('index.php?' . http_build_query([
        'route' => 'image',
        'path' => ltrim($path, '/'),
    ]));
}

function display_image_url(string $localPath, string $sourceUrl = ''): string
{
    if (preg_match('#^https?://#i', $localPath)) {
        $storedPrefix = '/' . trim((string) cfg('image_url_prefix', 'storage/images'), '/') . '/';
        $sourcePath = (string) parse_url($localPath, PHP_URL_PATH);
        $prefixPosition = strpos($sourcePath, $storedPrefix);
        if ($prefixPosition !== false) {
            return image_url(substr($sourcePath, $prefixPosition + 1));
        }
        return $localPath;
    }
    return $localPath !== '' ? image_url($localPath) : ($sourceUrl !== '' ? $sourceUrl : image_url(''));
}

function text_slice(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}

function text_contains(string $haystack, string $needle): bool
{
    return function_exists('mb_stripos')
        ? mb_stripos($haystack, $needle) !== false
        : stripos($haystack, $needle) !== false;
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

function slugify(string $value): string
{
    $value = trim($value);
    $ascii = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
    $ascii = trim($ascii, '-');
    return $ascii !== '' ? $ascii : 'item-' . substr(sha1($value), 0, 12);
}

function unique_slug(string $title, callable $exists): string
{
    $base = slugify($title);
    $slug = $base;
    $number = 2;
    while ($exists($slug)) {
        $slug = $base . '-' . $number++;
    }
    return $slug;
}

function normalize_url(string $url, string $base = ''): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (substr($url, 0, 2) === '//') {
        $url = 'http:' . $url;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if ($base !== '') {
        $parts = parse_url($base);
        if ($parts && isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }
            if (substr($url, 0, 1) === '/') {
                return $origin . $url;
            }
            $path = isset($parts['path']) ? dirname($parts['path']) : '';
            return $origin . '/' . ltrim($path . '/' . $url, '/');
        }
    }
    return '';
}

function request_url(string $url, int $timeout, array $requestHeaders = []): array
{
    $headers = [
        'User-Agent: ' . cfg('user_agent', 'Z-Pic-Auto'),
        'Accept: */*',
    ];
    foreach ($requestHeaders as $requestHeader) {
        if (is_string($requestHeader) && trim($requestHeader) !== '') {
            $headers[] = trim($requestHeader);
        }
    }
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => (string) cfg('user_agent', 'Z-Pic-Auto'),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if (defined('CURL_IPRESOLVE_V4')) {
            // 部分生产主机的 IPv6 出口不可达，优先使用 IPv4 避免图片 CDN 连接超时。
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        if (!cfg('verify_ssl', true)) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
        } else {
            $caInfo = trim((string) cfg('ca_info', ''));
            if ($caInfo !== '' && is_file($caInfo)) {
                $curlOptions[CURLOPT_CAINFO] = $caInfo;
            }
        }
        curl_setopt_array($handle, $curlOptions);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($body === false) {
            return ['', $status, $contentType, $error];
        }
        return [(string) $body, $status, $contentType, ''];
    }
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => $timeout,
        'header' => implode("\r\n", $headers) . "\r\n",
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
        $status = (int) $match[1];
    }
    return [$body === false ? '' : $body, $status, '', $body === false ? 'request failed' : ''];
}

function extract_text(?DOMNode $node): string
{
    return $node ? trim((string) $node->textContent) : '';
}

function first_xpath(DOMXPath $xpath, DOMNode $context, string $expression): ?DOMNode
{
    $nodes = @$xpath->query($expression, $context);
    return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
}

function image_source_from_node(DOMElement $node, string $baseUrl): string
{
    foreach (['src', 'data-src', 'data-original', 'data-lazy-src'] as $attribute) {
        $value = trim($node->getAttribute($attribute));
        if ($value !== '') {
            return normalize_url($value, $baseUrl);
        }
    }
    return '';
}
