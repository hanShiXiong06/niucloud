<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party\provider\express_order;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\third_party\provider\BaseProvider;

/**
 * 安果ERP快递服务提供者
 * Class ProviderAnguo
 * @package addon\hsx_recycle\app\service\core\third_party\provider\express_order
 */
class ProviderAnguo extends BaseProvider
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
            case 'createOrder':
                return $this->createOrder($params);
            case 'cancelOrder':
                return $this->cancelOrder($params);
            case 'trackExpress':
                return $this->trackExpress($params);
            case 'getOrderDetail':
                return $this->getOrderDetail($params);
            default:
                throw new \Exception("不支持的方法: {$method}");
        }
    }

    /**
     * 创建快递订单
     * @param array $params
     * @return array
     */
    private function createOrder(array $params): array
    {
        // 参数校验
        $this->validateOrderParams($params);

        $baseUrl = $this->getConfig('base_url', 'http://115.190.35.168:3000');
        $url = $baseUrl . '/api/waybill/sender';

        // 组装请求数据
        $data = [
            'senderName' => $params['senderName'],
            'senderPhone' => $params['senderMobile'],
            'senderProvince' => $params['senderProvince'],
            'senderCity' => $params['senderCity'],
            'senderArea' => $params['senderDistrict'],
            'senderAddress' => $params['senderAddress'],

            'recipientName' => $params['receiveName'],
            'recipientPhone' => $params['receiveMobile'],
            'recipientProvince' => $params['receiveProvince'],
            'recipientCity' => $params['receiveCity'],
            'recipientArea' => $params['receiveDistrict'],
            'recipientAddress' => $params['receiveAddress'],

            'weight' => $params['weight'] ?? 1.0,
            'remark' => $params['remark'] ?? '',
            'expressCompanyId' => $this->getExpressCompanyId($params['deliveryType'] ?? ''),
        ];

        $this->log('创建快递订单', ['data' => $data]);

        $response = $this->httpPost($url, $data, [
            'X-API-Key: afdd0b4ad2ec172c586e2150770fbf9e'
        ]);

        return $this->parseResponse($response);
    }

    /**
     * 取消订单
     * @param array $params
     * @return array
     */
    private function cancelOrder(array $params): array
    {
        $waybillNo = $params['waybill_no'] ?? '';
        if (empty($waybillNo)) {
            throw new \Exception('运单号不能为空');
        }

        $baseUrl = $this->getConfig('base_url', 'http://115.190.35.168:3000');
        $url = $baseUrl . '/api/waybill/cancel';

        $data = ['waybillNo' => $waybillNo];

        $response = $this->httpPost($url, $data, [
            'X-API-Key: afdd0b4ad2ec172c586e2150770fbf9e'
        ]);

        return $this->parseResponse($response);
    }

    /**
     * 查询物流轨迹
     * @param array $params
     * @return array
     */
    private function trackExpress(array $params): array
    {
        $waybillNo = $params['waybill_no'] ?? '';
        if (empty($waybillNo)) {
            throw new \Exception('运单号不能为空');
        }

        $baseUrl = $this->getConfig('base_url', 'http://115.190.35.168:3000');
        $url = $baseUrl . '/api/waybill/track';

        $data = ['waybillNo' => $waybillNo];

        $response = $this->httpPost($url, $data, [
            'X-API-Key: afdd0b4ad2ec172c586e2150770fbf9e'
        ]);

        return $this->parseResponse($response);
    }

    /**
     * 获取订单详情
     * @param array $params
     * @return array
     */
    private function getOrderDetail(array $params): array
    {
        $waybillNo = $params['waybill_no'] ?? '';
        if (empty($waybillNo)) {
            throw new \Exception('运单号不能为空');
        }

        $baseUrl = $this->getConfig('base_url', 'http://115.190.35.168:3000');
        $url = $baseUrl . '/api/waybill/detail';

        $data = ['waybillNo' => $waybillNo];

        $response = $this->httpPost($url, $data, [
            'X-API-Key: afdd0b4ad2ec172c586e2150770fbf9e'
        ]);

        return $this->parseResponse($response);
    }

    /**
     * 验证订单参数
     * @param array $params
     * @throws \Exception
     */
    private function validateOrderParams(array $params)
    {
        $requiredFields = [
            'senderName' => '寄件人姓名',
            'senderMobile' => '寄件人电话',
            'senderProvince' => '寄件人省份',
            'senderCity' => '寄件人城市',
            'senderDistrict' => '寄件人区县',
            'senderAddress' => '寄件人详细地址',
            'receiveName' => '收件人姓名',
            'receiveMobile' => '收件人电话',
            'receiveProvince' => '收件人省份',
            'receiveCity' => '收件人城市',
            'receiveDistrict' => '收件人区县',
            'receiveAddress' => '收件人详细地址',
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($params[$field]) || $params[$field] === '') {
                throw new \Exception("{$label}不能为空");
            }
        }
    }

    /**
     * 获取快递公司ID
     * @param string $deliveryType
     * @return int
     */
    private function getExpressCompanyId(string $deliveryType): int
    {
        // 根据快递类型返回对应的公司ID
        $companyMap = [
            'shunfeng' => 1,  // 顺丰
            'yuantong' => 2,  // 圆通
            'zhongtong' => 3, // 中通
            'yunda' => 4,     // 韵达
            'shentong' => 5,  // 申通
        ];

        return $companyMap[$deliveryType] ?? 1;
    }

    /**
     * 解析响应
     * @param array $response
     * @return array
     */
    private function parseResponse(array $response): array
    {
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
        ];
    }

    /**
     * 获取余额
     * @return float
     */
    public function getBalance(): float
    {
        try {
            $baseUrl = $this->getConfig('base_url', 'http://115.190.35.168:3000');
            $url = $baseUrl . '/api/user/balance';

            $response = $this->httpGet($url, [], [
                'X-API-Key: afdd0b4ad2ec172c586e2150770fbf9e'
            ]);

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
        try {
            $balance = $this->getBalance();
            return $balance >= 0; // 安果可能余额为0但服务正常
        } catch (\Exception $e) {
            $this->logError('健康检查失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取服务名称
     * @return string
     */
    public function getName(): string
    {
        return '安果ERP快递';
    }

    /**
     * 获取服务类型
     * @return string
     */
    public function getServiceType(): string
    {
        return ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER;
    }

    /**
     * 获取提供商名称
     * @return string
     */
    public function getProviderName(): string
    {
        return ThirdPartyDict::PROVIDER_ANGUO;
    }
}
