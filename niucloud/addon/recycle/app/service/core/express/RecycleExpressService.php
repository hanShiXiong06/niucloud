<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\express;

use addon\recycle\app\dict\express\ExpressProviderDict;
use addon\recycle\app\model\express\ExpressProviderConfig;
use addon\recycle\app\model\express\ExpressOrderRecord;
use addon\recycle\app\model\order\RecycleOrder;
use addon\recycle\app\service\core\ExpressOrderService;
use addon\recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 统一快递服务
 * 2.0 阶段仅保留亿速快递，订单侧仍通过本服务访问平台快递能力。
 * Class RecycleExpressService
 * @package addon\recycle\app\service\core\express
 */
class RecycleExpressService
{
    /**
     * 获取当前启用的默认服务商
     * @param int $siteId
     * @return string
     * @throws CommonException
     */
    public function getActiveProvider(int $siteId): string
    {
        $configService = new RecycleThirdPartyConfigService();
        if ($configService->hasSavedConfig($siteId)) {
            if (!$configService->isServiceEnabled($siteId, 'express_order')) {
                throw new CommonException('快递服务未启用，请在第三方配置中心启用亿速快递');
            }
            if (!$configService->isProviderConfigComplete($siteId, 'express_order', ExpressProviderDict::PROVIDER_YISU)) {
                throw new CommonException('亿速快递配置不完整，请在第三方配置中心配置');
            }
            return ExpressProviderDict::PROVIDER_YISU;
        }

        $provider = ExpressProviderConfig::getDefaultProvider($siteId);
        if (empty($provider)) {
            throw new CommonException('未配置快递服务商，请在后台设置');
        }
        if ($provider !== ExpressProviderDict::PROVIDER_YISU) {
            throw new CommonException('当前仅支持亿速快递，请在后台启用亿速快递服务');
        }
        return $provider;
    }

    /**
     * 获取快递报价
     * @param int $siteId 站点ID
     * @param array $senderAddress 寄件地址 {name, mobile, province, city, district, address}
     * @param float $weight 预估重量(kg)
     * @param int $packageCount 包裹数量
     * @return array
     * @throws CommonException
     */
    public function getQuote(int $siteId, array $senderAddress, float $weight = 1.0, int $packageCount = 1): array
    {
        $provider = $this->getActiveProvider($siteId);

        // 获取收件地址(商户)
        $shopAddress = $this->getShopAddress($siteId);
        if (!$shopAddress) {
            throw new CommonException('未配置商户收货地址，请先在后台配置');
        }

        return $this->getYisuQuote($siteId, $senderAddress, $shopAddress, $weight, $packageCount);
    }

    /**
     * 亿速报价
     */
    private function getYisuQuote(int $siteId, array $sender, array $receiver, float $weight, int $packageCount): array
    {
        $expressService = new ExpressOrderService();

        $params = [
            'senderProvince' => $sender['province'] ?? '',
            'senderCity' => $sender['city'] ?? '',
            'senderDistrict' => $sender['district'] ?? '',
            'senderAddress' => $sender['address'] ?? '',
            'senderMobile' => $sender['mobile'] ?? '',
            'receiveProvince' => $receiver['province'] ?? '',
            'receiveCity' => $receiver['city'] ?? '',
            'receiveDistrict' => $receiver['district'] ?? '',
            'receiveAddress' => $receiver['address'] ?? '',
            'weight' => $weight,
            'packageCount' => $packageCount,
        ];

        $quoteList = $expressService->getQuote($siteId, $params);

        // 统一返回格式
        $result = [];
        foreach ($quoteList as $item) {
            $price = (float)($item['totalPrice'] ?? $item['totalFee'] ?? $item['price'] ?? 0);
            if ($price <= 0) {
                $price = (float)($item['channelFee'] ?? 0)
                    + (float)($item['serviceCharge'] ?? 0)
                    + (float)($item['guarantFee'] ?? 0)
                    + (float)($item['incrementFee'] ?? 0);
            }
            $result[] = [
                'product_code' => $item['productCode'] ?? '',
                'product_name' => $item['productName'] ?? $item['channelName'] ?? $item['typeName'] ?? '',
                'price' => $price,
                'estimated_time' => $item['aging'] ?? $item['promiseTimeType'] ?? '',
                'provider' => ExpressProviderDict::PROVIDER_YISU,
                'logo' => $item['logo'] ?? '',
                'raw' => $item,
            ];
        }

        return [
            'provider' => ExpressProviderDict::PROVIDER_YISU,
            'provider_name' => '亿速物流',
            'list' => $result,
        ];
    }

    /**
     * 创建快递订单（与回收订单绑定）
     * @param int $siteId 站点ID
     * @param int $recycleOrderId 回收订单ID
     * @param array $expressConfig 快递配置
     * @param array $operatorInfo 操作人信息 {uid, username, source: admin|user, member_id}
     * @return array
     * @throws CommonException
     */
    public function createOrder(int $siteId, int $recycleOrderId, array $expressConfig, array $operatorInfo = []): array
    {
        $provider = $this->getActiveProvider($siteId);

        // 获取收件地址(商户)
        $shopAddress = $this->getShopAddress($siteId);
        if (!$shopAddress) {
            throw new CommonException('未配置商户收货地址，请先在后台配置');
        }

        Db::startTrans();
        try {
            // 获取回收订单
            $order = RecycleOrder::where('site_id', $siteId)->find($recycleOrderId);
            if (!$order) {
                throw new CommonException('回收订单不存在');
            }

            // 检查是否已下单快递
            if (!empty($order->express_no) && $order->delivery_status > 0) {
                throw new CommonException('该订单已有快递单，运单号：' . $order->express_no);
            }

            $result = [];

            $result = $this->createYisuOrder($siteId, $order, $expressConfig, $shopAddress, $operatorInfo);

            Db::commit();

            Log::info("回收订单{$recycleOrderId}快递下单成功", [
                'provider' => $provider,
                'express_no' => $result['express_no'] ?? '',
                'operator' => $operatorInfo,
            ]);

            return $result;

        } catch (\Exception $e) {
            Db::rollback();
            Log::error("回收订单{$recycleOrderId}快递下单失败: " . $e->getMessage());
            throw new CommonException('快递下单失败：' . $e->getMessage());
        }
    }

    /**
     * 亿速下单
     */
    private function createYisuOrder(int $siteId, $order, array $config, array $shopAddress, array $operatorInfo): array
    {
        $expressService = new ExpressOrderService();

        // 如果前端没传 product_code，自动取第一个启用的亿速产品
        $productCode = $config['product_code'] ?? '';
        if (empty($productCode)) {
            $enabledProducts = \addon\recycle\app\model\yisu\YisuProductConfig::getEnabledProducts($siteId);
            if (!empty($enabledProducts)) {
                $productCode = $enabledProducts[0]['product_code'];
            }
        }
        if (empty($productCode)) {
            throw new CommonException('未配置可用的亿速快递产品，请先在后台启用快递产品');
        }

        foreach (['province' => '省份', 'city' => '城市', 'district' => '区县', 'address' => '详细地址'] as $field => $label) {
            if (trim((string)($shopAddress[$field] ?? '')) === '') {
                throw new CommonException('商家收货地址' . $label . '不完整，请在后台重新选择省市区并保存地址');
            }
        }

        $params = [
            // 快递产品
            'deliveryType' => $productCode,
            'provider' => ExpressProviderDict::PROVIDER_YISU,
            'provider_name' => trim((string)($config['provider_name'] ?? ExpressProviderDict::getProviderName(ExpressProviderDict::PROVIDER_YISU))),

            // 寄件人(用户)
            'senderName' => $config['sender_name'] ?? '',
            'senderMobile' => $config['sender_mobile'] ?? '',
            'senderProvince' => $config['sender_province'] ?? '',
            'senderCity' => $config['sender_city'] ?? '',
            'senderDistrict' => $config['sender_district'] ?? '',
            'senderAddress' => $config['sender_address'] ?? '',

            // 收件人(商户)
            'receiveName' => $shopAddress['contact_name'],
            'receiveMobile' => $shopAddress['mobile'],
            'receiveProvince' => $shopAddress['province'],
            'receiveCity' => $shopAddress['city'],
            'receiveDistrict' => $shopAddress['district'],
            'receiveAddress' => $shopAddress['address'],

            // 物品信息
            'goods' => '回收设备',
            'weight' => (float)($config['weight'] ?? 1.0),
            'packageCount' => (int)($config['package_count'] ?? 1),
            'thirdOrderNo' => 'recycle_' . $siteId . '_' . $order->id,
            'remark' => $config['remark'] ?? '',
            'orderSendTime' => $config['pickup_time'] ?? '',

            // 关联回收订单
            'recycle_order_id' => $order->id,
            'estimated_cost' => (float)($config['estimated_cost'] ?? 0),
        ];

        // 调用亿速下单
        $apiResult = $expressService->createOrder($siteId, $params);

        $expressNo = $apiResult['deliveryId'] ?? $apiResult['orderNo'] ?? '';
        $orderNo = $apiResult['orderNo'] ?? '';
        if (empty($expressNo) && empty($orderNo)) {
            throw new CommonException('亿速下单成功但未返回运单号');
        }
        $estimatedCost = (float)($config['estimated_cost'] ?? 0);

        // 更新回收订单
        $order->save([
            'express_no' => $expressNo,
            'delivery_platform' => ExpressProviderDict::PROVIDER_YISU,
            'delivery_fee' => $estimatedCost,
            'delivery_status' => 1, // 已下单
            'delivery_order_id' => $orderNo,
            'delivery_data' => json_encode([
                'provider' => ExpressProviderDict::PROVIDER_YISU,
                'provider_name' => $params['provider_name'] ?? ExpressProviderDict::getProviderName(ExpressProviderDict::PROVIDER_YISU),
                'sender' => [
                    'name' => $config['sender_name'] ?? '',
                    'mobile' => $config['sender_mobile'] ?? '',
                    'province' => $config['sender_province'] ?? '',
                    'city' => $config['sender_city'] ?? '',
                    'district' => $config['sender_district'] ?? '',
                    'address' => $config['sender_address'] ?? '',
                ],
                'receiver' => $shopAddress,
                'product_code' => $productCode,
                'weight' => $config['weight'] ?? 1.0,
                'estimated_cost' => $estimatedCost,
                'order_no' => $orderNo,
                'express_no' => $expressNo,
                'create_time' => date('Y-m-d H:i:s'),
                'operator' => $operatorInfo,
            ], JSON_UNESCAPED_UNICODE),
            'pickup_time' => $config['pickup_time'] ?? '',
            'update_at' => time(),
        ]);

        return [
            'express_no' => $expressNo,
            'order_no' => $orderNo,
            'delivery_id' => $expressNo,
            'estimated_cost' => $estimatedCost,
            'provider' => ExpressProviderDict::PROVIDER_YISU,
            'provider_name' => $params['provider_name'] ?? ExpressProviderDict::getProviderName(ExpressProviderDict::PROVIDER_YISU),
        ];
    }

    /**
     * 取消快递订单
     * @param int $siteId
     * @param int $recycleOrderId
     * @param array $operatorInfo
     * @return bool
     * @throws CommonException
     */
    public function cancelOrder(int $siteId, int $recycleOrderId, array $operatorInfo = []): bool
    {
        $order = RecycleOrder::where('site_id', $siteId)->find($recycleOrderId);
        if (!$order) {
            throw new CommonException('回收订单不存在');
        }

        if (empty($order->express_no) || $order->delivery_status <= 0) {
            throw new CommonException('该订单未下快递单');
        }

        if ($order->delivery_status >= 3) {
            throw new CommonException('快递已签收或已取消，无法操作');
        }

        $platform = $order->delivery_platform;

        Db::startTrans();
        try {
            if ($platform !== ExpressProviderDict::PROVIDER_YISU) {
                throw new CommonException("未知的快递平台: {$platform}");
            }

            $expressService = new ExpressOrderService();
            $orderNo = $order->delivery_order_id ?: $order->express_no;
            $expressService->cancelOrder($siteId, $orderNo);

            // 更新回收订单
            $order->save([
                'delivery_status' => 4, // 已取消
                'delivery_fee' => 0,    // 取消后费用归零
                'update_at' => time(),
            ]);

            // 记录操作日志
            $deliveryData = json_decode($order->delivery_data ?: '{}', true);
            $deliveryData['cancel_info'] = [
                'cancel_time' => date('Y-m-d H:i:s'),
                'operator' => $operatorInfo,
            ];
            $order->save([
                'delivery_data' => json_encode($deliveryData, JSON_UNESCAPED_UNICODE),
            ]);

            Db::commit();

            Log::info("回收订单{$recycleOrderId}快递已取消", [
                'platform' => $platform,
                'operator' => $operatorInfo,
            ]);

            return true;

        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException('取消快递失败：' . $e->getMessage());
        }
    }

    /**
     * 查询快递轨迹
     * @param int $siteId
     * @param int $recycleOrderId
     * @return array
     * @throws CommonException
     */
    public function trackOrder(int $siteId, int $recycleOrderId): array
    {
        $order = RecycleOrder::where('site_id', $siteId)->find($recycleOrderId);
        if (!$order || empty($order->express_no)) {
            throw new CommonException('订单不存在或未下快递单');
        }

        $platform = $order->delivery_platform;

        if ($platform !== ExpressProviderDict::PROVIDER_YISU) {
            throw new CommonException("未知的快递平台: {$platform}");
        }

        $expressService = new ExpressOrderService();
        return $expressService->trackOrder($siteId, $order->express_no);
    }

    /**
     * 同步快递状态
     * @param int $siteId
     * @param int $recycleOrderId
     * @return array
     */
    public function syncStatus(int $siteId, int $recycleOrderId): array
    {
        return $this->trackOrder($siteId, $recycleOrderId);
    }

    /**
     * 获取可用的快递服务商信息（给前端展示）
     * @param int $siteId
     * @return array
     */
    public function getAvailableProviders(int $siteId): array
    {
        $configService = new RecycleThirdPartyConfigService();
        if ($configService->hasSavedConfig($siteId)) {
            if (!$configService->isServiceEnabled($siteId, 'express_order')) {
                return [];
            }

            return [
                [
                    'provider' => ExpressProviderDict::PROVIDER_YISU,
                    'provider_name' => ExpressProviderDict::getProviderName(ExpressProviderDict::PROVIDER_YISU),
                    'is_default' => 1,
                    'support_quote' => true,
                    'support_cancel' => true,
                    'support_track' => true,
                ],
            ];
        }

        $providers = ExpressProviderConfig::getEnabledProviders($siteId);
        $allProviders = ExpressProviderDict::getProviders();

        $result = [];
        foreach ($providers as $p) {
            $key = $p['provider'];
            $info = $allProviders[$key] ?? [];
            $result[] = [
                'provider' => $key,
                'provider_name' => $p['provider_name'],
                'is_default' => $p['is_default'],
                'support_quote' => $info['support_quote'] ?? false,
                'support_cancel' => $info['support_cancel'] ?? false,
                'support_track' => $info['support_track'] ?? false,
            ];
        }

        return $result;
    }

    /**
     * 判断站点是否启用了平台快递服务
     * @param int $siteId
     * @return bool
     */
    public function isExpressEnabled(int $siteId): bool
    {
        $configService = new RecycleThirdPartyConfigService();
        if ($configService->hasSavedConfig($siteId)) {
            return $configService->isServiceEnabled($siteId, 'express_order')
                && $configService->isProviderConfigComplete($siteId, 'express_order', ExpressProviderDict::PROVIDER_YISU);
        }

        $provider = ExpressProviderConfig::getDefaultProvider($siteId);
        return !empty($provider);
    }

    /**
     * 获取商户收货地址
     * @param int $siteId
     * @return array|null
     */
    public function getShopAddress(int $siteId): ?array
    {
        $address = Db::name('recycle_shop_address')
            ->where('site_id', $siteId)
            ->where('is_default_refund', 1)
            ->find();

        if (!$address) {
            $address = Db::name('recycle_shop_address')
                ->where('site_id', $siteId)
                ->order('id', 'asc')
                ->find();
        }

        if (!$address) {
            return null;
        }

        $fullAddress = (string)($address['full_address'] ?? '');
        $addressParts = $this->resolveShopAddressParts($address);

        return [
            'contact_name' => $address['contact_name'] ?? '',
            'mobile' => $address['mobile'] ?? '',
            'province' => $addressParts['province'],
            'city' => $addressParts['city'],
            'district' => $addressParts['district'],
            'address' => $addressParts['detail'],
            'full_address' => $fullAddress,
        ];
    }

    /**
     * 解析商家地址。优先使用省市区 ID，避免 full_address 格式变化导致省市区为空。
     * @param array $address
     * @return array
     */
    private function resolveShopAddressParts(array $address): array
    {
        $provinceId = (int)($address['province_id'] ?? 0);
        $cityId = (int)($address['city_id'] ?? 0);
        $districtId = (int)($address['district_id'] ?? 0);

        if ($provinceId > 0 && $cityId > 0 && $districtId > 0) {
            $areaNames = Db::name('sys_area')
                ->whereIn('id', [$provinceId, $cityId, $districtId])
                ->column('name', 'id');

            $province = (string)($areaNames[$provinceId] ?? '');
            $city = (string)($areaNames[$cityId] ?? '');
            $district = (string)($areaNames[$districtId] ?? '');
            $detail = trim((string)($address['address'] ?? ''));

            if ($province !== '' && $city !== '' && $district !== '') {
                return [
                    'province' => $province,
                    'city' => $city,
                    'district' => $district,
                    'detail' => $detail,
                ];
            }
        }

        $fullAddress = (string)($address['full_address'] ?? '');
        $addressParts = $this->parseAddress($fullAddress);
        if ($addressParts['detail'] === '' && !empty($address['address'])) {
            $addressParts['detail'] = (string)$address['address'];
        }

        return $addressParts;
    }

    /**
     * 解析地址为省市区
     * @param string $fullAddress
     * @return array
     */
    private function parseAddress(string $fullAddress): array
    {
        // 匹配省市区
        $pattern = '/^(.+?(?:省|自治区|市))(.+?(?:市|自治州|地区|盟))(.+?(?:区|县|市|旗))(.+)$/u';

        if (preg_match($pattern, $fullAddress, $matches)) {
            return [
                'province' => $matches[1],
                'city' => $matches[2],
                'district' => $matches[3],
                'detail' => $matches[4],
            ];
        }

        return [
            'province' => '',
            'city' => '',
            'district' => '',
            'detail' => $fullAddress,
        ];
    }
}
