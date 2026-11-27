<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
use addon\hsx_recycle\app\service\core\ExpressOrderService;
use core\exception\CommonException;
use think\facade\Log;

/** 回收订单与取件事实的连接层；不改变回收验收、付款等业务状态。 */
class RecyclePickupService
{
    public static function decode($data): array
    {
        if (is_array($data)) return $data;
        $decoded = json_decode((string)$data, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function view(array $order): array
    {
        $data = self::decode($order['delivery_data'] ?? []);
        if (!isset($data['booking_state']) && ($order['delivery_platform'] ?? '') === 'manual') {
            $data['booking_state'] = 'manual';
        } elseif (!isset($data['booking_state']) && !empty($order['delivery_platform'])) {
            $data['booking_state'] = [1 => 'accepted', 2 => 'in_transit', 3 => 'delivered', 4 => 'cancelled'][(int)($order['delivery_status'] ?? 0)] ?? 'unknown';
        }
        return PickupState::view($data, $order);
    }

    public function submit(int $siteId, int $orderId, array $config, int $memberId): array
    {
        $order = $this->ownedOrder($siteId, $orderId, $memberId);
        try {
            (new RecycleExpressService())->createOrder($siteId, $orderId, $config, ['source' => 'user', 'member_id' => $memberId]);
        } catch (\Throwable $e) {
            $record = $this->record($siteId, $orderId);
            if ($record) {
                // 请求已持久化：只相信预约记录，不把外部/落库异常误判成可以重下。
                $this->syncOrder($record);
            } else {
                // 统一服务在任何外部下单前必须先占位，未有占位说明未开始叫件。
                $data = self::decode($order->delivery_data ?? '');
                $data = array_replace($data, ['booking_state' => 'failed']);
                $data['receiver'] = (new RecycleExpressService())->getShopAddress($siteId) ?: [];
                $order->save(['delivery_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'delivery_status' => 0]);
                Log::warning('取件预约未发起', ['site_id' => $siteId, 'order_id' => $orderId, 'reason' => mb_substr($e->getMessage(), 0, 300)]);
            }
        }
        $order->refresh();
        $pickup = $this->view($order->toArray());
        $this->notify($siteId, $orderId, $pickup);
        return $pickup;
    }

    public function record(int $siteId, int $orderId): ?ExpressOrderRecord
    {
        return ExpressOrderRecord::where('site_id', $siteId)->where('recycle_order_id', $orderId)->order('id desc')->find();
    }

    public function applyResult(ExpressOrderRecord $record, array $result): void
    {
        ExpressOperationLock::run((int)$record->site_id, (string)$record->third_order_no, function () use ($record, $result) {
            $record->refresh();
            $before = (array)$record->api_response;
            $data = PickupState::merge($before, $result);
            $state = (string)($data['booking_state'] ?? 'unknown');
            $history = (array)$record->status_history;
            if (($before['booking_state'] ?? '') !== $state
                || (!empty($data['conflict']) && empty($before['conflict']))
                || ($before['conflict_state'] ?? '') !== ($data['conflict_state'] ?? '')) {
                $history[] = ['status' => $state, 'remark' => !empty($data['conflict']) ? '渠道状态冲突，请核实' : '渠道预约状态更新', 'time' => time()];
            }
            $update = ['api_response' => $data, 'order_status' => $state, 'status_history' => $history];
            if (!empty($data['deliveryId'])) {
                $update['delivery_id'] = (string)$data['deliveryId'];
            }
            if (!empty($data['orderNo'])) {
                $update['order_no'] = (string)$data['orderNo'];
            }
            foreach (['actual_cost', 'actual_weight'] as $field) {
                if (isset($result[$field]) && is_numeric($result[$field]) && (float)$result[$field] >= 0) {
                    $update[$field] = (float)$result[$field];
                }
            }
            if (isset($update['actual_cost'])) {
                $update['cost_diff'] = $update['actual_cost'] - (float)$record->estimated_cost;
            }
            if (isset($update['actual_weight'])) {
                $update['weight_diff'] = $update['actual_weight'] - (float)$record->estimated_weight;
            }
            $record->save($update);
            $this->syncOrder($record);
        });
    }

    public function syncOrder(ExpressOrderRecord $record): void
    {
        ExpressOperationLock::run((int)$record->site_id, (string)$record->third_order_no, function () use ($record) {
            $record->refresh();
            $this->syncOrderLocked($record);
        });
    }

    private function syncOrderLocked(ExpressOrderRecord $record): void
    {
        if (!(int)$record->recycle_order_id) {
            return;
        }
        $order = RecycleOrder::where('site_id', (int)$record->site_id)->find((int)$record->recycle_order_id);
        if (!$order) {
            return;
        }
        $current = self::decode($order->delivery_data ?? '');
        $raw = (array)$record->api_response;
        $incoming = array_intersect_key($raw, array_flip(['booking_state', 'carrier_name', 'carrier_code', 'pickup_time',
            'courier_name', 'courier_phone', 'courier_mobile', 'conflict', 'conflict_state', 'highest_booking_state', 'requested_at', 'cancellation']));
        $incoming['record_id'] = (int)$record->id;
        $incoming['attempt_id'] = 'pickup_' . (int)$record->id;
        $incoming['deliveryId'] = (string)$record->delivery_id;
        $incoming['receiver'] = ['contact_name' => (string)$record->receiver_name, 'mobile' => (string)$record->receiver_mobile,
            'province' => (string)$record->receiver_province, 'city' => (string)$record->receiver_city,
            'district' => (string)$record->receiver_district, 'address' => (string)$record->receiver_address];
        $data = PickupState::merge($current, $incoming);
        // 已自行寄件后，原预约晚到的回调只能形成冲突，不能覆盖实际新运单。
        if (($current['booking_state'] ?? '') === 'manual') {
            $data['deliveryId'] = (string)($current['deliveryId'] ?? $order->express_no);
            $data['carrier_name'] = (string)($current['carrier_name'] ?? $order->express_company);
        }
        $state = (string)($data['booking_state'] ?? 'unknown');
        $status = ['confirmed' => 1, 'assigned' => 1, 'picked_up' => 2, 'in_transit' => 2, 'delivered' => 3, 'cancelled' => 4, 'manual' => 1][$state] ?? 0;
        $update = ['delivery_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'delivery_status' => $status,
            'delivery_order_id' => (string)$record->order_no, 'pickup_time' => (string)($data['pickup_time'] ?? ''), 'update_at' => time()];
        if ($state !== 'manual') {
            $update['express_no'] = (string)($data['deliveryId'] ?? '');
            $update['express_company'] = (string)($data['carrier_name'] ?? '');
            $update['delivery_platform'] = (string)($raw['provider'] ?? '');
        }
        $order->save($update);
        $this->notify((int)$record->site_id, (int)$record->recycle_order_id, PickupState::view($data, $order->toArray()));
    }

    public function refresh(int $siteId, int $orderId, int $memberId): array
    {
        $order = $this->ownedOrder($siteId, $orderId, $memberId);
        $record = $this->record($siteId, $orderId);
        if (!$record || ($this->view($order->toArray())['state'] === 'manual')) {
            return $this->view($order->toArray()) + ['refresh_result' => [
                'status' => 'not_required', 'message' => '当前没有需要向渠道核实的预约，请按订单中的寄件方式办理', 'retry_after' => 0,
            ]];
        }
        $refreshResult = ExpressOperationLock::run($siteId, (string)$record->third_order_no, function () use ($record, $siteId) {
            $record->refresh();
            $data = (array)$record->api_response;
            $remaining = 60 - (time() - (int)($data['last_query_at'] ?? 0));
            if ($remaining > 0) {
                // 上次回调可能已经保存渠道事实但订单投影失败，节流也须修复本地展示。
                $this->syncOrder($record);
                return ['status' => 'throttled', 'message' => '已显示当前记录，请稍后再核实渠道状态', 'retry_after' => $remaining];
            }
            $requirements = (new ExpressProviderRegistry())->queryRequirements()[(string)($data['provider'] ?? '')] ?? [];
            $missingIdentifiers = array_filter($requirements, static function (string $field) use ($data): bool {
                return !isset($data[$field]) || !is_scalar($data[$field]) || trim((string)$data[$field]) === '';
            });
            if ($missingIdentifiers) {
                $this->syncOrder($record);
                return ['status' => 'waiting_callback', 'message' => '尚未取得渠道所需的查询标识，请联系门店核实，勿重复叫件', 'retry_after' => 60];
            }
            $data['last_query_at'] = time();
            $record->save(['api_response' => $data]);
            try {
                (new ExpressOrderService())->getOrderDetail($siteId, ['thirdOrderNo' => (string)$record->third_order_no]);
                $record->refresh();
                $data = (array)$record->api_response;
                unset($data['last_query_error']);
                $data['last_query_success_at'] = time();
                $record->save(['api_response' => $data]);
                $result = ['status' => 'updated', 'message' => '已核实渠道状态，请以当前取件安排为准', 'retry_after' => 60];
            } catch (\Throwable $e) {
                $record->refresh();
                $data = (array)$record->api_response;
                $data['last_query_error'] = mb_substr($e->getMessage(), 0, 300);
                $record->save(['api_response' => $data]);
                // 不把上游异常详情和账号信息返回客户，也不能将查询失败当预约失败。
                $result = ['status' => 'unavailable', 'message' => '暂未取得渠道最新状态，保留原预约，请稍后再试或联系门店', 'retry_after' => 60];
            }
            $this->syncOrder($record);
            return $result;
        });
        $order->refresh();
        return $this->view($order->toArray()) + ['refresh_result' => $refreshResult];
    }

    public function manual(int $siteId, int $orderId, int $memberId, array $input): array
    {
        $order = $this->ownedOrder($siteId, $orderId, $memberId);
        $record = $this->record($siteId, $orderId);
        $key = $record ? (string)$record->third_order_no : 'recycle_' . $siteId . '_' . $orderId;
        return ExpressOperationLock::run($siteId, $key, function () use ($order, $input) {
            $order->refresh();
            $view = $this->view($order->toArray());
            if (!$view['can_manual'] || (int)$order->status !== 1) {
                throw new CommonException('当前不能补填寄件；原预约可能仍有效，请联系门店核实');
            }
            $company = trim((string)($input['express_company'] ?? ''));
            $number = trim((string)($input['express_no'] ?? ''));
            if ($company === '' || mb_strlen($company) > 40 || !preg_match('/^[A-Za-z0-9-]{6,50}$/D', $number)) {
                throw new CommonException('请填写实际快递公司和正确运单号（6-50位字母、数字或连字符）');
            }
            $data = self::decode($order->delivery_data ?? '');
            $data['booking_state'] = 'manual';
            $data['carrier_name'] = $company;
            $data['deliveryId'] = $number;
            $data['manual_at'] = time();
            $order->save(['express_company' => $company, 'express_no' => $number, 'delivery_platform' => 'manual',
                'delivery_status' => 1, 'delivery_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'update_at' => time()]);
            return PickupState::view($data, $order->toArray());
        });
    }

    private function ownedOrder(int $siteId, int $orderId, int $memberId): RecycleOrder
    {
        $order = RecycleOrder::where('site_id', $siteId)->where('member_id', $memberId)->where('delete_at', 0)->find($orderId);
        if (!$order) {
            throw new CommonException('订单不存在或无权操作');
        }
        return $order;
    }

    private function notify(int $siteId, int $orderId, array $pickup): void
    {
        if (!empty($pickup['conflict']) || in_array($pickup['cancellation']['state'] ?? '', ['pending', 'unknown', 'manual_review'], true)) {
            return; // 状态冲突不能发“预约成功/取消成功”的确定性通知。
        }
        try {
            (new \addon\hsx_recycle\app\service\core\recycle_order\CoreRecyclePickupNotifyService())->notify($siteId, $orderId, $pickup);
        } catch (\Throwable $e) {
            Log::warning('取件状态已保存，消息通知未完成', ['site_id' => $siteId, 'order_id' => $orderId, 'reason' => mb_substr($e->getMessage(), 0, 200)]);
        }
    }
}
