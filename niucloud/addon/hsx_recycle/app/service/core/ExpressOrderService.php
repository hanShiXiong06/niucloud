<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core;

use addon\hsx_recycle\app\service\core\express\RecyclePickupService;

use addon\hsx_recycle\app\model\express\ExpressAddressBook;
use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\express\ExpressDomainEventService;
use addon\hsx_recycle\app\service\core\express\ExpressGatewayService;
use addon\hsx_recycle\app\service\core\express\ExpressOperationLock;
use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;
use addon\hsx_recycle\app\service\core\express\PickupState;
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
        $thirdOrderNo = trim((string)($params['thirdOrderNo'] ?? $params['third_order_no'] ?? ''));
        if ($thirdOrderNo === '') {
            $thirdOrderNo = !empty($params['recycle_order_id'])
                ? 'recycle_' . $siteId . '_' . (int)$params['recycle_order_id']
                : 'ex_' . bin2hex(random_bytes(12));
        }
        $params['thirdOrderNo'] = $thirdOrderNo;
        return ExpressOperationLock::run($siteId, $thirdOrderNo, function () use ($siteId, $params) {
            return $this->createLocked($siteId, $params);
        });
    }

    private function createLocked(int $siteId, array $params): array
    {
        // 必须先查原请求：默认配置变化不能让同一次预约被另一渠道重新提交。
        $existingResult = $this->resolveIdempotentCreateResult($siteId, (string)$params['thirdOrderNo']);
        if ($existingResult !== null) {
            return $existingResult;
        }
        $provider = $this->expressGateway->activeProvider($siteId);
        $params['provider'] = $provider['key'];
        $params['provider_name'] = $provider['name'];
        $adapter = (new \addon\hsx_recycle\app\service\core\express\ExpressProviderRegistry())->resolve($siteId, $params['provider']);
        $providerSnapshot = method_exists($adapter, 'prepareSnapshot') ? $adapter->prepareSnapshot($siteId, $params) : [];
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

        // 外部调用前持久化占位。进程中断/响应丢失也不允许再次叫件。
        $record = $this->createExpressRecord($siteId, $params, ['booking_state' => 'submitting']);
        $snapshot = array_replace((array)$record->api_response, $providerSnapshot);
        $snapshot['callback_salt'] = bin2hex(random_bytes(24));
        $snapshot['pickup_time'] = (string)($params['orderSendTime'] ?? '');
        $snapshot['requested_at'] = time();
        $record->save(['api_response' => $snapshot, 'order_status' => 'submitting']);
        // 发送前仍按占位时的路由校验，防止管理员并发切换账号/承运商。
        $params = array_replace($params, $providerSnapshot);
        $params['callback_salt'] = $snapshot['callback_salt'];
        $params['record_id'] = (int)$record->id;
        if (!empty($providerSnapshot['callback_base'])) {
            $base = (string)$providerSnapshot['callback_base'];
            $params['callback_url'] = $base . (strpos($base, '?') === false ? '?' : '&') . 'record_id=' . (int)$record->id;
        }
        try {
            $apiData = $this->expressGateway->create($siteId, $params);
            $apiData['orderNo'] = (string)($apiData['orderNo'] ?? $apiData['orderCode'] ?? '');
            $apiData['deliveryId'] = (string)($apiData['deliveryId'] ?? $apiData['waybillNo'] ?? $apiData['trackingNum'] ?? '');
            $apiData['booking_state'] = $apiData['booking_state'] ?? 'accepted';
            $apiData['record_id'] = (int)$record->id;
            $apiData['provider'] = $params['provider'];
            $record->refresh();
            $merged = PickupState::merge((array)$record->api_response, $apiData);
            $record->save([
                'order_no' => (string)($merged['orderNo'] ?? ''),
                'delivery_id' => (string)($merged['deliveryId'] ?? ''),
                'order_status' => (string)$merged['booking_state'],
                'api_response' => $merged,
            ]);
        } catch (\Throwable $e) {
            // 仅适配器明确证明未创建的拒绝才允许自行寄件；其他错误一律待核实。
            $state = method_exists($e, 'outcome') && $e->outcome() === 'rejected' ? 'failed' : 'unknown';
            $record->refresh();
            $snapshot = (array)$record->api_response;
            if ($e instanceof ExpressSubmissionException) {
                foreach ($e->identifiers() as $field => $value) {
                    // 不用部分响应覆盖回调已经落库的编号，只补齐缺失的查询证据。
                    if (empty($snapshot[$field])) $snapshot[$field] = $value;
                }
            }
            $snapshot = PickupState::merge($snapshot, ['booking_state' => $state]);
            $snapshot['failure_reason'] = mb_substr($e->getMessage(), 0, 500);
            $record->save(['order_status' => $snapshot['booking_state'], 'api_response' => $snapshot,
                'order_no' => (string)($snapshot['orderNo'] ?? '') ?: (string)($record->order_no ?? ''),
                'delivery_id' => (string)($snapshot['deliveryId'] ?? '') ?: (string)($record->delivery_id ?? '')]);
            throw $e;
        }
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
        ])->order('id desc')->find();
        if (!$record) {
            return null;
        }
        $snapshot = (array)$record->api_response;
        unset($snapshot['callback_salt']);
        return array_merge($snapshot, [
            'orderNo' => (string)$record->order_no,
            'deliveryId' => (string)$record->delivery_id,
            'waybillNo' => (string)$record->delivery_id,
            'record_id' => (int)$record->id,
            'booking_state' => (string)($snapshot['booking_state'] ?? 'accepted'),
            'idempotent' => true,
        ]);
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
                'order_status' => (string)($apiResult['booking_state'] ?? 'pending'),
                'status_history' => [
                    [
                        'status' => 'pending',
                        'remark' => '预约请求已记录，等待渠道确认',
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
            throw new CommonException('预约记录保存失败，尚未调用快递公司，请联系管理员');
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
        return $this->cancelOrInterceptOrder($siteId, ['order_no' => $orderNo, 'waybill_no' => $orderNo]);
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
        if (!$record) {
            throw new CommonException('未找到本站原预约记录，不能取消');
        }
        return ExpressOperationLock::run($siteId, (string)$record->third_order_no, function () use ($siteId, $record, $params) {
            $record->refresh();
            $raw = (array)$record->api_response;
            if (!empty($raw['conflict'])) throw new CommonException('原预约存在履约冲突，请先联系快递核实');
            if ((string)$record->order_status === 'cancelled') {
                $this->saveCancellationResult($record, 'confirmed', '快递已确认取消，不会重复发起取消请求');
                return true;
            }
            if ((int)($params['genre'] ?? 1) !== 3 && in_array((string)($raw['highest_booking_state'] ?? $record->order_status), ['picked_up', 'in_transit', 'delivered'], true)) {
                $this->saveCancellationResult($record, 'manual_review', '包裹已取件，请联系快递核实拦截或退回，不能当作未寄件取消');
                throw new CommonException('包裹已取件，请联系快递核实拦截或退回');
            }
            $this->saveCancellationResult($record, 'pending', '正在向原渠道核实取消结果，请勿重新叫件');
            try {
                $params = $this->buildCancelIdentifierParams($record, $params);
                $this->expressGateway->cancel($siteId, $params);
                $remark = ((int)($params['genre'] ?? 1) === 3) ? '已拦截/关闭' : '用户取消订单';
                $this->markLocalExpressRecordClosed($siteId, $params, $remark);
                $record->refresh();
                if (!empty($record->api_response['conflict'])) throw new CommonException('取消与履约状态冲突，请联系渠道核实');
                $this->saveCancellationResult($record, 'confirmed', '快递已确认取消原取件预约');
            } catch (\Throwable $e) {
                $this->saveCancellationResult($record, 'unknown', '取消尚未确认，请核对原预约；可重试原单取消，不能重新叫件');
                throw $e;
            }
            try {
                $this->domainEventService->dispatch('express.shipment.cancelled', array_merge([
                    'site_id' => $siteId, 'remark' => $remark,
                ], $params));
            } catch (\Throwable $e) {
                Log::warning('取件已取消，后续事件未完成', ['site_id' => $siteId, 'record_id' => $record->id]);
            }
            return true;
        });
    }

    private function saveCancellationResult(ExpressOrderRecord $record, string $state, string $message): void
    {
        $record->refresh();
        $raw = (array)$record->api_response;
        $cancel = (array)($raw['cancellation'] ?? []);
        $raw['cancellation'] = array_replace($cancel, ['state' => $state, 'message' => $message,
            'requested_at' => $cancel['requested_at'] ?? time(), 'updated_at' => time()]);
        $history = (array)$record->status_history;
        $history[] = ['status' => (string)$record->order_status, 'remark' => $message, 'time' => time()];
        $record->save(['api_response' => $raw, 'status_history' => $history]);
        (new RecyclePickupService())->syncOrder($record);
    }

    private function buildCancelIdentifierParams(ExpressOrderRecord $record, array $params): array
    {
        $apiResponse = $record->api_response ?? [];
        if (!empty($apiResponse['provider'])) {
            $params['provider'] = $apiResponse['provider'];
        } elseif (in_array((string)$record->provider_name, ['亿速物流', '易速物流', '亿速', '易速'], true)) {
            $params['provider'] = 'yisu';
        } else {
            throw new CommonException('原运单未标明服务商，请联系管理员核实，不会自动使用新渠道');
        }
        foreach (['provider_mode', 'provider_environment', 'provider_task_id', 'provider_account_fingerprint',
            'provider_scene', 'provider_site_id', 'provider_order_id', 'product_code',
            'carrier_code', 'carrier_name', 'service_type', 'payment'] as $key) {
            if (isset($apiResponse[$key])) {
                $params[$key] = $apiResponse[$key];
            }
        }
        $params['orderNo'] = (string)$record->order_no;
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
        if ($thirdOrderNo !== '') {
            $record = (clone $query)->where('third_order_no', $thirdOrderNo)->order('id desc')->find();
            if ($record) {
                return $record;
            }
        }
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
            if (isset($apiResponse['booking_state'])) {
                $record->save(['cancel_reason' => $remark, 'cancel_time' => time(), 'api_response' => $apiResponse]);
                (new \addon\hsx_recycle\app\service\core\express\RecyclePickupService())->applyResult($record, ['booking_state' => 'cancelled']);
                return;
            }

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
                'update_at' => time(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('更新快递订单记录关闭状态失败: ' . $e->getMessage(), ['site_id' => $siteId, 'params' => $params]);
            throw new CommonException('快递公司已受理取消，本地记录暂未更新，请刷新核实，勿重复叫件');
        }
    }

    public function modifyOrder(int $siteId, array $params): array
    {
        $record = $this->findLocalExpressRecord($siteId, $params);
        if (!$record) {
            throw new CommonException('未找到本站预约记录，不能修改');
        }
        $params = $this->buildCancelIdentifierParams($record, $params);
        return $this->expressGateway->modify($siteId, $params);
    }

    public function getOrderDetail(int $siteId, array $params): array
    {
        $record = $this->findLocalExpressRecord($siteId, $params);
        if (!$record) {
            throw new CommonException('未找到本站原预约记录，不能查询');
        }
        $params = $this->buildCancelIdentifierParams($record, $params);
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

        if (isset($detail['booking_state'])) {
            (new \addon\hsx_recycle\app\service\core\express\RecyclePickupService())->applyResult($record, $detail);
            return;
        }

        $status = $this->mapYisuDetailStatus((int)($detail['status'] ?? -1));
        if ($status === '') {
            return;
        }

        // 新预约无论来自哪个渠道都走同一状态合并，避免查询旧渠道时绕过终态保护。
        if (isset($record->api_response['booking_state'])) {
            $state = ['pending' => 'confirmed', 'in_transit' => 'in_transit', 'delivered' => 'delivered',
                'cancelled' => 'cancelled', 'exception' => 'exception'][$status] ?? 'unknown';
            if ($state === 'confirmed' && !empty($detail['courierPhone'])) {
                $state = 'assigned';
            }
            $result = ['booking_state' => $state, 'last_detail' => $detail,
                'orderNo' => (string)($detail['orderCode'] ?? ''),
                'deliveryId' => (string)($detail['trackingNum'] ?? ''),
                'courier_name' => (string)($detail['courierInfo'] ?? ''),
                'courier_phone' => (string)($detail['courierPhone'] ?? '')];
            if (isset($detail['payFee']) && is_numeric($detail['payFee']) && (float)$detail['payFee'] >= 0) {
                $result['actual_cost'] = (float)$detail['payFee'];
            }
            if (isset($detail['weightActual']) && is_numeric($detail['weightActual']) && (float)$detail['weightActual'] > 0) {
                $result['actual_weight'] = (float)$detail['weightActual'];
            }
            (new \addon\hsx_recycle\app\service\core\express\RecyclePickupService())->applyResult($record, $result);
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
