<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\model\order\RecycleNoticeLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use think\facade\Db;
use think\facade\Log;

/** 订单预约状态落库并提交事务后调用；通知失败不影响业务结果。 */
class CoreRecyclePickupNotifyService
{
    private const STATES = [
        'confirmed' => '预约成功', 'assigned' => '已派快递员',
        'failed' => '预约失败', 'cancelled' => '预约取消', 'picked_up' => '已取件',
    ];

    public function notify(int $siteId, int $orderId, array $pickup): void
    {
        $state = $pickup['state'] ?? '';
        if ($siteId <= 0 || $orderId <= 0 || !is_string($state) || !isset(self::STATES[$state])) return;
        try {
            $config = (new PickupNoticeConfigService())->get($siteId);
        } catch (\Throwable $e) {
            $this->failedAttempt($siteId, $orderId, $pickup, 'config', '通知配置读取失败：' . $e->getMessage());
            return;
        }
        $channels = array_values(array_filter(['weapp', 'wechat'], static fn(string $channel): bool => !empty($config[$channel]['enabled'])));
        if (!$channels) {
            $this->failedAttempt($siteId, $orderId, $pickup, 'none', '预约通知未启用或未配置模板，请检查回收快递配置的预约通知设置');
            return;
        }
        // 各渠道独立发送、独立留痕；一渠道未授权不得阻断另一个渠道。
        foreach ($channels as $channel) {
            $logId = 0;
            try {
                $claim = $this->claim($siteId, $orderId, $pickup, $channel);
                if ($claim === null) continue;
                $logId = $claim['id'];
                $response = (new RecyclePickupMessage())->send($siteId, $channel, $claim['payload'], $config[$channel]);
                (new CoreRecycleNoticeLogService())->markSuccess($logId, $response);
            } catch (\Throwable $e) {
                $this->recordFailure($logId, $siteId, $orderId, $channel, $e->getMessage());
            }
        }
    }

    /** 在既有订单行锁内抢占通知日志，发送在事务外，不锁单等待外网。 */
    private function claim(int $siteId, int $orderId, array $pickup, string $channel): ?array
    {
        return Db::transaction(function () use ($siteId, $orderId, $pickup, $channel) {
            $order = RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', $orderId]])
                ->lock(true)->field('id,site_id,member_id,order_no')->findOrEmpty()->toArray();
            if (!$order) throw new \RuntimeException('当前站点回收订单不存在');
            $snapshot = $this->snapshot($pickup);
            // 不使用回调时间/自由描述去重，避免供应商重放时改变描述再次骚扰用户。
            $identity = $snapshot;
            unset($identity['message']);
            $scene = 'pickup:' . $channel . ':' . hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $duplicate = RecycleNoticeLog::where([
                ['site_id', '=', $siteId], ['order_id', '=', $orderId],
                ['notice_key', '=', PickupNoticeConfigService::NOTICE_KEY], ['scene', '=', $scene],
            ])->findOrEmpty();
            // pending/failed 也不自动重发：网络结果未知时，重试可能重复扣订阅次数。
            if (!$duplicate->isEmpty()) return null;
            $payload = $snapshot + [
                'site_id' => $siteId, 'order_id' => $orderId, 'member_id' => (int)$order['member_id'],
                'order_no' => (string)$order['order_no'], 'state_name' => self::STATES[$snapshot['state']],
            ];
            $id = (new CoreRecycleNoticeLogService())->createPending([
                'site_id' => $siteId, 'order_id' => $orderId, 'member_id' => (int)$order['member_id'],
                'order_no' => (string)$order['order_no'], 'notice_key' => PickupNoticeConfigService::NOTICE_KEY,
                'scene' => $scene, 'receiver_type' => 'member', 'receiver_id' => (int)$order['member_id'],
                'target_page' => RecyclePickupMessage::DETAIL_PAGE . '?id=' . $orderId,
                'request_data' => $payload + ['channel' => $channel, 'dedupe_key' => $scene],
            ]);
            return ['id' => $id, 'payload' => $payload];
        });
    }

    private function snapshot(array $pickup): array
    {
        $result = [];
        foreach (['state', 'carrier_name', 'pickup_time', 'courier_name', 'courier_phone', 'tracking_no', 'message'] as $key) {
            $value = is_scalar($pickup[$key] ?? '') ? (string)($pickup[$key] ?? '') : '';
            $result[$key] = mb_substr(trim((string)preg_replace('/\s+/u', ' ', $value)), 0, 500);
        }
        // 新预约尝试可以携带稳定 ID；即使状态/时段相同也不误判为旧预约。
        foreach (['provider_order_no', 'attempt_id'] as $key) {
            if (isset($pickup[$key]) && is_scalar($pickup[$key])) $result[$key] = (string)$pickup[$key];
        }
        if ($result['message'] === '') $result['message'] = '预约取件状态已更新，请查看订单详情。';
        return $result;
    }

    private function failedAttempt(int $siteId, int $orderId, array $pickup, string $channel, string $reason): void
    {
        try {
            $claim = $this->claim($siteId, $orderId, $pickup, $channel);
            if ($claim !== null) $this->recordFailure($claim['id'], $siteId, $orderId, $channel, $reason);
        } catch (\Throwable $e) {
            $this->recordFailure(0, $siteId, $orderId, $channel, $e->getMessage());
        }
    }

    private function recordFailure(int $logId, int $siteId, int $orderId, string $channel, string $reason): void
    {
        try {
            if ($logId > 0) (new CoreRecycleNoticeLogService())->markFail($logId, $reason, [
                'channel' => $channel, 'send_accepted' => false, 'customer_read' => false,
                'message' => $reason,
            ]);
        } catch (\Throwable $ignored) {
            // 日志表故障也不能改变已经落库的预约状态。
        }
        try { Log::error('【回收预约通知】' . $reason, ['site_id' => $siteId, 'order_id' => $orderId, 'channel' => $channel]); }
        catch (\Throwable $ignored) {}
    }
}
