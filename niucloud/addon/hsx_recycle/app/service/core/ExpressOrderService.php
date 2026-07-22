<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core;

use addon\hsx_recycle\app\model\express\ExpressAddressBook;
use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\express\ExpressDomainEventService;
use addon\hsx_recycle\app\service\core\express\ExpressGatewayService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 快递服务类
 * Class ExpressOrderService
 * @package addon\hsx_recycle\app\service\core
 */
class ExpressOrderService
{
    private $expressGateway;
    private $domainEventService;

    public function __construct()
    {
        $this->expressGateway = new ExpressGatewayService();
        $this->domainEventService = new ExpressDomainEventService();
    }

    /**
     * 获取快递报价
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
        if (empty($params['thirdOrderNo'])) {
            $params['thirdOrderNo'] = $params['third_order_no']
                ?? (!empty($params['recycle_order_id']) ? 'recycle_' . $siteId . '_' . $params['recycle_order_id'] : 'recycle_express_' . $siteId . '_' . date('YmdHis') . mt_rand(1000, 9999));
        }

        $productCode = $this->firstFilledString($params, ['productCode', 'deliveryType']);
        if ($productCode === '' || $productCode === '0') {
            return $this->getEnabledProductQuotes($siteId, $params);
        }

        if (!$this->isProductEnabled($siteId, $productCode)) {
            throw new CommonException('所选快递产品未启用，请先在快递产品配置中启用');
        }

        return $this->getSingleProductQuote($siteId, $params, $productCode);
    }

    public function getProducts(int $siteId): array
    {
        return $this->expressGateway->products($siteId);
    }

    private function getEnabledProductQuotes(int $siteId, array $params): array
    {
        $enabledProducts = $this->expressGateway->products($siteId);
        if (empty($enabledProducts)) {
            throw new CommonException('暂无启用的快递产品，请先在快递产品配置中启用');
        }

        $quotes = [];
        $errors = [];
        foreach ($enabledProducts as $product) {
            $productCode = (string)($product['product_code'] ?? '');
            if ($productCode === '') {
                continue;
            }

            try {
                foreach ($this->getSingleProductQuote($siteId, $params, $productCode) as $quote) {
                    if ($this->resolveQuoteAmount($quote) > 0) {
                        $quotes[] = $quote;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = ($product['product_name'] ?? $productCode) . '：' . $e->getMessage();
            }
        }

        usort($quotes, function ($left, $right) {
            return $this->resolveQuoteAmount($left) <=> $this->resolveQuoteAmount($right);
        });

        if (empty($quotes)) {
            $message = '未获取到可用报价';
            if (!empty($errors)) {
                $message .= '：' . implode('；', array_slice($errors, 0, 3));
            }
            throw new CommonException($message);
        }

        return $quotes;
    }

    private function getSingleProductQuote(int $siteId, array $params, string $productCode): array
    {
        $quoteParams = $params;
        $quoteParams['productCode'] = $productCode;
        $quoteParams['deliveryType'] = $productCode;
        $quote = $this->expressGateway->quote($siteId, $quoteParams);
        if (empty($quote)) {
            return [];
        }

        if (isset($quote[0]) && is_array($quote[0])) {
            foreach ($quote as &$item) {
                $item = $this->fillQuoteProductInfo($item, $productCode);
            }
            return $quote;
        }

        return [$this->fillQuoteProductInfo($quote, $productCode)];
    }

    private function fillQuoteProductInfo(array $quote, string $fallbackProductCode = ''): array
    {
        $quote['debug_raw'] = $quote;
        $productCode = (string)($quote['productCode'] ?? $quote['product_code'] ?? $fallbackProductCode);
        if ($productCode !== '') {
            $quote['productCode'] = $productCode;
            $quote['productName'] = $quote['productName'] ?? $quote['product_name'] ?? $productCode;
        } else {
            $quote['productName'] = $quote['productName'] ?? '智能报价';
        }
        $quote['estimatedCost'] = $this->resolveQuoteAmount($quote);

        return $quote;
    }

    private function firstFilledString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
                return trim((string)$data[$key]);
            }
        }

        return '';
    }

    private function isProductEnabled(int $siteId, string $productCode): bool
    {
        return $this->findProduct($siteId, $productCode) !== null;
    }

    private function findProduct(int $siteId, string $productCode): ?array
    {
        foreach ($this->expressGateway->products($siteId) as $product) {
            if ((string)($product['product_code'] ?? '') === $productCode && !empty($product['enabled'])) {
                return $product;
            }
        }
        return null;
    }

    private function resolveQuoteAmount(array $quote): float
    {
        foreach ([
            'estimatedCost',
            'totalPrice',
            'totalFee',
            'totalAmount',
            'price',
            'fee',
            'amount',
            'prePrice',
            'predictPrice',
            'estimatedPrice',
            'freight',
            'freightFee',
            'transportFee',
            'channelFee',
        ] as $field) {
            if (isset($quote[$field]) && is_numeric($quote[$field]) && (float)$quote[$field] > 0) {
                return round((float)$quote[$field], 2);
            }
        }

        $sum = 0.0;
        foreach ([
            'channelFee',
            'serviceCharge',
            'serviceFee',
            'guarantFee',
            'guaranteeFee',
            'incrementFee',
            'otherFee',
        ] as $field) {
            if (isset($quote[$field]) && is_numeric($quote[$field])) {
                $sum += (float)$quote[$field];
            }
        }

        return round($sum, 2);
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
        if (!$this->isProductEnabled($siteId, (string)$params['deliveryType'])) {
            throw new CommonException('所选快递产品未启用，请重新获取报价后下单');
        }
        if (empty($params['thirdOrderNo'])) {
            $params['thirdOrderNo'] = $params['third_order_no']
                ?? (!empty($params['recycle_order_id']) ? 'recycle_' . $siteId . '_' . $params['recycle_order_id'] : 'recycle_express_' . $siteId . '_' . date('YmdHis') . mt_rand(1000, 9999));
        }

        $existingResult = $this->resolveIdempotentCreateResult($siteId, (string)$params['thirdOrderNo']);
        if ($existingResult !== null) {
            return $existingResult;
        }

        $apiData = $this->expressGateway->create($siteId, $params);

        // 创建快递订单记录
        $record = $this->createExpressRecord($siteId, $params, $apiData);
        $this->saveAddressBook($siteId, $params);
        $this->domainEventService->dispatch('express.shipment.created', [
            'site_id' => $siteId,
            'record_id' => (int)$record->id,
            'provider' => (string)($params['provider'] ?? ''),
            'provider_name' => (string)($params['provider_name'] ?? ''),
            'third_order_no' => (string)($params['thirdOrderNo'] ?? ''),
            'order_no' => (string)($apiData['orderNo'] ?? $apiData['orderCode'] ?? ''),
            'delivery_id' => (string)($apiData['deliveryId'] ?? $apiData['waybillNo'] ?? $apiData['trackingNum'] ?? ''),
            'recycle_order_id' => (int)($params['recycle_order_id'] ?? 0),
        ]);

        return $apiData;
    }

    private function resolveIdempotentCreateResult(int $siteId, string $thirdOrderNo): ?array
    {
        if ($thirdOrderNo === '') {
            return null;
        }

        $record = ExpressOrderRecord::where([
            ['site_id', '=', $siteId],
            ['third_order_no', '=', $thirdOrderNo],
            ['order_status', '<>', 'cancelled'],
        ])->order('id desc')->find();
        if (!$record || (empty($record->order_no) && empty($record->delivery_id))) {
            return null;
        }

        return [
            'orderNo' => (string)$record->order_no,
            'deliveryId' => (string)$record->delivery_id,
            'waybillNo' => (string)$record->delivery_id,
            'idempotent' => true,
        ];
    }

    private function saveAddressBook(int $siteId, array $params): void
    {
        try {
            $this->upsertAddressBook($siteId, [
                'address_type' => 'sender',
                'name' => $params['senderName'] ?? '',
                'mobile' => $params['senderMobile'] ?? '',
                'province' => $params['senderProvince'] ?? '',
                'city' => $params['senderCity'] ?? '',
                'district' => $params['senderDistrict'] ?? '',
                'address' => $params['senderAddress'] ?? '',
                'tag' => '最近使用',
            ]);
            $this->upsertAddressBook($siteId, [
                'address_type' => 'receiver',
                'name' => $params['receiveName'] ?? '',
                'mobile' => $params['receiveMobile'] ?? '',
                'province' => $params['receiveProvince'] ?? '',
                'city' => $params['receiveCity'] ?? '',
                'district' => $params['receiveDistrict'] ?? '',
                'address' => $params['receiveAddress'] ?? '',
                'tag' => '最近使用',
            ]);
        } catch (\Throwable $e) {
            Log::error('保存快递常用地址失败: ' . $e->getMessage());
        }
    }

    private function upsertAddressBook(int $siteId, array $data): void
    {
        foreach (['address_type', 'name', 'mobile', 'province', 'city', 'district', 'address'] as $field) {
            if (trim((string)($data[$field] ?? '')) === '') {
                return;
            }
        }

        $record = [
            'site_id' => $siteId,
            'address_type' => (string)$data['address_type'],
            'name' => trim((string)$data['name']),
            'mobile' => trim((string)$data['mobile']),
            'province' => trim((string)$data['province']),
            'city' => trim((string)$data['city']),
            'district' => trim((string)$data['district']),
            'address' => trim((string)$data['address']),
            'tag' => trim((string)($data['tag'] ?? '最近使用')),
            'status' => 1,
        ];

        $exists = ExpressAddressBook::where([
            ['site_id', '=', $siteId],
            ['address_type', '=', $record['address_type']],
            ['mobile', '=', $record['mobile']],
            ['province', '=', $record['province']],
            ['city', '=', $record['city']],
            ['district', '=', $record['district']],
            ['address', '=', $record['address']],
        ])->find();

        if ($exists) {
            $exists->save($record);
            return;
        }

        ExpressAddressBook::create($record);
    }

    /**
     * 创建快递订单记录
     * @param int $siteId
     * @param array $params
     * @param array $apiResult
     * @return ExpressOrderRecord
     */
    private function createExpressRecord(int $siteId, array $params, array $apiResult): ExpressOrderRecord
    {
        $productInfo = $this->findProduct($siteId, (string)$params['deliveryType']) ?: [];

        // 计算体积
        $volume = 0;
        if (!empty($params['vloumLong']) && !empty($params['vloumWidth']) && !empty($params['vloumHeight'])) {
            $volume = ($params['vloumLong'] / 100) * ($params['vloumWidth'] / 100) * ($params['vloumHeight'] / 100);
        }

        // 准备记录数据
        $recordData = [
                'site_id' => $siteId,
                'order_no' => $apiResult['orderNo'] ?? $apiResult['orderCode'] ?? '',
                'third_order_no' => $params['thirdOrderNo'] ?? $params['third_order_no'] ?? '',
                'recycle_order_id' => $params['recycle_order_id'] ?? 0,
                'recycle_device_id' => $params['recycle_device_id'] ?? 0,

                // 快递服务商信息
                'provider_name' => trim((string)($params['provider_name'] ?? '亿速物流')),
                'product_code' => $params['deliveryType'],
                'product_name' => $productInfo['product_name'] ?? '',
                'delivery_id' => $apiResult['deliveryId'] ?? $apiResult['waybillNo'] ?? $apiResult['trackingNum'] ?? '',

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
                'api_response' => array_merge($apiResult, [
                    'thirdOrderNo' => $params['thirdOrderNo'] ?? $params['third_order_no'] ?? '',
                    'provider' => $params['provider'] ?? $params['provider_key'] ?? '',
                ]),
        ];

        $record = ExpressOrderRecord::createRecord($recordData);
        if (!$record) {
            throw new CommonException('快递下单成功，但本地运单记录保存失败，请勿重复下单并联系管理员核对');
        }

        return $record;
    }

    /**
     * 取消快递订单
     * @param int $siteId 站点ID
     * @param string $orderNo 订单号
     * @return bool
     * @throws CommonException
     */
    public function cancelOrder(int $siteId, string $orderNo, string $providerKey = ''): bool
    {
        $record = $this->findLocalExpressRecord($siteId, ['order_no' => $orderNo, 'waybill_no' => $orderNo]);
        $cancelParams = ['order_no' => $orderNo];
        if ($record) {
            $cancelParams = $this->buildCancelIdentifierParams($record, $cancelParams);
        }
        if ($providerKey !== '') {
            $cancelParams['provider'] = $providerKey;
        }

        $this->expressGateway->cancel($siteId, $cancelParams);

        $this->markLocalExpressRecordClosed($siteId, $cancelParams, '用户取消订单');
        $this->domainEventService->dispatch('express.shipment.cancelled', array_merge([
            'site_id' => $siteId,
        ], $cancelParams));

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
        $result = $this->getOrderDetail($siteId, ['delivery_id' => $deliveryId]);

        return $result['trace_list'] ?? $result['traceList'] ?? [];
    }

    /**
     * 查询账户余额
     * @param int $siteId 站点ID
     * @return float
     * @throws CommonException
     */
    public function getBalance(int $siteId): float
    {
        $fund = $this->getFund($siteId);
        return (float)($fund['balance'] ?? 0);
    }

    public function getFund(int $siteId): array
    {
        return $this->expressGateway->account($siteId);
    }

    public function cancelOrInterceptOrder(int $siteId, array $params): bool
    {
        $record = $this->findLocalExpressRecord($siteId, $params);
        if ($record) {
            $params = $this->buildCancelIdentifierParams($record, $params);
        }

        $this->expressGateway->cancel($siteId, $params);

        $remark = ((int)($params['genre'] ?? 1) === 3) ? '已拦截/关闭' : '用户取消订单';
        $this->markLocalExpressRecordClosed($siteId, $params, $remark);
        $this->domainEventService->dispatch('express.shipment.cancelled', array_merge([
            'site_id' => $siteId,
            'remark' => $remark,
        ], $params));

        return true;
    }

    private function buildCancelIdentifierParams(ExpressOrderRecord $record, array $params): array
    {
        $apiResponse = $record->api_response ?? [];
        if (!empty($apiResponse['provider'])) {
            $params['provider'] = $apiResponse['provider'];
        }
        if (!empty($record->order_no)) {
            $params['order_no'] = $record->order_no;
        }
        if (!empty($record->delivery_id)) {
            $params['waybill_no'] = $record->delivery_id;
        }
        if (!empty($apiResponse['thirdOrderNo'])) {
            $params['third_order_no'] = $apiResponse['thirdOrderNo'];
        } elseif (!empty($apiResponse['third_order_no'])) {
            $params['third_order_no'] = $apiResponse['third_order_no'];
        } elseif (empty($params['third_order_no']) && !empty($record->recycle_order_id)) {
            $params['third_order_no'] = 'recycle_' . (int)$record->site_id . '_' . (int)$record->recycle_order_id;
        }

        return $params;
    }

    private function findLocalExpressRecord(int $siteId, array $params): ?ExpressOrderRecord
    {
        $query = ExpressOrderRecord::where('site_id', $siteId);
        $orderNo = (string)($params['order_no'] ?? $params['orderNo'] ?? '');
        if ($orderNo !== '') {
            $record = (clone $query)->where('order_no', $orderNo)->find();
            if ($record) {
                return $record;
            }
        }

        $waybillNo = (string)($params['waybill_no'] ?? $params['waybillNo'] ?? $params['delivery_id'] ?? '');
        if ($waybillNo !== '') {
            $record = (clone $query)->where('delivery_id', $waybillNo)->find();
            if ($record) {
                return $record;
            }
        }

        $thirdOrderNo = (string)($params['third_order_no'] ?? $params['thirdOrderNo'] ?? '');
        if ($thirdOrderNo !== '' && preg_match('/^recycle_(\d+)_(\d+)$/', $thirdOrderNo, $matches)) {
            return (clone $query)
                ->where('site_id', (int)$matches[1])
                ->where('recycle_order_id', (int)$matches[2])
                ->find();
        }

        return null;
    }

    private function markLocalExpressRecordClosed(int $siteId, array $params, string $remark): void
    {
        try {
            $record = $this->findLocalExpressRecord($siteId, $params);
            if (!$record) {
                Log::warning('取消快递成功，但未匹配到本地运单记录', ['site_id' => $siteId, 'params' => $params]);
                return;
            }

            $statusHistory = $record->status_history ?? [];
            $statusHistory[] = [
                'status' => 'cancelled',
                'remark' => $remark,
                'time' => time(),
            ];

            $apiResponse = $record->api_response ?? [];
            $apiResponse['last_cancel'] = [
                'params' => $params,
                'remark' => $remark,
                'time' => time(),
            ];

            $record->save([
                'order_status' => 'cancelled',
                'cancel_reason' => $remark,
                'cancel_time' => time(),
                'status_history' => $statusHistory,
                'api_response' => $apiResponse,
            ]);

            if (!empty($record->recycle_order_id)) {
                RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', (int)$record->recycle_order_id]])->update([
                    'delivery_status' => 4,
                    'delivery_fee' => 0,
                    'update_at' => time(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('更新快递订单记录关闭状态失败: ' . $e->getMessage(), ['site_id' => $siteId, 'params' => $params]);
        }
    }

    public function modifyOrder(int $siteId, array $params): array
    {
        return $this->expressGateway->modify($siteId, $params);
    }

    public function getOrderDetail(int $siteId, array $params): array
    {
        $record = $this->findLocalExpressRecord($siteId, $params);
        if ($record) {
            $params = $this->buildCancelIdentifierParams($record, $params);
        }
        $detail = $this->expressGateway->detail($siteId, $params);
        $this->syncLocalExpressRecordFromDetail($siteId, $params, $detail);

        return $detail;
    }

    private function syncLocalExpressRecordFromDetail(int $siteId, array $params, array $detail): void
    {
        if (empty($detail)) {
            return;
        }

        $identifyParams = $params;
        if (!empty($detail['orderCode'])) {
            $identifyParams['order_no'] = $detail['orderCode'];
        }
        if (!empty($detail['trackingNum'])) {
            $identifyParams['waybill_no'] = $detail['trackingNum'];
        }

        $record = $this->findLocalExpressRecord($siteId, $identifyParams);
        if (!$record) {
            return;
        }

        $status = $this->mapYisuDetailStatus((int)($detail['status'] ?? -1));
        if ($status === '') {
            return;
        }

        $oldStatus = (string)$record->order_status;
        $statusChanged = $oldStatus !== $status;
        if (!$this->canApplyLocalStatus($oldStatus, $status)) {
            $this->dispatchExpressStatusSyncEvent('status_sync_ignored', $siteId, $record, $oldStatus, $status, $detail, [
                'notice' => '第三方状态与本地终态不一致，系统已忽略本次覆盖',
                'source' => 'detail_query',
            ]);
            return;
        }

        $statusName = (string)($detail['statusName'] ?? '查询运单详情同步');
        $statusHistory = $record->status_history ?? [];
        if ($statusChanged) {
            $statusHistory[] = [
                'status' => $status,
                'remark' => '运单详情同步：' . $statusName,
                'time' => time(),
            ];
        }

        $apiResponse = $record->api_response ?? [];
        $apiResponse['last_detail'] = $detail;
        if ($statusChanged) {
            $apiResponse['status_sync_notice'][] = [
                'source' => 'detail_query',
                'old_status' => $oldStatus,
                'new_status' => $status,
                'third_status' => (int)($detail['status'] ?? -1),
                'third_status_name' => $statusName,
                'notice' => '第三方运单状态与本地不一致，已按第三方状态同步',
                'time' => time(),
            ];
        }

        $update = [
            'api_response' => $apiResponse,
        ];
        if ($statusChanged) {
            $update['order_status'] = $status;
            $update['status_history'] = $statusHistory;
        }

        if (!empty($detail['trackingNum'])) {
            $update['delivery_id'] = $detail['trackingNum'];
        }
        if (isset($detail['payFee']) && is_numeric($detail['payFee'])) {
            $update['actual_cost'] = (float)$detail['payFee'];
            $update['cost_diff'] = (float)$detail['payFee'] - (float)$record->estimated_cost;
        }
        if (isset($detail['weightActual']) && is_numeric($detail['weightActual']) && (float)$detail['weightActual'] > 0) {
            $update['actual_weight'] = (float)$detail['weightActual'];
            $update['weight_diff'] = (float)$detail['weightActual'] - (float)$record->estimated_weight;
        }
        if ($statusChanged && $status === 'cancelled') {
            $update['cancel_time'] = time();
            $update['cancel_reason'] = $statusName ?: '已关闭';
        } elseif ($statusChanged && $status === 'delivered') {
            $update['delivery_time'] = time();
        }

        $record->save($update);

        if ($statusChanged && !empty($record->recycle_order_id)) {
            $deliveryStatusMap = [
                'pending' => 1,
                'in_transit' => 2,
                'delivered' => 3,
                'cancelled' => 4,
                'exception' => 2,
            ];
            $orderUpdate = [
                'delivery_status' => $deliveryStatusMap[$status] ?? 1,
                'delivery_fee' => (float)($update['actual_cost'] ?? $record->actual_cost ?? 0),
                'update_at' => time(),
            ];
            if (!empty($update['delivery_id'])) {
                $orderUpdate['express_no'] = $update['delivery_id'];
            }
            RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', (int)$record->recycle_order_id]])->update($orderUpdate);
        }

        if ($statusChanged) {
            $this->dispatchExpressStatusSyncEvent('status_synced', $siteId, $record, $oldStatus, $status, $detail, [
                'notice' => '第三方运单状态与本地不一致，已按第三方状态同步',
                'source' => 'detail_query',
                'update' => $update,
            ]);
        }
    }

    private function dispatchExpressStatusSyncEvent(string $eventType, int $siteId, ExpressOrderRecord $record, string $oldStatus, string $newStatus, array $detail, array $extra = []): void
    {
        $payload = array_merge([
            'site_id' => $siteId,
            'event_type' => $eventType,
            'record_id' => (int)$record->id,
            'order_no' => (string)$record->order_no,
            'delivery_id' => (string)$record->delivery_id,
            'recycle_order_id' => (int)$record->recycle_order_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'third_status' => (int)($detail['status'] ?? -1),
            'third_status_name' => (string)($detail['statusName'] ?? ''),
            'detail' => $detail,
        ], $extra);

        event('RecycleExpressEvent', $payload);
        $this->domainEventService->dispatch('express.shipment.status_changed', $payload);
    }

    private function mapYisuDetailStatus(int $status): string
    {
        $map = [
            0 => 'pending',
            1 => 'pending',
            2 => 'in_transit',
            5 => 'delivered',
            6 => 'cancelled',
            7 => 'cancelled',
            8 => 'cancelled',
            9 => 'cancelled',
        ];

        return $map[$status] ?? '';
    }

    private function canApplyLocalStatus(string $currentStatus, string $incomingStatus): bool
    {
        if ($currentStatus === '') {
            return true;
        }
        if ($currentStatus === 'cancelled') {
            return $incomingStatus === 'cancelled';
        }
        if ($currentStatus === 'delivered') {
            return $incomingStatus === 'delivered';
        }

        return true;
    }

    public function getWaybillPdf(int $siteId, array $params): array
    {
        $record = $this->findLocalExpressRecord($siteId, $params);
        if ($record) {
            $params = $this->buildCancelIdentifierParams($record, $params);
        }
        $data = $this->expressGateway->waybill($siteId, $params);
        $this->recordWaybillPdfResult($siteId, $params, $data);

        return $data;
    }

    private function recordWaybillPdfResult(int $siteId, array $params, array $data): void
    {
        try {
            $record = $this->findLocalExpressRecord($siteId, $params);
            if (!$record) {
                return;
            }

            $apiResponse = $record->api_response ?? [];
            $history = $apiResponse['waybill_pdf_history'] ?? [];
            $history[] = [
                'params' => $params,
                'data_format' => $data['dataFormat'] ?? $data['data_format'] ?? '',
                'has_pdf_data' => !empty($data['pdfData'] ?? $data['url'] ?? $data['pdf_url'] ?? ''),
                'time' => time(),
            ];
            if (count($history) > 20) {
                $history = array_slice($history, -20);
            }

            $apiResponse['last_waybill_pdf'] = end($history);
            $apiResponse['waybill_pdf_history'] = $history;
            $record->save([
                'api_response' => $apiResponse,
                'update_at' => time(),
            ]);

            event('RecycleExpressEvent', [
                'site_id' => $siteId,
                'event_type' => 'waybill_pdf',
                'record_id' => (int)$record->id,
                'order_no' => (string)$record->order_no,
                'delivery_id' => (string)$record->delivery_id,
                'recycle_order_id' => (int)$record->recycle_order_id,
                'detail' => [
                    'params' => $params,
                    'data_format' => $data['dataFormat'] ?? $data['data_format'] ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('记录面单获取结果失败: ' . $e->getMessage(), [
                'site_id' => $siteId,
                'params' => $params,
            ]);
        }
    }
}
