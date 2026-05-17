<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party\provider\address_parse;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\third_party\provider\BaseProvider;

/**
 * 腾讯云市场地址识别服务
 */
class ProviderTencentCloudMarketAddress extends BaseProvider
{
    public function execute(string $method, array $params): array
    {
        switch ($method) {
            case 'parse':
                return $this->parseAddress((string)($params['address'] ?? ''));
            default:
                throw new \Exception("不支持的方法: {$method}");
        }
    }

    public function getBalance(): float
    {
        return 0;
    }

    public function healthCheck(): bool
    {
        return $this->getConfig('base_url', '') !== ''
            && $this->getConfig('api_path', '') !== '';
    }

    public function getName(): string
    {
        return '腾讯云市场地址解析';
    }

    public function getServiceType(): string
    {
        return ThirdPartyDict::SERVICE_TYPE_ADDRESS_PARSE;
    }

    public function getProviderName(): string
    {
        return ThirdPartyDict::PROVIDER_TENCENT_CLOUD_MARKET_ADDRESS;
    }

    private function parseAddress(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            throw new \Exception('地址内容不能为空');
        }

        $baseUrl = rtrim((string)$this->getConfig('base_url', ''), '/');
        $apiPath = '/' . ltrim((string)$this->getConfig('api_path', ''), '/');
        $secretId = (string)$this->getConfig('secret_id', '');
        $secretKey = (string)$this->getConfig('secret_key', '');
        if ($baseUrl === '' || $apiPath === '' || $secretId === '' || $secretKey === '') {
            throw new \Exception('地址解析配置不完整');
        }

        $date = gmdate('D, d M Y H:i:s T');
        $url = $baseUrl . $apiPath . '?' . http_build_query(['address' => $address]);
        $authorization = $this->buildAuthorization($secretId, $secretKey, $date);

        $response = $this->httpRequest('GET', $url, [], [
            'request-id: ' . $this->makeRequestId(),
            'Authorization: ' . $authorization,
            'X-Requested-With: XMLHttpRequest',
        ], 'form');

        if ((int)($response['code'] ?? 0) !== 1) {
            throw new \Exception($response['msg'] ?? '地址解析失败');
        }

        $data = $response['data'] ?? [];
        if (!is_array($data)) {
            throw new \Exception('地址解析响应格式错误');
        }

        return [
            'success' => true,
            'data' => [
                'name' => (string)($data['name'] ?? ''),
                'mobile' => (string)($data['mobile'] ?? ''),
                'area' => (string)($data['area'] ?? ''),
                'info' => (string)($data['info'] ?? ''),
                'province' => (string)($data['province'] ?? ''),
                'city' => (string)($data['city'] ?? ''),
                'district' => (string)($data['county'] ?? $data['district'] ?? ''),
                'raw' => $data,
            ],
            'raw_data' => $response,
            'message' => '解析成功',
        ];
    }

    private function buildAuthorization(string $secretId, string $secretKey, string $date): string
    {
        $signature = base64_encode(hash_hmac('sha1', 'x-date: ' . $date, $secretKey, true));

        return sprintf('{"id": "%s", "x-date": "%s" , "signature": "%s"}', $secretId, $date, $signature);
    }

    private function makeRequestId(): string
    {
        if (function_exists('uuid')) {
            return (string)uuid();
        }

        return md5(uniqid((string)mt_rand(), true));
    }
}
