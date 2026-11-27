<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\service\core\order\CoreOrderCreditStateService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * ERP 应收结算回写商城线下挂账进度。
 *
 * ERP 是资金事实中心；事件仅作触发信号，消费时读实时确认结算。
 * 不使用过期事件金额，重复或乱序 Outbox 都不会回退已收进度。
 */
class ErpSettlementCompletedListener
{
    public function handle(array $event): array
    {
        $consumer = 'phone_shop.erp_credit_state';
        if ((string)($event['event_name'] ?? '') !== 'erp.settlement.completed.v1') {
            return ['consumer' => $consumer, 'status' => 'ignored', 'skipped' => true];
        }

        try {
            $siteId = (int)($event['site_id'] ?? 0);
            if ($siteId <= 0) throw new CommonException('ERP结算通知缺少有效站点');
            $payload = (array)($event['payload'] ?? []);
            $updated = [];
            $recognized = false;
            foreach ((array)($payload['targets'] ?? []) as $target) {
                if (!is_array($target) || (string)($target['target_type'] ?? '') !== 'receivable') continue;
                $origin = (array)($target['origin'] ?? []);
                $isMallOrigin = (string)($origin['plugin'] ?? '') === 'phone_shop';
                $mirrorSaleNo = !$isMallOrigin && ($target['source_type'] ?? '') === 'sale'
                    ? trim((string)($target['source_no'] ?? '')) : '';
                if (!$isMallOrigin && $mirrorSaleNo === '') continue;
                $recognized = true;

                $orderId = $isMallOrigin ? (int)($origin['id'] ?? 0) : 0;
                $orderNo = $isMallOrigin ? trim((string)($origin['no'] ?? '')) : '';
                if ($orderId <= 0 && $orderNo === '' && $mirrorSaleNo === '') throw new CommonException('ERP结算通知缺少原商城订单标识');
                $result = Db::transaction(function () use ($siteId, $orderId, $orderNo, $mirrorSaleNo) {
                    $query = Db::name('phone_shop_order')->where('site_id', '=', $siteId);
                    if ($mirrorSaleNo !== '') $query->where('relate_source', '=', $mirrorSaleNo);
                    elseif ($orderId > 0) $query->where('order_id', '=', $orderId);
                    else $query->where('order_no', '=', $orderNo);
                    $orders = $query->lock(true)->select()->toArray();
                    if (count($orders) > 1) throw new CommonException('同一ERP销售单对应多笔商城订单，请先核对，未自动回写');
                    $order = $orders[0] ?? null;
                    if (!$order || (string)($order['payment_mode'] ?? '') !== 'offline_credit') return null;
                    if ($orderNo !== '' && (string)$order['order_no'] !== $orderNo) throw new CommonException('ERP结算通知的原商城订单号不一致');
                    $state = $this->creditStateService()->getState($order, true);
                    if (empty($state['available']) || empty($state['matched']) || in_array($state['status'] ?? '', ['conflict', 'inconsistent'], true)) {
                        throw new CommonException((string)($state['message'] ?? 'ERP收款状态未核实'));
                    }
                    $isSettled = !empty($state['can_complete']);
                    $creditStatus = $isSettled ? 'settled' : ((float)$state['settled_amount'] > 0 ? 'partial' : 'created');
                    $orderUpdate = [
                        'pay_money' => $state['settled_amount'], 'credit_status' => $creditStatus,
                        'settle_status' => $isSettled ? 1 : 0, 'is_enable_refund' => 0,
                    ];
                    if ($isSettled && (int)($order['pay_time'] ?? 0) <= 0 && (int)($state['confirmed_at'] ?? 0) > 0) {
                        $orderUpdate['pay_time'] = (int)$state['confirmed_at'];
                    }
                    Db::name('phone_shop_order')->where('site_id', $siteId)->where('order_id', (int)$order['order_id'])->update($orderUpdate);
                    Db::name('phone_shop_order_goods')->where('site_id', $siteId)->where('order_id', (int)$order['order_id'])->update(['is_enable_refund' => 0]);
                    return [
                        'order_id' => (int)$order['order_id'], 'credit_status' => $creditStatus,
                        'settled_amount' => $state['settled_amount'], 'remaining_amount' => $state['remaining_amount'],
                    ];
                });
                if ($result !== null) $updated[] = $result;
            }

            return [
                'consumer' => $consumer,
                // phone_shop线上订单的人工对账事件无需回写挂账字段，但仍属于
                // 已识别并正确消费，不能让ERP Outbox误判为必需消费者缺失。
                'status' => $recognized ? 'processed' : 'ignored',
                'skipped' => !$recognized,
                'orders' => $updated,
            ];
        } catch (\Throwable $e) {
            Log::error('[phone_shop] ERP结算状态回写商城失败: ' . $e->getMessage());
            return [
                'consumer' => $consumer,
                'status' => 'failed',
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function creditStateService(): CoreOrderCreditStateService
    {
        return new CoreOrderCreditStateService();
    }
}
