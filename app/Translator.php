<?php
declare(strict_types=1);

final class TranslationException extends RuntimeException
{
}

interface TranslatorInterface
{
    public function translate(string $text): string;
}

final class Translator implements TranslatorInterface
{
    private $config;
    private $unavailable = false;

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
        if ($this->unavailable) {
            throw new TranslationException('翻译服务暂时不可用。');
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
        $fallbackEndpoint = trim((string) ($translationConfig['fallback_endpoint'] ?? 'https://api.mymemory.translated.net/get'));
        $target = trim((string) ($translationConfig['target'] ?? 'zh-CN'));
        $timeout = max(5, (int) ($translationConfig['timeout'] ?? $this->config['request_timeout'] ?? 20));
        if ($endpoint === '' || $target === '') {
            throw new TranslationException('翻译服务配置不完整。');
        }

        $errors = [];
        try {
            return $this->translateWithGoogle($endpoint, $target, $text, $timeout);
        } catch (TranslationException $error) {
            $errors[] = $error->getMessage();
        }
        if ($fallbackEndpoint !== '' && $fallbackEndpoint !== $endpoint) {
            try {
                return $this->translateWithMyMemory($fallbackEndpoint, $target, $text, $timeout);
            } catch (TranslationException $error) {
                $errors[] = $error->getMessage();
            }
        }
        $this->unavailable = true;
        throw new TranslationException('翻译服务不可用：' . implode('；', $errors));
    }

    private function translateWithGoogle(string $endpoint, string $target, string $text, int $timeout): string
    {
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
            throw new TranslationException('Google 翻译请求失败：HTTP ' . $status . ' ' . $error);
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            throw new TranslationException('Google 翻译返回了无法识别的结果。');
        }
        $segments = [];
        foreach ($data[0] as $segment) {
            if (is_array($segment) && isset($segment[0])) {
                $segments[] = (string) $segment[0];
            }
        }
        $translated = trim(implode('', $segments));
        if ($translated === '') {
            throw new TranslationException('Google 翻译返回空内容。');
        }
        if (!$this->containsChinese($translated) && preg_match('/\p{L}/u', $text)) {
            throw new TranslationException('Google 翻译结果不包含中文。');
        }
        return $translated;
    }

    private function translateWithMyMemory(string $endpoint, string $target, string $text, int $timeout): string
    {
        $query = http_build_query(['q' => $text, 'langpair' => 'en|' . $target]);
        [$body, $status, , $error] = request_url($endpoint . '?' . $query, $timeout);
        if ($body === '' || ($status >= 400 && $status !== 0)) {
            throw new TranslationException('备用翻译请求失败：HTTP ' . $status . ' ' . $error);
        }
        $data = json_decode($body, true);
        if (!is_array($data) || (isset($data['responseStatus']) && (int) $data['responseStatus'] !== 200)) {
            throw new TranslationException('备用翻译返回了无法识别的结果。');
        }
        $translated = trim(html_entity_decode((string) ($data['responseData']['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($translated === '') {
            throw new TranslationException('备用翻译返回空内容。');
        }
        if (!$this->containsChinese($translated) && preg_match('/\p{L}/u', $text)) {
            throw new TranslationException('备用翻译结果不包含中文。');
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
