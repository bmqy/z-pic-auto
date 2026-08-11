<?php
declare(strict_types=1);

interface TranslatorInterface
{
    public function translate(string $text): string;
}

final class Translator implements TranslatorInterface
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function translate(string $text): string
    {
        $text = trim($text);
        if ($text === '' || $this->isChineseOnly($text)) {
            return $text;
        }
        $knownTerm = [
            'APOD' => '每日天文图',
            'NASA' => '美国国家航空航天局',
            'JPL' => '喷气推进实验室',
            'ESA' => '欧洲航天局',
            'ISS' => '国际空间站',
        ];
        if (isset($knownTerm[$text])) {
            return $knownTerm[$text];
        }

        $translationConfig = (array) ($this->config['translation'] ?? []);
        $endpoint = trim((string) ($translationConfig['endpoint'] ?? 'https://translate.googleapis.com/translate_a/single'));
        $target = trim((string) ($translationConfig['target'] ?? 'zh-CN'));
        $timeout = max(5, (int) ($translationConfig['timeout'] ?? $this->config['request_timeout'] ?? 20));
        if ($endpoint === '' || $target === '') {
            throw new RuntimeException('翻译服务配置不完整。');
        }

        $query = http_build_query([
            'client' => 'gtx',
            'sl' => 'auto',
            'tl' => $target,
            'dt' => 't',
            'ie' => 'UTF-8',
            'oe' => 'UTF-8',
            'q' => $text,
        ]);
        [$body, $status, , $error] = request_url($endpoint . '?' . $query, $timeout);
        if ($body === '' || ($status >= 400 && $status !== 0)) {
            throw new RuntimeException('翻译服务请求失败：HTTP ' . $status . ' ' . $error);
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            throw new RuntimeException('翻译服务返回了无法识别的结果。');
        }
        $segments = [];
        foreach ($data[0] as $segment) {
            if (is_array($segment) && isset($segment[0])) {
                $segments[] = (string) $segment[0];
            }
        }
        $translated = trim(implode('', $segments));
        if ($translated === '') {
            throw new RuntimeException('翻译服务返回空内容。');
        }
        if (!$this->containsChinese($translated) && preg_match('/\p{L}/u', $text)) {
            throw new RuntimeException('翻译结果不包含中文，已阻止原文入库。');
        }
        return $translated;
    }

    private function isChineseOnly(string $text): bool
    {
        if (!$this->containsChinese($text)) {
            return false;
        }
        return !preg_match('/[A-Za-z]/', $text);
    }

    private function containsChinese(string $text): bool
    {
        return preg_match('/\p{Han}/u', $text) === 1;
    }
}
