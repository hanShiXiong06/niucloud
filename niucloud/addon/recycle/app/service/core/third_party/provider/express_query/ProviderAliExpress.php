<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\third_party\provider\express_query;

use addon\recycle\app\dict\third_party\ThirdPartyDict;
use addon\recycle\app\service\core\third_party\provider\BaseProvider;

/**
 * 阿里云快递查询服务提供者
 * Class ProviderAliExpress
 * @package addon\recycle\app\service\core\third_party\provider\express_query
 */
class ProviderAliExpress extends BaseProvider
{
    /**
     * 获取服务名称
     * @return string
     */
    public function getName(): string
    {
        return '阿里云快递查询';
    }

    /**
     * 获取服务类型
     * @return string
     */
    public function getServiceType(): string
    {
        return ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY;
    }

    /**
     * 获取提供商名称
     * @return string
     */
    public function getProviderName(): string
    {
        return ThirdPartyDict::PROVIDER_ALI_EXPRESS;
    }

    /**
     * 执行调用
     * @param string $method 方法名
     * @param array $params 参数
     * @return array
     */
    public function execute(string $method, array $params): array
    {
        switch ($method) {
            case 'query':
                return $this->queryExpress($params);
            default:
                throw new \Exception("不支持的方法: {$method}");
        }
    }

    /**
     * 查询快递信息
     * @param array $params 参数 ['express_no' => '快递单号', 'mobile' => '手机号后四位']
     * @return array
     */
    protected function queryExpress(array $params): array
    {
        $expressNo = $params['express_no'] ?? '';
        $mobile = $params['mobile'] ?? '';

        if (empty($expressNo)) {
            throw new \Exception('快递单号不能为空');
        }

        $baseUrl = $this->getConfig('base_url', '');
        $apiKey = $this->getConfig('api_key');
        $apiPath = $this->getConfig('api_path', $this->getConfig('enabled_apis', ''));

        if (empty($baseUrl) || empty($apiKey) || empty($apiPath)) {
            throw new \Exception('阿里快递查询配置不完整');
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($apiPath, '/');
        $method = "POST";

        // 设置请求头
        $headers = [
            'Authorization:APPCODE ' . $apiKey,
            'Content-Type:application/x-www-form-urlencoded; charset=UTF-8',
            'Accept:application/json',
        ];

        // 构建POST请求体
        $bodys = "expressNo={$expressNo}&mobile={$mobile}";

        try {
            // 使用curl发送POST请求
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->getConfig('timeout', 30));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                throw new \Exception($err);
            }

            // 解析响应
            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON解析失败: ' . json_last_error_msg());
            }

            // 返回原始结果（与原API服务保持一致）
            return [
                'success' => true,
                'data' => $result,
                'message' => '查询成功',
            ];

        } catch (\Exception $e) {
            $this->logError('快递查询失败', [
                'express_no' => $expressNo,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 获取余额
     * @return float
     */
    public function getBalance(): float
    {
        // 阿里云市场API通常不提供余额查询接口
        // 需要在阿里云控制台查看
        return 0.0;
    }

    /**
     * 健康检查
     * @return bool
     */
    public function healthCheck(): bool
    {
        return !empty($this->getConfig('base_url', ''))
            && !empty($this->getConfig('api_key', ''))
            && !empty($this->getConfig('api_path', $this->getConfig('enabled_apis', '')));
    }
}
