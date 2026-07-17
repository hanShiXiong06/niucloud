<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardFinanceLink;
use addon\hsx_member_card\app\model\MemberCardInboxEvent;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardOperationLog;
use addon\hsx_member_card\app\model\MemberCardRefund;
use core\base\BaseAdminService;
use think\facade\Db;

/** 消费 ERP 实际结算回调，只根据本插件财务关联更新本地快照。 */
final class MemberCardFinanceSyncService extends BaseAdminService
{
    public function consume(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $eventId = mb_substr(trim((string)($event['event_id'] ?? '')), 0, 100);
        if ($siteId <= 0 || $eventId === '' || (string)($event['event_name'] ?? '') !== 'erp.settlement.completed.v1') {
            return ['consumer' => 'hsx_member_card', 'status' => 'skipped', 'reason' => 'invalid_event'];
        }
        $existing = MemberCardInboxEvent::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
        if (!$existing->isEmpty() && (string)$existing->status === 'processed') {
            return ['consumer' => 'hsx_member_card', 'status' => 'duplicate', 'event_id' => $eventId];
        }

        $updatedOrders = [];
        $updatedRefunds = [];
        try {
            Db::transaction(function () use ($event, $siteId, $eventId, &$updatedOrders, &$updatedRefunds): void {
                $inbox = MemberCardInboxEvent::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->lock(true)->findOrEmpty();
                if ($inbox->isEmpty()) {
                    $inbox = MemberCardInboxEvent::create([
                        'site_id' => $siteId,
                        'event_id' => $eventId,
                        'event_name' => (string)$event['event_name'],
                        'payload_json' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'status' => 'processing',
                        'attempts' => 1,
                        'occurred_at' => (int)($event['occurred_at'] ?? time()),
                        'create_at' => time(),
                        'update_at' => time(),
                    ]);
                } elseif ((string)$inbox->status === 'processed') return;
                else $inbox->save(['status' => 'processing', 'attempts' => (int)$inbox->attempts + 1, 'update_at' => time()]);

                $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
                foreach ((array)($payload['targets'] ?? []) as $target) {
                    if (!is_array($target) || (string)($target['origin']['plugin'] ?? '') !== 'hsx_member_card') continue;
                    $link = MemberCardFinanceLink::where([
                        ['site_id', '=', $siteId],
                        ['target_type', '=', (string)($target['target_type'] ?? '')],
                        ['target_id', '=', (int)($target['target_id'] ?? 0)],
                    ])->lock(true)->findOrEmpty();
                    if ($link->isEmpty()) continue;
                    $settled = max(0, round((float)($target['settled_amount'] ?? 0), 2));
                    $remain = max(0, round((float)($target['remaining_amount'] ?? max(0, (float)$link->amount - $settled)), 2));
                    $financeStatus = (string)($target['finance_status'] ?? ($remain <= 0.0001 ? 'settled' : ($settled > 0 ? 'partial' : 'pending')));
                    $now = time();
                    $link->save([
                        'settled_amount' => $settled,
                        'remaining_amount' => $remain,
                        'finance_status' => $financeStatus,
                        'settlement_no' => (string)($payload['settlement_no'] ?? $link->settlement_no),
                        'last_error' => '',
                        'last_sync_at' => $now,
                        'update_at' => $now,
                    ]);
                    if ((string)$link->biz_type === 'card_sale') {
                        $order = MemberCardOrder::where([['site_id', '=', $siteId], ['id', '=', (int)$link->biz_id]])->lock(true)->findOrEmpty();
                        if ($order->isEmpty()) continue;
                        $order->save([
                            'paid_amount' => $settled,
                            'finance_status' => $financeStatus,
                            'capital_account_id' => (int)($payload['capital_account_id'] ?? $order->capital_account_id),
                            'capital_account_name' => (string)($payload['capital_account_name'] ?? $order->capital_account_name),
                            'update_at' => $now,
                        ]);
                        MemberCard::where([['site_id', '=', $siteId], ['order_id', '=', (int)$order->id]])->update([
                            'finance_status' => $financeStatus,
                            'update_at' => $now,
                        ]);
                        $orderSnapshot = $order->toArray();
                        $orderSnapshot['_event_applied_amount'] = max(0, round((float)($target['applied_amount'] ?? 0), 2));
                        $updatedOrders[(int)$order->id] = $orderSnapshot;
                    } elseif ((string)$link->biz_type === 'card_refund') {
                        $refund = MemberCardRefund::where([['site_id', '=', $siteId], ['id', '=', (int)$link->biz_id]])->lock(true)->findOrEmpty();
                        if ($refund->isEmpty()) continue;
                        $refundStatus = $financeStatus === 'settled' ? 'paid' : ($settled > 0 ? 'pending' : (string)$refund->status);
                        $refund->save([
                            'status' => $refundStatus,
                            'capital_account_id' => (int)($payload['capital_account_id'] ?? $refund->capital_account_id),
                            'capital_account_name' => (string)($payload['capital_account_name'] ?? $refund->capital_account_name),
                            'paid_at' => $financeStatus === 'settled' ? (int)($payload['confirmed_at'] ?? $now) : (int)$refund->paid_at,
                            'last_error' => '',
                            'update_at' => $now,
                        ]);
                        if ($financeStatus === 'settled') {
                            MemberCardOrder::where([['site_id', '=', $siteId], ['id', '=', (int)$refund->order_id]])->update([
                                'business_status' => 'refunded', 'finance_status' => 'refunded',
                                'refunded_amount' => (string)$refund->refund_amount, 'update_at' => $now,
                            ]);
                            MemberCard::where([['site_id', '=', $siteId], ['id', '=', (int)$refund->card_id]])->update([
                                'status' => 'refunded', 'finance_status' => 'refunded', 'update_at' => $now,
                            ]);
                            MemberCardOperationLog::create([
                                'site_id' => $siteId,
                                'biz_type' => 'refund',
                                'biz_id' => (int)$refund->id,
                                'biz_no' => (string)$refund->refund_no,
                                'action' => 'finance_paid',
                                'before_json' => '{}',
                                'after_json' => json_encode([
                                    'status' => 'paid',
                                    'settlement_no' => (string)($payload['settlement_no'] ?? ''),
                                    'capital_account_name' => (string)($payload['capital_account_name'] ?? ''),
                                    'paid_at' => (int)($payload['confirmed_at'] ?? $now),
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                                'operator_uid' => (int)($event['operator']['id'] ?? 0),
                                'operator_name' => (string)($event['operator']['name'] ?? ''),
                                'ip' => '',
                                'occurred_at' => (int)($payload['confirmed_at'] ?? $now),
                                'create_at' => $now,
                            ]);
                        }
                        $refundSnapshot = $refund->toArray();
                        $refundSnapshot['_event_applied_amount'] = max(0, round((float)($target['applied_amount'] ?? 0), 2));
                        $updatedRefunds[(int)$refund->id] = $refundSnapshot;
                    }
                }
                $inbox->save(['status' => 'processed', 'error' => '', 'update_at' => time()]);
            });
        } catch (\Throwable $e) {
            $inbox = MemberCardInboxEvent::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
            if (!$inbox->isEmpty()) $inbox->save(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'update_at' => time()]);
            return ['consumer' => 'hsx_member_card', 'status' => 'failed', 'error' => 1, 'message' => $e->getMessage()];
        }

        $operator = is_array($event['operator'] ?? null) ? $event['operator'] : [];
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        foreach ($updatedOrders as $order) {
            if ((int)($operator['id'] ?? 0) <= 0) continue;
            (new MemberCardStaffFactService())->record([
                'event_id' => 'hsx_member_card:settlement:' . (int)($payload['settlement_id'] ?? 0) . ':order:' . (int)$order['id'] . ':received',
                'fact_type' => 'paid',
                'biz_type' => 'card_order',
                'biz_id' => (int)$order['id'],
                'biz_no' => (string)$order['order_no'],
                'staff_role' => 'receipt_operator',
                'staff_uid' => (int)$operator['id'],
                'staff_name' => (string)($operator['name'] ?? ''),
                'metric_key' => 'member_card_received',
                'quantity' => 1,
                'amount' => (float)($order['_event_applied_amount'] ?? 0),
                'direction' => 1,
                'occurred_at' => (int)($payload['confirmed_at'] ?? time()),
            ]);
        }
        foreach ($updatedRefunds as $refund) {
            if ((string)($refund['status'] ?? '') !== 'paid' || (int)($operator['id'] ?? 0) <= 0) continue;
            (new MemberCardStaffFactService())->record([
                'event_id' => 'hsx_member_card:settlement:' . (int)($payload['settlement_id'] ?? 0) . ':refund:' . (int)$refund['id'] . ':paid',
                'fact_type' => 'refund', 'biz_type' => 'card_refund', 'biz_id' => (int)$refund['id'], 'biz_no' => (string)$refund['refund_no'],
                'staff_role' => 'refund_payment_operator', 'staff_uid' => (int)$operator['id'], 'staff_name' => (string)($operator['name'] ?? ''),
                'metric_key' => 'member_card_refund_paid', 'quantity' => 1, 'amount' => (string)($refund['_event_applied_amount'] ?? 0), 'direction' => -1,
                'occurred_at' => (int)($payload['confirmed_at'] ?? time()),
            ]);
        }
        return [
            'consumer' => 'hsx_member_card', 'status' => 'processed', 'event_id' => $eventId,
            'updated_orders' => array_keys($updatedOrders), 'updated_refunds' => array_keys($updatedRefunds),
        ];
    }
}
