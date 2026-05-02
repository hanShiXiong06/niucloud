<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation_v2;

use addon\recycle\app\service\core\quotation\QuotationCrawlerConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 超牛报价爬虫请求
 */
class ChaoniuCrawlerService extends BaseAdminService
{
    private $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new QuotationCrawlerConfigService();
    }

    public function fetch(array $dataset): array
    {
        $providerConfig = $this->configService->getProviderConfig($this->site_id, 'chaoniu', false);
        if (empty($providerConfig)) {
            throw new CommonException('超牛报价渠道未启用或未配置');
        }

        foreach (['base_url', 'detail_path', 'authorization_token', 'open_id'] as $field) {
            if (trim((string)($providerConfig[$field] ?? '')) === '') {
                throw new CommonException('超牛报价渠道配置不完整：' . $field . '。配置诊断：' . json_encode($this->configService->getDiagnostic($this->site_id), JSON_UNESCAPED_UNICODE));
            }
        }

        $params = $this->buildParams($dataset);
        $url = rtrim((string)$providerConfig['base_url'], '/') . '/' . ltrim((string)$providerConfig['detail_path'], '/') . '?' . http_build_query($params);
        $headers = $this->buildHeaders($providerConfig);
        $timeout = max(1, (int)($providerConfig['timeout'] ?? 30));
        $started = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $duration = (int)round((microtime(true) - $started) * 1000);
        if ($curlError) {
            throw new CommonException('超牛报价请求失败：' . $curlError);
        }

        $response = json_decode((string)$responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CommonException('超牛报价响应不是有效 JSON：' . json_last_error_msg());
        }
        if ($httpCode !== 200) {
            throw new CommonException('超牛报价 HTTP 状态异常：' . $httpCode);
        }
        if ((int)($response['code'] ?? 0) !== 200) {
            throw new CommonException('超牛报价返回失败：' . (string)($response['msg'] ?? '未知错误') . '。请求诊断：' . json_encode([
                'url' => $url,
                'headers' => $headers,
                'credential_source' => (string)($providerConfig['_credential_source'] ?? 'sys_config'),
            ], JSON_UNESCAPED_UNICODE));
        }

        return [
            'url' => $url,
            'params' => $params,
            'headers' => $headers,
            'credential_source' => (string)($providerConfig['_credential_source'] ?? 'sys_config'),
            'http_code' => $httpCode,
            'duration' => $duration,
            'response' => $response,
        ];
    }

    private function buildParams(array $dataset): array
    {
        $custom = is_array($dataset['request_params'] ?? null) ? $dataset['request_params'] : [];
        return array_merge([
            'quotation_id' => (int)$dataset['quotation_id'],
            'price_name' => (string)$dataset['price_name'],
            'default_price_value' => 200,
            'default_percentage_value' => 3,
            'price_adjustment_type' => 2,
            'price_adjustment_value' => -200,
            'watermark' => '南京熊猫哥',
            'quotation_background_color' => 'linear-gradient( 90deg, #000000 0%, #666666 100%)',
            'quotation_text_color' => '#ffffff',
        ], $custom);
    }

    private function buildHeaders(array $config): array
    {
        return [
            'Version' => (string)($config['version'] ?? '2.4.1'),
            'Platform' => (string)($config['platform'] ?? '2'),
            'Authorization' => (string)$config['authorization_token'],
            'OpenId' => (string)$config['open_id'],
            'content-type' => 'application/json',
            'Accept-Encoding' => (string)($config['accept_encoding'] ?? 'gzip,compress,br,deflate'),
            'User-Agent' => (string)($config['user_agent'] ?? ''),
            'Referer' => (string)($config['referer'] ?? ''),
        ];
    }

    private function formatHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if ($value !== '') {
                $result[] = $key . ': ' . $value;
            }
        }

        return $result;
    }

}
