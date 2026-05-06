<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\core;

use core\exception\CommonException;

class QuoteSpiderClient
{
    public function fetchList(array $source): array
    {
        $data = $this->request($source, (string)($source['list_path'] ?? ''), $this->getQuery($source, 'list_query'));
        return $this->extractListData($data);
    }

    public function fetchDetail(array $source, string $id): array
    {
        $query = $this->getQuery($source, 'detail_query');
        $query['id'] = $id;
        return $this->request($source, (string)($source['detail_path'] ?? ''), $query);
    }

    public function sanitizeConfig(array $config): array
    {
        foreach (['headers', 'cookies', 'query', 'list_query', 'detail_query'] as $key) {
            if (empty($config[$key]) || !is_array($config[$key])) {
                continue;
            }
            foreach ($config[$key] as $name => $value) {
                if (preg_match('/cookie|token|secret|authorization|password|sign/i', (string)$name)) {
                    $config[$key][$name] = '******';
                }
            }
        }
        return $config;
    }

    private function request(array $source, string $path, array $query = []): array
    {
        $baseUrl = rtrim((string)($source['base_url'] ?? ''), '/');
        if ($baseUrl === '' || $path === '') {
            throw new CommonException('报价源地址未配置');
        }

        $config = $source['request_config'] ?? [];
        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }

        $query = array_merge($this->getQueryFromConfig($config, 'query'), $query);
        $url = $baseUrl . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $retryTimes = max(0, (int)($source['retry_times'] ?? 1));
        $timeout = max(3, (int)($source['timeout'] ?? 15));
        $lastError = '';

        for ($i = 0; $i <= $retryTimes; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => $this->buildHeaders($config),
                CURLOPT_USERAGENT => $this->getUserAgent($config),
            ]);

            $body = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body !== false && $httpCode >= 200 && $httpCode < 300) {
                $data = json_decode((string)$body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
                $lastError = '返回数据不是有效JSON：' . $this->previewBody((string)$body);
            } else {
                $lastError = $error ?: ('HTTP ' . $httpCode);
            }

            if ($i < $retryTimes) {
                usleep(300000);
            }
        }

        throw new CommonException('请求报价源失败：' . $lastError);
    }

    private function getQuery(array $source, string $key): array
    {
        $config = $source['request_config'] ?? [];
        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }
        return $this->getQueryFromConfig($config, $key);
    }

    private function getQueryFromConfig(array $config, string $key): array
    {
        return !empty($config[$key]) && is_array($config[$key]) ? $config[$key] : [];
    }

    private function extractListData(array $data): array
    {
        if (isset($data['image']) && is_array($data['image'])) {
            return $data['image'];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        if (isset($data['result']) && is_array($data['result']) && $this->looksLikeCategoryList($data['result'])) {
            return $data['result'];
        }
        if ($this->looksLikeCategoryList($data)) {
            return $data;
        }
        throw new CommonException('报价源列表结构未识别，请确认列表接口是否返回分类报价数据');
    }

    private function looksLikeCategoryList(array $data): bool
    {
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['types']) || isset($item['goods']) || (isset($item['id']) && isset($item['mobile_name']))) {
                return true;
            }
        }
        return false;
    }

    private function buildHeaders(array $config): array
    {
        $headers = [];
        foreach (($config['headers'] ?? []) as $name => $value) {
            $headerName = strtolower((string)$name);
            if ($value !== '' && !$this->isIgnoredHeader($headerName)) {
                $headers[] = $name . ': ' . $value;
            }
        }
        if (!empty($config['cookies']) && is_array($config['cookies'])) {
            $cookie = [];
            foreach ($config['cookies'] as $name => $value) {
                $cookie[] = $name . '=' . $value;
            }
            $headers[] = 'Cookie: ' . implode('; ', $cookie);
        }
        return $headers;
    }

    private function getUserAgent(array $config): string
    {
        if (!empty($config['user_agent'])) {
            return (string)$config['user_agent'];
        }
        foreach (($config['headers'] ?? []) as $name => $value) {
            if (strtolower((string)$name) === 'user-agent' && $value !== '') {
                return (string)$value;
            }
        }
        return 'Mozilla/5.0 recycle-quote-spider';
    }

    private function isIgnoredHeader(string $name): bool
    {
        return in_array($name, [
            'host',
            'connection',
            'content-length',
            'accept-encoding',
            'user-agent',
            'sec-fetch-site',
            'sec-fetch-mode',
            'sec-fetch-dest',
            'priority',
        ], true);
    }

    private function previewBody(string $body): string
    {
        $body = trim(strip_tags($body));
        $body = preg_replace('/\s+/', ' ', $body) ?: '';
        if ($body === '') {
            return '空响应';
        }
        return mb_substr($body, 0, 220);
    }
}
