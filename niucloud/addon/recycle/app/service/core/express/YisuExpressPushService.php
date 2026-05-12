<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\express;

use addon\recycle\app\model\express\ExpressOrderRecord;
use addon\recycle\app\model\order\RecycleOrder;
use think\facade\Db;
use think\facade\Log;

/**
 * 易速快递回调事件处理
 */
class YisuExpressPushService
{
    public function handle(array $payload): void
    {
        $payload = $this->normalizePayload($payload);
        Log::write('=====亿速OPEN回调信息=====' . date('Y-m-d H:i:s'));
        Log::write($payload);

        $record = $this->findExpressRecord($payload);
        if (!$record) {
            Log::warning('易速推送未匹配到运单记录', ['payload' => $payload]);
            event('RecycleExpressEvent', [
                'event_type' => 'unmatched',
                'payload' => $payload,
            ]);
            return;
        }
        $siteId = (int)$record->site_id;
        $pushType = (int)($payload['pushType'] ?? 0);

        Db::startTrans();
        try {
            $apiResponse = $this->normalizeArray($record->api_response ?? []);
            $oldOrderStatus = (string)$record->order_status;
            $apiResponse['last_push'] = $payload;
            $apiResponse['push_history'][] = [
                'push_type' => $pushType,
                'payload' => $payload,
                'time' => time(),
            ];

            $update = ['api_response' => $apiResponse];
            $eventType = 'unknown';
            $this->applyWaybillNo($record, $payload, $update, $apiResponse);

            if ($pushType === 1) {
                $eventType = 'status_changed';
                $this->applyStatusPush($record, $payload, $update, $apiResponse);
            } elseif ($pushType === 2) {
                $eventType = 'billing_changed';
                $this->applyBillingPush($record, $payload, $update, $apiResponse);
            } elseif ($pushType === 3) {
                $eventType = 'courier_changed';
                $this->applyCourierPush($payload, $update, $apiResponse);
            } elseif ($pushType === 4) {
                $eventType = 'waybill_changed';
                $this->applyOrderChangePush($payload, $update, $apiResponse);
            }

            $update['api_response'] = $apiResponse;
            $record->save($update);
            if (!empty($record->recycle_order_id)) {
                $this->syncRecycleOrderDelivery($siteId, (int)$record->recycle_order_id, $record, $update);
            }

            Db::commit();

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
        } catch (\Throwable $e) {
            Db::rollback();
            Log::write('亿速回调处理异常：' . $e->getMessage());
            throw $e;
        }
    }

    private function normalizePayload(array $payload): array
    {
        if (isset($payload['data']) && is_string($payload['data'])) {
            $payload['data'] = json_decode($payload['data'], true) ?: [];
        }
        if (!isset($payload['data']) || !is_array($payload['data'])) {
            $payload['data'] = [];
        }

        return $payload;
    }

    private function normalizeArray($value): array
    {
        return is_array($value) ? $value : [];
    }

    private function applyWaybillNo(ExpressOrderRecord $record, array $payload, array &$update, array &$apiResponse): void
    {
        $waybillNo = trim((string)($payload['waybillNo'] ?? ''));
        if ($waybillNo !== '' && $waybillNo !== (string)$record->delivery_id) {
            $update['delivery_id'] = $waybillNo;
            $apiResponse['last_waybill_no'] = $waybillNo;
        }
    }

    private function applyStatusPush(ExpressOrderRecord $record, array $payload, array &$update, array &$apiResponse): void
    {
        $data = $payload['data'];
        $status = $this->mapYisuStatus((int)($data['status'] ?? 0));
        if ($status === '') {
            return;
        }

        $remark = (string)($data['desc'] ?? '易速状态推送');
        $apiResponse['status_push'] = [
            'status' => (string)($data['status'] ?? ''),
            'desc' => $remark,
            'time' => time(),
        ];

        if ($this->canApplyStatus((string)$record->order_status, $status)) {
            $update['order_status'] = $status;
            $update = array_merge($update, $this->buildStatusUpdate($record, $status, $remark));
            return;
        }

        $apiResponse['ignored_push'][] = [
            'reason' => 'terminal_status_not_overwritten',
            'current_status' => (string)$record->order_status,
            'incoming_status' => $status,
            'remark' => $remark,
            'time' => time(),
        ];
    }

    private function applyBillingPush(ExpressOrderRecord $record, array $payload, array &$update, array &$apiResponse): void
    {
        $data = $payload['data'];
        $apiResponse['billing'] = [
            'original_fee' => $data['originalFee'] ?? null,
            'weight_final' => $data['weightFinal'] ?? null,
            'weight_fee' => $data['weightFee'] ?? null,
            'volume' => $data['volume'] ?? null,
            'product_code' => $data['productCode'] ?? null,
            'product_name' => $data['productName'] ?? null,
            'if_original_fee' => $data['ifOriginalFee'] ?? null,
            'total_fee' => $data['totalFee'] ?? null,
            'fee_list' => $data['feeList'] ?? [],
            'time' => time(),
        ];

        if (array_key_exists('totalFee', $data) || !empty($data['feeList'])) {
            $actualCost = array_key_exists('totalFee', $data)
                ? (float)$data['totalFee']
                : $this->sumFeeList($data['feeList'] ?? []);
            $update['actual_cost'] = $actualCost;
            $update['cost_diff'] = $actualCost - (float)$record->estimated_cost;
        }

        $actualWeight = (float)($data['weightFinal'] ?? $data['weightFee'] ?? 0);
        if ($actualWeight > 0) {
            $update['actual_weight'] = $actualWeight;
            $update['weight_diff'] = $actualWeight - (float)$record->estimated_weight;
        }

        if (!empty($data['productCode'])) {
            $update['product_code'] = (string)$data['productCode'];
        }
        if (!empty($data['productName'])) {
            $update['product_name'] = (string)$data['productName'];
        }
    }

    private function sumFeeList(array $feeList): float
    {
        $total = 0.0;
        foreach ($feeList as $item) {
            if (!is_array($item)) {
                continue;
            }
            $fee = (float)($item['fee'] ?? 0);
            $total += ((int)($item['type'] ?? 0) === 9) ? -$fee : $fee;
        }

        return round($total, 2);
    }

    private function applyCourierPush(array $payload, array &$update, array &$apiResponse): void
    {
        $data = $payload['data'];
        $apiResponse['courier'] = [
            'courier_info' => (string)($data['courierInfo'] ?? ''),
            'courier_phone' => (string)($data['courierPhone'] ?? ''),
            'time' => time(),
        ];
    }

    private function applyOrderChangePush(array $payload, array &$update, array &$apiResponse): void
    {
        $data = $payload['data'];
        $newWaybillNo = trim((string)($data['newWaybillNo'] ?? ''));
        if ($newWaybillNo === '') {
            return;
        }

        $update['delivery_id'] = $newWaybillNo;
        $apiResponse['new_waybill_no'] = $newWaybillNo;
        $apiResponse['order_change'] = [
            'new_waybill_no' => $newWaybillNo,
            'time' => time(),
        ];
    }

    private function findExpressRecord(array $payload): ?ExpressOrderRecord
    {
        return $this->findExpressRecordByQuery(ExpressOrderRecord::where('id', '>', 0), $payload);
    }

    private function findExpressRecordByQuery($query, array $payload): ?ExpressOrderRecord
    {
        if (!empty($payload['orderNo'])) {
            $record = (clone $query)->where('order_no', $payload['orderNo'])->find();
            if ($record) {
                return $record;
            }
        }
        if (!empty($payload['waybillNo'])) {
            $record = (clone $query)->where('delivery_id', $payload['waybillNo'])->find();
            if ($record) {
                return $record;
            }
        }
        if (!empty($payload['thirdOrderNo'])) {
            $record = (clone $query)->where('third_order_no', $payload['thirdOrderNo'])->find();
            if ($record) {
                return $record;
            }
        }
        if (!empty($payload['thirdOrderNo']) && preg_match('/^recycle_(\d+)_(\d+)$/', (string)$payload['thirdOrderNo'], $matches)) {
            $record = (clone $query)
                ->where('site_id', (int)$matches[1])
                ->where('recycle_order_id', (int)$matches[2])
                ->find();
            if ($record) {
                return $record;
            }
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
