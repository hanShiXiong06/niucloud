<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\third_party\provider\device_query;

use addon\recycle\app\dict\third_party\ThirdPartyDict;
use addon\recycle\app\service\core\third_party\provider\BaseProvider;

/**
 * 3023设备查询服务提供者
 * Class Provider3023
 * @package addon\recycle\app\service\core\third_party\provider\device_query
 */
class Provider3023 extends BaseProvider
{
    /**
     * 执行调用
     * @param string $method
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function execute(string $method, array $params): array
    {
        switch ($method) {
            case 'queryByImei':
                return $this->queryByImei($params['imei'] ?? '', $params['api'] ?? '/apple/model');
            case 'getCoverage':
                return $this->getCoverage($params);
            case 'getActivationLock':
                return $this->getActivationLock($params['imei'] ?? '');
            case 'getMdm':
                return $this->getMdm($params['imei'] ?? '');
            default:
                throw new \Exception("不支持的方法: {$method}");
        }
    }

    /**
     * 通过IMEI查询设备信息
     * @param string $imei
     * @param string $api
     * @return array
     */
    private function queryByImei(string $imei, string $api = '/apple/model'): array
    {
        if (empty($imei)) {
            throw new \Exception('IMEI不能为空');
        }

        $baseUrl = $this->getConfig('base_url', '');
        $apiKey = $this->getConfig('api_key', '');

        if (empty($apiKey)) {
            throw new \Exception('API Key未配置');
        }

        // 构建URL - 3023 API使用GET请求，参数在URL中
        // $url = rtrim($baseUrl, '/') . $api . '?imei=' . $imei;

        $paramKey = str_starts_with($api, '/apple/') ? 'imei' : 'sn';
        $url = rtrim($baseUrl, '/') . $api . '?' . $paramKey . '=' . $imei;

        // 设置请求头 - API key在header中
        $headers = [
            'key: ' . $apiKey
        ];

        // 使用GET请求
        $response = $this->httpGet($url, [], $headers);

        return $this->parseResponse($response);
    }

    /**
     * 获取保修信息
     * @param array $params
     * @return array
     */
    private function getCoverage(array $params): array
    {
        $imei = $params['imei'] ?? '';
        $sn = $params['sn'] ?? '';

        if (empty($imei) && empty($sn)) {
            throw new \Exception('IMEI或序列号不能为空');
        }

        $baseUrl = $this->getConfig('base_url', '');
        $apiKey = $this->getConfig('api_key', '');

        // 构建URL
        $url = rtrim($baseUrl, '/') . '/apple/coverage';
        if (!empty($imei)) {
            $url .= '?imei=' . $imei;
        } else {
            $url .= '?sn=' . $sn;
        }

        // 设置请求头
        $headers = [
            'key: ' . $apiKey
        ];

        $response = $this->httpGet($url, [], $headers);

        return $this->parseResponse($response);
    }

    /**
     * 获取激活锁状态
     * @param string $imei
     * @return array
     */
    private function getActivationLock(string $imei): array
    {
        if (empty($imei)) {
            throw new \Exception('IMEI不能为空');
        }

        $baseUrl = $this->getConfig('base_url', '');
        $apiKey = $this->getConfig('api_key', '');

        $url = rtrim($baseUrl, '/') . '/apple/activationlock?imei=' . $imei;
        $headers = [
            'key: ' . $apiKey
        ];

        $response = $this->httpGet($url, [], $headers);

        return $this->parseResponse($response);
    }

    /**
     * 获取MDM监管锁状态
     * @param string $imei
     * @return array
     */
    private function getMdm(string $imei): array
    {
        if (empty($imei)) {
            throw new \Exception('IMEI不能为空');
        }

        $baseUrl = $this->getConfig('base_url', '');
        $apiKey = $this->getConfig('api_key', '');

        $url = rtrim($baseUrl, '/') . '/apple/mdm?imei=' . $imei;
        $headers = [
            'key: ' . $apiKey
        ];

        $response = $this->httpGet($url, [], $headers);

        return $this->parseResponse($response);
    }

    /**
     * 解析响应
     * @param array $response
     * @return array
     */
    private function parseResponse(array $response): array
    {
        // 根据3023 API的响应格式解析
        if (!isset($response['code'])) {
            throw new \Exception('API响应格式错误');
        }

        if ($response['code'] != 0) {
            throw new \Exception($response['message'] ?? 'API调用失败');
        }

        return [
            'success' => true,
            'data' => $response['data'] ?? [],
            'cost' => $response['cost'] ?? 0,
            'balance' => $response['balance'] ?? 0,
        ];
    }

    /**
     * 获取余额
     * @return float
     */
    public function getBalance(): float
    {
        try {
            $baseUrl = $this->getConfig('base_url', '');
            $apiKey = $this->getConfig('api_key', '');

            $url = rtrim($baseUrl, '/') . '/user/balance';
            $headers = [
                'key: ' . $apiKey
            ];

            $response = $this->httpGet($url, [], $headers);

            if (isset($response['code']) && $response['code'] == 0) {
                return (float)($response['data']['balance'] ?? 0);
            }

            return 0;
        } catch (\Exception $e) {
            $this->logError('获取余额失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 健康检查
     * @return bool
     */
    public function healthCheck(): bool
    {
        // 检查必要的配置是否存在
        $apiKey = $this->getConfig('api_key', '');
        $baseUrl = $this->getConfig('base_url', '');
        if (empty($baseUrl) || empty($apiKey)) {
            $this->logError('健康检查失败: 3023配置不完整');
            return false;
        }

        // 配置完整即认为健康（避免每次都调用API）
        return true;
    }

    /**
     * 获取服务名称
     * @return string
     */
    public function getName(): string
    {
        return '3023设备查询';
    }

    /**
     * 获取服务类型
     * @return string
     */
    public function getServiceType(): string
    {
        return ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY;
    }

    /**
     * 获取提供商名称
     * @return string
     */
    public function getProviderName(): string
    {
        return ThirdPartyDict::PROVIDER_3023;
    }
}
