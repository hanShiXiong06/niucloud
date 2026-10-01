<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\admin\order;

use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\service\core\order\CoreOrderEventService;
use addon\phone_shop\app\service\core\order\CoreOrderCloseService;
use addon\phone_shop\app\service\core\order\CoreOrderLogService;
use addon\phone_shop\app\service\core\order\CoreOrderPayService;
use addon\phone_shop\app\service\core\order\OfflineOrderSchemaService;
use app\model\sys\SysUser;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;
use app\service\core\notice\NoticeService;

/**
 * 客户提交线下支付订单后的店内处理。
 *
 * 商城订单负责锁货和客户进度，ERP 负责销售、应收及真实资金事实。
 * 本服务只通过公开事件向 ERP 传递，不直接依赖 ERP 数据表。
 */
class OfflineOrderService extends BaseAdminService
{
    public function capitalAccountOptions(): array
    {
        $responses = (array)event('ErpCapitalAccountOptionsRequested', [
            'event_name' => 'erp.capital_account.options_requested.v1',
            'event_version' => 1,
            'event_id' => 'phone_shop:capital_account_options:' . $this->site_id . ':' . uniqid('', true),
            'site_id' => $this->site_id,
            'source_plugin' => 'phone_shop',
            'occurred_at' => time(),
        ]);
        foreach ($responses as $response) {
            if (is_array($response) && (string)($response['consumer'] ?? '') === 'hsx_erp') {
                return array_values((array)($response['list'] ?? []));
            }
        }
        return [];
    }

    /**
     * @param array{order_id:int,action:string,capital_account_id?:int,deal_total?:float|null,remark?:string,voucher_urls?:array,close_reason?:string} $data
     */
    public function process(array $data): array
    {
        $orderId = (int)($data['order_id'] ?? 0);
        $action = (string)($data['action'] ?? '');
        if ($action === 'return_preview') return (new OfflineOrderReturnService())->preview($orderId);
        if ($action === 'return_received') return (new OfflineOrderReturnService())->confirm($data);
        if (in_array($action, ['batch_delivery', 'batch_finish'], true)) return (new OfflineOrderBatchService())->process($data);
        if ($orderId <= 0 || !in_array($action, ['contacted', 'confirm_paid', 'confirm_credit', 'confirm_delivery', 'reject_voucher', 'close_unreachable'], true)) {
            throw new AdminException('线下订单处理参数不正确');
        }
        OfflineOrderSchemaService::ensure();
        if ($action === 'confirm_delivery') {
            return $this->confirmDelivery($orderId, mb_substr(trim((string)($data['remark'] ?? '')), 0, 255));
        }
        $accountId = (int)($data['capital_account_id'] ?? 0);
        if ($action === 'confirm_paid' && $accountId <= 0) {
            throw new AdminException('确认收款必须选择实际到账账户');
        }
        $remark = mb_substr(trim((string)($data['remark'] ?? '')), 0, 255);
        $voucherUrls = array_values(array_unique(array_filter(array_map(static function ($url): string {
            return mb_substr(trim((string)$url), 0, 500);
        }, (array)($data['voucher_urls'] ?? [])))));
        if (count($voucherUrls) > 6) throw new AdminException('收款凭证最多上传6张');
        $dealTotal = array_key_exists('deal_total', $data) && $data['deal_total'] !== null
            ? round((float)$data['deal_total'], 2)
            : null;
        if ($dealTotal !== null && ($dealTotal <= 0 || $dealTotal > 99999999.99)) {
            throw new AdminException('成交总价必须大于0');
        }

        // 改价必须发生在状态校验之后，避免越权调用把普通在线待支付订单改价。
        $pendingOrder = (new Order())->where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])->findOrEmpty();
        if ($pendingOrder->isEmpty()) throw new AdminException('订单不存在');
        $pendingMode = (string)$pendingOrder['payment_mode'];
        $canRetryCash = $action === 'confirm_paid' && $pendingMode === 'offline_cash';
        if ((int)$pendingOrder['status'] !== OrderDict::WAIT_PAY
            || ($pendingMode !== 'offline_pending' && !$canRetryCash)) {
            throw new AdminException('该订单已处理，请刷新后查看最新状态');
        }

        if ($action === 'contacted') {
            $tradeConfig = (new \addon\phone_shop\app\service\core\order\CoreOrderConfigService())
                ->getOnlineTradeConfig($this->site_id);
            $operator = $this->operatorSnapshot();
            $this->updateWorkflow($orderId, [
                'handler_uid' => $this->uid,
                'handler_name' => $this->username,
                'handler_mobile' => $operator['mobile'],
                'status' => 'contacted',
                'contact_at' => time(),
                'remark' => $remark,
            ]);
            $orderUpdate = ['staff_id' => $this->uid];
            if ((int)($tradeConfig['offline_hold_on_progress'] ?? 1) === 1) $orderUpdate['timeout'] = 0;
            (new Order())->where([['site_id', '=', $this->site_id], ['order_id', '=', $orderId]])
                ->update($orderUpdate);
            (new CoreOrderLogService())->add([
                'order_id' => $orderId, 'status' => OrderDict::WAIT_PAY,
                'main_type' => OrderLogDict::SYSTEM, 'main_id' => $this->uid,
                'type' => OrderDict::ORDER_REMARK_ACTION,
                'content' => ($this->username ?: '业务员') . '已联系客户，等待到店付款',
            ]);
            return ['order_id' => $orderId, 'payment_mode' => 'offline_pending', 'status' => OrderDict::WAIT_PAY, 'next_action' => 'receive'];
        }

        if ($action === 'reject_voucher') {
            $reason = mb_substr(trim((string)($data['close_reason'] ?? $remark)), 0, 500);
            if ($reason === '') throw new AdminException('请填写凭证未通过的原因');
            $timeoutMinutes = (int)((new \addon\phone_shop\app\service\core\order\CoreOrderConfigService())
                ->getOnlineTradeConfig($this->site_id)['offline_timeout_minutes'] ?? 20);
            $this->updateWorkflow($orderId, [
                'handler_uid' => $this->uid, 'handler_name' => $this->username,
                'status' => 'voucher_rejected', 'remark' => $reason,
            ]);
            (new Order())->where([['site_id', '=', $this->site_id], ['order_id', '=', $orderId]])
                ->update(['staff_id' => $this->uid, 'timeout' => time() + max(15, $timeoutMinutes) * 60]);
            NoticeService::send($this->site_id, 'phone_shop_offline_order_status', [
                'order_id' => $orderId, 'status_name' => '付款凭证未通过', 'status_remark' => $reason,
            ]);
            return ['order_id' => $orderId, 'payment_mode' => 'offline_pending', 'status' => OrderDict::WAIT_PAY, 'next_action' => 'resubmit_voucher'];
        }

        if ($action === 'close_unreachable') {
            $reason = mb_substr(trim((string)($data['close_reason'] ?? $remark)), 0, 500);
            if ($reason === '') throw new AdminException('请填写无法联系或关闭订单的原因');
            (new CoreOrderCloseService())->close([
                'site_id' => $this->site_id, 'order_id' => $orderId,
                'main_type' => OrderLogDict::SYSTEM, 'main_id' => $this->uid,
                'close_type' => OrderDict::SHOP_CLOSE, 'close_remark' => $reason,
            ]);
            $this->updateWorkflow($orderId, [
                'handler_uid' => $this->uid, 'handler_name' => $this->username,
                'status' => 'closed', 'close_reason' => $reason, 'remark' => $reason,
            ]);
            return ['order_id' => $orderId, 'payment_mode' => 'offline_pending', 'status' => OrderDict::CLOSE, 'next_action' => 'closed'];
        }

        $priceAdjustment = $this->applyDealTotal($orderId, $dealTotal);

        $orderData = Db::transaction(function () use ($orderId, $action, $remark): array {
            $order = (new Order())->where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $orderId],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) throw new AdminException('订单不存在');
            $paymentMode = (string)$order['payment_mode'];
            $canRetryCash = $action === 'confirm_paid' && $paymentMode === 'offline_cash';
            if ((int)$order['status'] !== OrderDict::WAIT_PAY
                || ($paymentMode !== 'offline_pending' && !$canRetryCash)) {
                throw new AdminException('该订单已处理，请刷新后查看最新状态');
            }

            $isCredit = $action === 'confirm_credit';
            $orderUpdate = [
                'payment_mode' => $isCredit ? 'offline_credit' : 'offline_cash',
                'staff_id' => $this->uid,
                'is_credit' => $isCredit ? 1 : 0,
                'credit_status' => $isCredit ? 'created' : 'settled',
                'settle_status' => $isCredit ? 0 : 1,
                'timeout' => 0,
                'shop_remark' => $remark !== ''
                    ? trim((string)$order['shop_remark'] . "\n" . $remark)
                    : (string)$order['shop_remark'],
            ];
            if ($isCredit) {
                $orderUpdate = array_merge($orderUpdate, [
                    'status' => OrderDict::WAIT_DELIVERY,
                    'pay_money' => 0,
                    'pay_time' => 0,
                    // 线下挂账没有原路线上退款，客户侧不能自行发起退款。
                    'is_enable_refund' => 0,
                ]);
            }
            // 同一个模型连续 save 时，ThinkORM 会在第二次更新中自动补入
            // phone_shop_order 没有框架默认更新时间字段，因此订单状态一次写入。
            $order->save($orderUpdate);
            if ($isCredit) {
                // 固定挂账成交时间；不把尚未到账的订单伪装成已支付。
                $occurredAt = time();
                $lines = (new OrderGoods())->where([
                    ['site_id', '=', $this->site_id], ['order_id', '=', $orderId],
                ])->lock(true)->select();
                foreach ($lines as $line) {
                    $line->save(['extend' => \addon\phone_shop\app\service\core\order\ErpDeviceSnapshot::withSaleTime($line->extend, $occurredAt)]);
                }
                (new OrderGoods())->where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', $orderId],
                ])->update([
                    'delivery_status' => OrderDeliveryDict::WAIT_DELIVERY,
                    'is_enable_refund' => 0,
                ]);
            }
            return $order->toArray();
        });

        if ($action === 'confirm_paid') {
            if (!$voucherUrls) {
                $storedVoucherUrls = OrderOfflineRecord::where([
                    ['site_id', '=', $this->site_id], ['order_id', '=', $orderId],
                ])->value('voucher_urls');
                if (is_string($storedVoucherUrls)) {
                    $decoded = json_decode($storedVoucherUrls, true);
                    $storedVoucherUrls = is_array($decoded) ? $decoded : [];
                }
                $voucherUrls = array_values(array_filter((array)$storedVoucherUrls));
            }
            $operator = $this->operatorSnapshot();
            $this->updateWorkflow($orderId, [
                'handler_uid' => $this->uid, 'handler_name' => $this->username,
                'handler_mobile' => $operator['mobile'],
                'status' => 'paid', 'voucher_urls' => $voucherUrls, 'remark' => $remark,
            ]);
            (new CoreOrderPayService())->pay([
                'site_id' => $this->site_id,
                'trade_id' => $orderId,
                'main_type' => OrderLogDict::SYSTEM,
                'main_id' => $this->uid,
                'capital_account_id' => $accountId,
                'payment_mode' => 'offline_cash',
                'operator_id' => $this->uid,
                'operator_name' => $this->username,
                'remark' => $remark,
                'voucher_urls' => $voucherUrls,
            ]);
            // 商城原生支付完成逻辑会默认开放客户退款入口；线下收款没有线上原路退款，
            // 必须由业务/财务人员在后台处理退货退款，因此在支付事件完成后统一关闭。
            (new Order())->where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $orderId],
            ])->update(['is_enable_refund' => 0]);
            (new OrderGoods())->where([
                ['site_id', '=', $this->site_id],
                ['order_id', '=', $orderId],
            ])->update(['is_enable_refund' => 0]);
            NoticeService::send($this->site_id, 'phone_shop_offline_order_status', [
                'order_id' => $orderId, 'status_name' => '线下付款已确认',
                'status_remark' => '门店已确认收款，订单已进入备货与交付流程。',
            ]);
        } else {
            $operator = $this->operatorSnapshot();
            $this->updateWorkflow($orderId, [
                'handler_uid' => $this->uid, 'handler_name' => $this->username,
                'handler_mobile' => $operator['mobile'],
                'status' => 'credit', 'remark' => $remark,
            ]);
            CoreOrderEventService::orderPay([
                'site_id' => $this->site_id,
                'trade_id' => $orderId,
                'order_id' => $orderId,
                'order_data' => $orderData,
                'main_type' => OrderLogDict::SYSTEM,
                'main_id' => $this->uid,
                'payment_mode' => 'offline_credit',
                'operator_id' => $this->uid,
                'operator_name' => $this->username,
                'remark' => $remark,
            ]);
            (new CoreOrderLogService())->add([
                'order_id' => $orderId,
                'status' => OrderDict::WAIT_DELIVERY,
                'main_type' => OrderLogDict::SYSTEM,
                'main_id' => $this->uid,
                'type' => OrderDict::ORDER_PAY_ACTION,
                'content' => '客户线下挂账，已进入待交付',
            ]);
        }

        return [
            'order_id' => $orderId,
            'payment_mode' => $action === 'confirm_paid' ? 'offline_cash' : 'offline_credit',
            'status' => OrderDict::WAIT_DELIVERY,
            'next_action' => 'delivery',
            'deal_total' => $priceAdjustment['deal_total'],
            'discount_amount' => $priceAdjustment['discount_amount'],
            'allocation' => $priceAdjustment['allocation'],
        ];
    }

    private function updateWorkflow(int $orderId, array $data): void
    {
        $record = OrderOfflineRecord::where([
            ['site_id', '=', $this->site_id], ['order_id', '=', $orderId],
        ])->findOrEmpty();
        $data['update_time'] = time();
        if ($record->isEmpty()) {
            OrderOfflineRecord::create(array_merge([
                'site_id' => $this->site_id, 'order_id' => $orderId,
                'handler_uid' => 0, 'handler_name' => '', 'handler_mobile' => '',
                'status' => 'pending', 'contact_at' => 0, 'voucher_urls' => [],
                'close_reason' => '', 'remark' => '', 'create_time' => time(),
            ], $data));
        } else {
            $record->save($data);
        }
    }

    /**
     * 线下自提的责任人在同一个订单页完成交付。
     *
     * 先调用商城原生门店自提发货，再调用原生完成服务，
     * 以保证商品出库、订单事件、ERP 同步和财务事实仍走统一链路。
     */
    private function confirmDelivery(int $orderId, string $remark): array
    {
        $order = (new Order())->where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])->findOrEmpty();
        if ($order->isEmpty()) throw new AdminException('订单不存在');
        if ((int)$order['status'] !== OrderDict::WAIT_DELIVERY) {
            throw new AdminException('只有已收款或已挂账的待自提订单才能确认交付');
        }
        if ((string)$order['delivery_type'] !== OrderDeliveryDict::STORE) {
            throw new AdminException('该入口仅用于门店自提订单');
        }
        if (!in_array((string)$order['payment_mode'], ['offline_cash', 'offline_credit'], true)) {
            throw new AdminException('该订单尚未完成线下收款或挂账');
        }

        $record = OrderOfflineRecord::where([
            ['site_id', '=', $this->site_id], ['order_id', '=', $orderId],
        ])->findOrEmpty();
        if ($record->isEmpty() || !in_array((string)$record['status'], ['paid', 'credit'], true)) {
            throw new AdminException('线下订单责任记录尚未完成收款或挂账');
        }

        $deliveryLines = (new OrderGoods())->where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])->select()->toArray();
        $orderGoodsIds = array_column(array_filter($deliveryLines, static function ($line) {
            return empty(\addon\phone_shop\app\service\core\order\ErpDeviceSnapshot::decode($line['extend'])['erp_return'])
                && (int)$line['is_gift'] !== 1
                && (string)$line['delivery_status'] === OrderDeliveryDict::WAIT_DELIVERY;
        }), 'order_goods_id');
        if (!$orderGoodsIds) throw new AdminException('订单没有可交付商品');

        (new OrderDeliveryService())->delivery([
            'order_id' => $orderId,
            'delivery_ids' => [],
            'order_goods_ids' => $orderGoodsIds,
            'delivery_type' => OrderDeliveryDict::STORE,
            'remark' => $remark !== '' ? $remark : '线下自提当面交付',
        ]);
        (new OrderFinishService())->finish($orderId);
        $this->updateWorkflow($orderId, [
            'handler_uid' => $this->uid,
            'handler_name' => $this->username,
            'handler_mobile' => $this->operatorSnapshot()['mobile'],
            'status' => 'delivered',
            'remark' => $remark !== '' ? $remark : '已核对客户并完成设备交付',
        ]);

        return [
            'order_id' => $orderId,
            'payment_mode' => (string)$order['payment_mode'],
            'status' => OrderDict::FINISH,
            'next_action' => 'completed',
        ];
    }

    /** 当前实际处理人的公开联系方式快照，避免多人接单后仍显示默认负责人的电话。 */
    private function operatorSnapshot(): array
    {
        $user = (new SysUser())->where('uid', $this->uid)->field('uid,username,real_name,mobile')->findOrEmpty()->toArray();
        return [
            'uid' => (int)($user['uid'] ?? $this->uid),
            'name' => (string)($user['real_name'] ?? $user['username'] ?? $this->username),
            'mobile' => (string)($user['mobile'] ?? ''),
        ];
    }

    /**
     * 将单台议价或多台打包总价按当前设备成交金额比例分摊。
     *
     * 复用商城原生改价服务，使每个 order_goods 的实际成交价成为退款上限，
     * 同时保留原价、改价事件和操作日志。
     */
    private function applyDealTotal(int $orderId, ?float $dealTotal): array
    {
        $order = (new Order())->where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
        ])->findOrEmpty();
        if ($order->isEmpty()) throw new AdminException('订单不存在');

        $currentTotal = round((float)$order['order_money'], 2);
        $dealTotal = $dealTotal ?? $currentTotal;
        $result = [
            'deal_total' => number_format($dealTotal, 2, '.', ''),
            'discount_amount' => number_format($currentTotal - $dealTotal, 2, '.', ''),
            'allocation' => [],
        ];
        if (abs($dealTotal - $currentTotal) < 0.001) return $result;

        $deliveryMoney = round((float)$order['delivery_money'], 2);
        $orderDiscount = round((float)$order['discount_money'], 2);
        $targetGoodsMoney = round($dealTotal - $deliveryMoney + $orderDiscount, 2);
        if ($targetGoodsMoney < 0) {
            throw new AdminException('成交总价不能低于配送费用');
        }

        $goods = (new OrderGoods())->where([
            ['site_id', '=', $this->site_id],
            ['order_id', '=', $orderId],
            ['is_gift', '=', 0],
        ])->order('order_goods_id asc')->select()->toArray();
        if (!$goods) throw new AdminException('订单没有可改价的商品');

        $baseGoodsMoney = array_sum(array_map(static fn(array $item): float => (float)$item['goods_money'], $goods));
        $remaining = $targetGoodsMoney;
        $adjustments = [];
        $lastIndex = count($goods) - 1;
        foreach ($goods as $index => $item) {
            $targetItemGoods = $index === $lastIndex
                ? round($remaining, 2)
                : round($targetGoodsMoney * ($baseGoodsMoney > 0
                    ? (float)$item['goods_money'] / $baseGoodsMoney
                    : 1 / count($goods)), 2);
            $remaining = round($remaining - $targetItemGoods, 2);
            $targetItemNet = round($targetItemGoods - (float)$item['discount_money'], 2);
            if ($targetItemNet < 0) {
                throw new AdminException('打包价过低，无法覆盖商品已有优惠');
            }
            $delta = round($targetItemNet - (float)$item['order_goods_money'], 2);
            $adjustments[(int)$item['order_goods_id']] = ['money' => $delta];
            $result['allocation'][] = [
                'order_goods_id' => (int)$item['order_goods_id'],
                'goods_name' => (string)$item['goods_name'],
                'original_amount' => number_format((float)$item['order_goods_money'], 2, '.', ''),
                'deal_amount' => number_format($targetItemNet, 2, '.', ''),
            ];
        }

        (new OrderService())->editPrice([
            'order_id' => $orderId,
            'delivery_money' => $deliveryMoney,
            'order_goods_data' => $adjustments,
        ]);

        return $result;
    }
}
