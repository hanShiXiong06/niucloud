<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\express;

use addon\recycle\app\model\express\ExpressOrderRecord;
use addon\recycle\app\model\order\RecycleOrder;
use think\facade\Log;

/**
 * 易速快递回调事件处理
 */
class YisuExpressPushService
{
    public function handle(int $siteId, array $payload): void
    {
        $record = $this->findExpressRecord($siteId, $payload);
        if (!$record) {
            Log::warning('易速推送未匹配到运单记录', ['site_id' => $siteId, 'payload' => $payload]);
            event('RecycleExpressEvent', [
                'site_id' => $siteId,
                'event_type' => 'unmatched',
                'payload' => $payload,
            ]);
            return;
        }
        $siteId = (int)$record->site_id;

        $pushType = (int)($payload['pushType'] ?? 0);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $apiResponse = $record->api_response ?? [];
        $oldOrderStatus = (string)$record->order_status;
        $apiResponse['last_push'] = $payload;
        $apiResponse['push_history'][] = [
            'push_type' => $pushType,
            'payload' => $payload,
            'time' => time(),
        ];

        $update = ['api_response' => $apiResponse];
        $eventType = 'unknown';
        $waybillNo = trim((string)($payload['waybillNo'] ?? ''));
        if ($waybillNo !== '' && $waybillNo !== (string)$record->delivery_id) {
            $update['delivery_id'] = $waybillNo;
            $apiResponse['last_waybill_no'] = $waybillNo;
            $update['api_response'] = $apiResponse;
        }

        if ($pushType === 1) {
            $eventType = 'status_changed';
            $status = $this->mapYisuStatus((int)($data['status'] ?? 0));
            if ($status !== '') {
                $remark = $data['desc'] ?? '易速状态推送';
                if ($this->canApplyStatus((string)$record->order_status, $status)) {
                    $update['order_status'] = $status;
                    $update = array_merge($update, $this->buildStatusUpdate($record, $status, $remark));
                } else {
                    $apiResponse['ignored_push'][] = [
                        'reason' => 'terminal_status_not_overwritten',
                        'current_status' => (string)$record->order_status,
                        'incoming_status' => $status,
                        'remark' => $remark,
                        'time' => time(),
                    ];
                    $update['api_response'] = $apiResponse;
                }
            }
        } elseif ($pushType === 2) {
            $eventType = 'billing_changed';
            $actualCost = (float)($data['totalFee'] ?? 0);
            $actualWeight = (float)($data['weightFinal'] ?? $data['weightFee'] ?? 0);
            if ($actualCost > 0) {
                $update['actual_cost'] = $actualCost;
                $update['cost_diff'] = $actualCost - (float)$record->estimated_cost;
            }
            if ($actualWeight > 0) {
                $update['actual_weight'] = $actualWeight;
                $update['weight_diff'] = $actualWeight - (float)$record->estimated_weight;
            }
        } elseif ($pushType === 3) {
            $eventType = 'courier_changed';
            $apiResponse['courier'] = [
                'courier_info' => (string)($data['courierInfo'] ?? ''),
                'courier_phone' => (string)($data['courierPhone'] ?? ''),
                'time' => time(),
            ];
            $update['api_response'] = $apiResponse;
        } elseif ($pushType === 4) {
            $eventType = 'waybill_changed';
            if (!empty($data['newWaybillNo'])) {
                $update['delivery_id'] = $data['newWaybillNo'];
                $apiResponse['new_waybill_no'] = $data['newWaybillNo'];
                $update['api_response'] = $apiResponse;
            }
        }

        $record->save($update);
        if (!empty($record->recycle_order_id)) {
            $this->syncRecycleOrderDelivery($siteId, (int)$record->recycle_order_id, $record, $update);
        }

        event('RecycleExpressEvent', [
            'site_id' => $siteId,
            'event_type' => $eventType,
            'push_type' => $pushType,
            'record_id' => (int)$record->id,
            'order_no' => $record->order_no,
            'delivery_id' => $update['delivery_id'] ?? $record->delivery_id,
            'recycle_order_id' => (int)$record->recycle_order_id,
            'old_order_status' => $oldOrderStatus,
            'new_order_status' => (string)($update['order_status'] ?? $record->order_status),
            'payload' => $payload,
            'update' => $update,
        ]);
    }

    private function findExpressRecord(int $siteId, array $payload): ?ExpressOrderRecord
    {
        $siteQuery = ExpressOrderRecord::where('site_id', $siteId);
        $record = $this->findExpressRecordByQuery($siteQuery, $payload);
        if ($record) {
            return $record;
        }

        return $this->findExpressRecordByQuery(ExpressOrderRecord::where('id', '>', 0), $payload);
    }

    private function findExpressRecordByQuery($query, array $payload): ?ExpressOrderRecord
    {
        if (!empty($payload['orderNo'])) {
            return (clone $query)->where('order_no', $payload['orderNo'])->find();
        }
        if (!empty($payload['waybillNo'])) {
            return (clone $query)->where('delivery_id', $payload['waybillNo'])->find();
        }
        if (!empty($payload['thirdOrderNo']) && preg_match('/^recycle_(\d+)_(\d+)$/', (string)$payload['thirdOrderNo'], $matches)) {
            return (clone $query)
                ->where('site_id', (int)$matches[1])
                ->where('recycle_order_id', (int)$matches[2])
                ->find();
        }

        return null;
    }

    private function mapYisuStatus(int $status): string
    {
        $map = [
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

    private function canApplyStatus(string $currentStatus, string $incomingStatus): bool
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

    private function buildStatusUpdate(ExpressOrderRecord $record, string $status, string $remark): array
    {
        $statusHistory = $record->status_history ?? [];
        $statusHistory[] = [
            'status' => $status,
            'remark' => $remark,
            'time' => time(),
        ];

        $update = ['status_history' => $statusHistory];
        if ($status === 'picked') {
            $update['pickup_time'] = time();
        } elseif ($status === 'delivered') {
            $update['delivery_time'] = time();
        } elseif ($status === 'cancelled') {
            $update['cancel_time'] = time();
            if ($remark !== '') {
                $update['cancel_reason'] = $remark;
            }
        }

        return $update;
    }

    private function syncRecycleOrderDelivery(int $siteId, int $orderId, ExpressOrderRecord $record, array $update): void
    {
        $deliveryStatusMap = [
            'pending' => 1,
            'in_transit' => 2,
            'delivered' => 3,
            'cancelled' => 4,
            'exception' => 2,
        ];
        $orderUpdate = [
            'update_at' => time(),
        ];
        if (array_key_exists('actual_cost', $update)) {
            $orderUpdate['delivery_fee'] = (float)$update['actual_cost'];
        }
        if (!empty($update['delivery_id'])) {
            $orderUpdate['express_no'] = $update['delivery_id'];
        }
        if (!empty($update['order_status'])) {
            $orderUpdate['delivery_status'] = $deliveryStatusMap[$update['order_status']] ?? 1;
        }

        RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', $orderId]])->update($orderUpdate);
    }
}
