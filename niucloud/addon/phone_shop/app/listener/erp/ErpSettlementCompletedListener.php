<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\order\Order;
use think\facade\Db;
use think\facade\Log;

/**
 * ERP 应收结算回写商城线下挂账进度。
 *
 * ERP 是资金事实中心；商城只保存客户可见的订单进度。事件携带绝对聚合值，
 * 消费端始终覆盖而非累加，因此收款、折账和 Outbox 重试都保持幂等。
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
            $payload = (array)($event['payload'] ?? []);
            $updated = [];
            $recognized = false;
            foreach ((array)($payload['targets'] ?? []) as $target) {
                if (!is_array($target) || (string)($target['target_type'] ?? '') !== 'receivable') continue;
                $origin = (array)($target['origin'] ?? []);
                if ((string)($origin['plugin'] ?? '') !== 'phone_shop') continue;
                $recognized = true;

                $orderId = (int)($origin['id'] ?? 0);
                $orderNo = trim((string)($origin['no'] ?? ''));
                $query = Db::name('phone_shop_order')->where('site_id', '=', $siteId);
                if ($orderId > 0) {
                    $query->where('order_id', '=', $orderId);
                } elseif ($orderNo !== '') {
                    $query->where('order_no', '=', $orderNo);
                } else {
                    continue;
                }
                $order = $query->find();
                if (!$order || (string)($order['payment_mode'] ?? '') !== 'offline_credit') continue;

                $summary = is_array($target['origin_finance'] ?? null)
                    ? (array)$target['origin_finance']
                    : $target;
                $amount = max(0, round((float)($summary['target_amount'] ?? $order['order_money'] ?? 0), 2));
                $settled = max(0, min($amount, round((float)($summary['settled_amount'] ?? 0), 2)));
                $remaining = max(0, round($amount - $settled, 2));
                $isSettled = $remaining <= 0.01;
                $creditStatus = $isSettled ? 'settled' : ($settled > 0 ? 'partial' : 'created');
                $orderUpdate = [
                    'pay_money' => $settled,
                    'credit_status' => $creditStatus,
                    'settle_status' => $isSettled ? 1 : 0,
                    // 线下挂账不存在客户侧原路退款。
                    'is_enable_refund' => 0,
                ];
                if ($isSettled && (int)($order['pay_time'] ?? 0) <= 0) {
                    $orderUpdate['pay_time'] = (int)($payload['confirmed_at'] ?? 0) ?: time();
                }
                Db::name('phone_shop_order')->where([
                    ['site_id', '=', $siteId],
                    ['order_id', '=', (int)$order['order_id']],
                ])->update($orderUpdate);
                Db::name('phone_shop_order_goods')->where([
                    ['site_id', '=', $siteId],
                    ['order_id', '=', (int)$order['order_id']],
                ])->update(['is_enable_refund' => 0]);
                $updated[] = [
                    'order_id' => (int)$order['order_id'],
                    'credit_status' => $creditStatus,
                    'settled_amount' => number_format($settled, 2, '.', ''),
                    'remaining_amount' => number_format($remaining, 2, '.', ''),
                ];
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
}
