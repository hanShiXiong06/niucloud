<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\core\ExpressOrderService;
use think\facade\Log;

/** 回收取消与渠道取消分开记账；请求意图先随回收订单提交，外部调用在提交后执行。 */
class RecyclePickupCancellationService
{
    public function prepare(array $order): array
    {
        $siteId = (int)$order['site_id'];
        $orderId = (int)$order['id'];
        $data = RecyclePickupService::decode($order['delivery_data'] ?? []);
        $record = (new RecyclePickupService())->record($siteId, $orderId);
        if (($order['delivery_platform'] ?? '') === 'manual' || ($data['booking_state'] ?? '') === 'manual') {
            return ['state' => 'not_required'];
        }
        if (!$record && empty($order['delivery_order_id']) && empty($order['express_no']) && empty($data['record_id'])) {
            return ['state' => 'not_required'];
        }
        $raw = $record ? (array)$record->api_response : [];
        $state = (string)($raw['booking_state'] ?? ($record ? $record->order_status : ($data['booking_state'] ?? 'unknown')));
        if ($state === 'cancelled' && empty($raw['conflict'])) return ['state' => 'confirmed'];
        if ($state === 'failed' && empty($raw['conflict'])) return ['state' => 'not_required'];
        $manual = !$record || !empty($raw['conflict'])
            || in_array($state, ['picked_up', 'in_transit', 'delivered'], true)
            || in_array((string)($raw['highest_booking_state'] ?? ''), ['picked_up', 'in_transit', 'delivered'], true);
        $cancel = ['state' => $manual ? 'manual_review' : 'pending', 'requested_at' => time(),
            'source' => 'recycle_order_cancel', 'message' => $manual
                ? '回收订单已取消，快递需门店核实；已取件的包裹请联系快递拦截或退回。'
                : '回收订单已取消，正在核实原取件预约的取消结果。'];
        $data['cancellation'] = $cancel;
        RecycleOrder::where('site_id', $siteId)->where('id', $orderId)
            ->update(['delivery_data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
        if ($record) {
            $raw['cancellation'] = $cancel;
            $history = (array)$record->status_history;
            $history[] = ['status' => $state, 'remark' => $cancel['message'], 'time' => time()];
            $record->save(['api_response' => $raw, 'status_history' => $history]);
        }
        return $cancel;
    }

    public function complete(int $siteId, int $orderId): array
    {
        $order = RecycleOrder::where('site_id', $siteId)->find($orderId);
        $data = RecyclePickupService::decode($order->delivery_data ?? []);
        $cancel = $data['cancellation'] ?? ['state' => 'not_required'];
        if (($cancel['state'] ?? '') !== 'pending') return $cancel;
        try {
            $record = (new RecyclePickupService())->record($siteId, $orderId);
            if (!$record) throw new \RuntimeException('原预约记录不存在');
            // 原预约号可先于运单号存在，accepted / unknown 也需要核实取消，不使用旧整数状态拦截。
            (new ExpressOrderService())->cancelOrInterceptOrder($siteId, ['third_order_no' => (string)$record->third_order_no]);
            $order->refresh();
            return RecyclePickupService::decode($order->delivery_data ?? [])['cancellation'] ?? ['state' => 'confirmed'];
        } catch (\Throwable $e) {
            // 原因仅留服务日志；对客户不给出密钥/上游原始响应，也不宣称已取消预约。
            Log::error('回收取消后，取件取消待核实', ['site_id' => $siteId, 'order_id' => $orderId, 'reason' => $e->getMessage()]);
            $order->refresh();
            $data = RecyclePickupService::decode($order->delivery_data ?? []);
            if (in_array($data['cancellation']['state'] ?? '', ['confirmed', 'manual_review'], true)) {
                return $data['cancellation'];
            }
            $cancel = array_replace($cancel, ['state' => 'unknown', 'updated_at' => time(),
                'message' => '回收订单已取消，但快递取消尚未确认。请联系门店核实；如快递员联系您，请说明不再寄件。']);
            $data['cancellation'] = $cancel;
            $order->save(['delivery_data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
            return $cancel;
        }
    }
}
