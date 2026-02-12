<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core;

use addon\recycle\app\dict\yisu\YisuProductDict;
use addon\recycle\app\model\express\ExpressOrderRecord;
use addon\recycle\app\model\yisu\YisuProductConfig;
use addon\recycle\app\service\core\third_party\CoreThirdPartyService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 快递服务类
 * Class ExpressOrderService
 * @package addon\recycle\app\service\core
 */
class ExpressOrderService
{
    private $thirdPartyService;

    public function __construct()
    {
        $this->thirdPartyService = new CoreThirdPartyService();
    }

    /**
     * 获取快递报价（只返回启用的产品）
     * @param int $siteId 站点ID
     * @param array $params 参数
     * @return array
     * @throws CommonException
     */
    public function getQuote(int $siteId, array $params): array
    {
        // 必填参数验证
        $requiredFields = ['senderProvince', 'senderCity', 'senderDistrict', 'senderAddress',
                          'receiveProvince', 'receiveCity', 'receiveDistrict', 'receiveAddress',
                          'weight', 'packageCount'];

        foreach ($requiredFields as $field) {
            if (empty($params[$field])) {
                throw new CommonException("缺少必填参数: {$field}");
            }
        }

        // 调用第三方服务获取所有报价
        $result = $this->thirdPartyService->call(
            'express_order',  // 服务类型
            'preOrder',       // 方法：获取报价
            $params,
            $siteId
        );

        if (!$result['success']) {
            throw new CommonException($result['message'] ?? '获取报价失败');
        }

        // 获取启用的产品代码列表
        $enabledProductCodes = YisuProductConfig::getEnabledProductCodes($siteId);

        // 如果没有配置启用的产品，返回所有报价
        if (empty($enabledProductCodes)) {
            return $result['data'];
        }

        // 过滤只返回启用的产品
        $filteredData = [];
        foreach ($result['data'] as $quote) {
            if (in_array($quote['productCode'] ?? '', $enabledProductCodes)) {
                $filteredData[] = $quote;
            }
        }

        return $filteredData;
    }

    /**
     * 创建快递订单
     * @param int $siteId 站点ID
     * @param array $params 参数
     * @return array
     * @throws CommonException
     */
    public function createOrder(int $siteId, array $params): array
    {
        // 必填参数验证
        $requiredFields = ['deliveryType', 'senderName', 'senderMobile', 'senderProvince',
                          'senderCity', 'senderDistrict', 'senderAddress',
                          'receiveName', 'receiveMobile', 'receiveProvince',
                          'receiveCity', 'receiveDistrict', 'receiveAddress',
                          'goods', 'weight', 'packageCount'];

        foreach ($requiredFields as $field) {
            if (empty($params[$field])) {
                throw new CommonException("缺少必填参数: {$field}");
            }
        }

        // 调用第三方服务
        $result = $this->thirdPartyService->call(
            'express_order',
            'sendOrder',      // 方法：下单
            $params,
            $siteId
        );

        if (!$result['success']) {
            throw new CommonException($result['message'] ?? '下单失败');
        }

        // 创建快递订单记录
        $this->createExpressRecord($siteId, $params, $result['data']);

        return $result['data'];
    }

    /**
     * 创建快递订单记录
     * @param int $siteId
     * @param array $params
     * @param array $apiResult
     * @return void
     */
    private function createExpressRecord(int $siteId, array $params, array $apiResult): void
    {
        try {
            // 获取产品信息
            $productInfo = YisuProductDict::getProduct($params['deliveryType']);

            // 计算体积
            $volume = 0;
            if (!empty($params['vloumLong']) && !empty($params['vloumWidth']) && !empty($params['vloumHeight'])) {
                $volume = ($params['vloumLong'] / 100) * ($params['vloumWidth'] / 100) * ($params['vloumHeight'] / 100);
            }

            // 准备记录数据
            $recordData = [
                'site_id' => $siteId,
                'order_no' => $apiResult['orderNo'] ?? '',
                'recycle_order_id' => $params['recycle_order_id'] ?? 0,
                'recycle_device_id' => $params['recycle_device_id'] ?? 0,

                // 快递服务商信息
                'provider_name' => 'yisu',
                'product_code' => $params['deliveryType'],
                'product_name' => $productInfo['product_name'] ?? '',
                'delivery_id' => $apiResult['deliveryId'] ?? '',

                // 发件人信息
                'sender_name' => $params['senderName'],
                'sender_mobile' => $params['senderMobile'],
                'sender_province' => $params['senderProvince'],
                'sender_city' => $params['senderCity'],
                'sender_district' => $params['senderDistrict'],
                'sender_address' => $params['senderAddress'],

                // 收件人信息
                'receiver_name' => $params['receiveName'],
                'receiver_mobile' => $params['receiveMobile'],
                'receiver_province' => $params['receiveProvince'],
                'receiver_city' => $params['receiveCity'],
                'receiver_district' => $params['receiveDistrict'],
                'receiver_address' => $params['receiveAddress'],

                // 物品信息
                'goods_name' => $params['goods'],
                'goods_value' => $params['guaranteeValueAmount'] ?? 0,
                'package_count' => $params['packageCount'],

                // 重量和体积信息
                'estimated_weight' => $params['weight'],
                'actual_weight' => 0,
                'weight_diff' => 0,
                'volume' => $volume,
                'volume_long' => $params['vloumLong'] ?? 0,
                'volume_width' => $params['vloumWidth'] ?? 0,
                'volume_height' => $params['vloumHeight'] ?? 0,

                // 费用信息（从报价中获取，如果有的话）
                'estimated_cost' => $params['estimated_cost'] ?? 0,
                'actual_cost' => 0,
                'cost_diff' => 0,
                'payment_status' => 0,
                'user_paid' => 0,
                'discount_amount' => 0,

                // 订单状态
                'order_status' => 'pending',
                'status_history' => [
                    [
                        'status' => 'pending',
                        'remark' => '订单创建成功',
                        'time' => time(),
                    ]
                ],

                // API响应数据
                'api_response' => $apiResult,
            ];

            ExpressOrderRecord::createRecord($recordData);
        } catch (\Exception $e) {
            // 记录失败不影响主流程，只记录日志
            Log::error('创建快递订单记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 取消快递订单
     * @param int $siteId 站点ID
     * @param string $orderNo 订单号
     * @return bool
     * @throws CommonException
     */
    public function cancelOrder(int $siteId, string $orderNo): bool
    {
        $result = $this->thirdPartyService->call(
            'express_order',
            'cancelOrder',    // 方法：取消订单
            ['order_no' => $orderNo],
            $siteId
        );

        if (!$result['success']) {
            throw new CommonException($result['message'] ?? '取消订单失败');
        }

        // 更新快递订单记录状态
        try {
            ExpressOrderRecord::updateStatus($orderNo, 'cancelled', '用户取消订单');
        } catch (\Exception $e) {
            Log::error('更新快递订单记录状态失败: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * 查询快递轨迹
     * @param int $siteId 站点ID
     * @param string $deliveryId 运单号
     * @return array
     * @throws CommonException
     */
    public function trackOrder(int $siteId, string $deliveryId): array
    {
        $result = $this->thirdPartyService->call(
            'express_order',
            'track',          // 方法：轨迹查询
            ['delivery_id' => $deliveryId],
            $siteId
        );

        if (!$result['success']) {
            throw new CommonException($result['message'] ?? '查询轨迹失败');
        }

        return $result['data'];
    }

    /**
     * 查询账户余额
     * @param int $siteId 站点ID
     * @return float
     * @throws CommonException
     */
    public function getBalance(int $siteId): float
    {
        $result = $this->thirdPartyService->call(
            'express_order',
            'balance',        // 方法：余额查询
            [],
            $siteId
        );

        if (!$result['success']) {
            throw new CommonException($result['message'] ?? '查询余额失败');
        }

        return (float)($result['balance'] ?? 0);
    }
}
