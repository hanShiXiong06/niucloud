<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\notice\PickupNoticeTemplate;
use addon\hsx_recycle\app\model\order\RecycleNoticeLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use app\service\core\notice\NoticeService;
use think\facade\Db;
use think\facade\Log;

/** 订单预约状态落库并提交事务后调用；通知失败不影响业务结果。 */
class CoreRecyclePickupNotifyService
{
    public function notify(int $siteId, int $orderId, array $pickup): void
    {
        $state = $pickup['state'] ?? '';
        if ($siteId <= 0 || $orderId <= 0 || !is_string($state) || !isset(PickupNoticeTemplate::STATES[$state])) return;
        $logId = 0;
        try {
            $claim = $this->claim($siteId, $orderId, $pickup);
            if ($claim === null) return;
            $logId = $claim['id'];
            $config = (new PickupNoticeConfigService())->get($siteId);
            $channels = array_values(array_filter(['weapp', 'wechat'], static fn(string $channel): bool => !empty($config[$channel]['enabled'])));
            if (!$channels) {
                (new CoreRecycleNoticeLogService())->markSkipped($logId, '未在框架消息管理开启预约通知，本次未发送');
                return;
            }
            foreach ($channels as $channel) {
                if (empty($config[$channel]['ready'])) {
                    throw new \RuntimeException(($channel === 'weapp' ? '小程序：' : '公众号：') . implode('；', $config[$channel]['missing']));
                }
            }
            // 一个业务事件只提交一次，收件人由 NoticeData 校验，各渠道由框架 Notice 监听分发。
            $result = NoticeService::send($siteId, PickupNoticeTemplate::KEY, $claim['payload']);
            if ($result === false || $result === null) throw new \RuntimeException('框架通知未接受任务，请检查消息模板和队列');
            // 框架的 true/任务 ID 不是微信回执，不能标为“发送成功”或“已读”。
            (new CoreRecycleNoticeLogService())->markDispatched($logId, [
                'dispatch_submitted' => true, 'send_accepted' => null, 'customer_read' => null,
                'channels' => $channels, 'message' => '已交给通知框架；请查看框架通知记录，客户送达结果未确认。',
            ]);
        } catch (\Throwable $e) {
            $this->recordFailure($logId, $siteId, $orderId, $e->getMessage());
        }
    }

    /** 在既有订单行锁内抢占通知日志，发送在事务外，不锁单等待外网。 */
    private function claim(int $siteId, int $orderId, array $pickup): ?array
    {
        return Db::transaction(function () use ($siteId, $orderId, $pickup) {
            $order = RecycleOrder::where([['site_id', '=', $siteId], ['id', '=', $orderId]])
                ->lock(true)->field('id,site_id,member_id,order_no')->findOrEmpty()->toArray();
            if (!$order) throw new \RuntimeException('当前站点回收订单不存在');
            if ((int)$order['member_id'] <= 0) throw new \RuntimeException('订单未关联会员，无法发送客户通知');
            $snapshot = $this->snapshot($pickup);
            // 不使用回调时间/自由描述去重，避免供应商重放时改变描述再次骚扰用户。
            $identity = $snapshot;
            unset($identity['message']);
            $scene = 'pickup:notice:' . hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $duplicate = RecycleNoticeLog::where([
                ['site_id', '=', $siteId], ['order_id', '=', $orderId],
                ['notice_key', '=', PickupNoticeTemplate::KEY], ['scene', '=', $scene],
            ])->findOrEmpty();
            // pending/failed 也不自动重发：网络结果未知时，重试可能重复扣订阅次数。
            if (!$duplicate->isEmpty()) return null;
            $payload = $snapshot + [
                'site_id' => $siteId, 'order_id' => $orderId, 'member_id' => (int)$order['member_id'],
                'order_no' => (string)$order['order_no'], 'state_name' => PickupNoticeTemplate::STATES[$snapshot['state']],
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $id = (new CoreRecycleNoticeLogService())->createPending([
                'site_id' => $siteId, 'order_id' => $orderId, 'member_id' => (int)$order['member_id'],
                'order_no' => (string)$order['order_no'], 'notice_key' => PickupNoticeTemplate::KEY,
                'scene' => $scene, 'receiver_type' => 'member', 'receiver_id' => (int)$order['member_id'],
                'target_page' => PickupNoticeTemplate::DETAIL_PAGE . '?id=' . $orderId,
                'request_data' => $payload + ['transport' => 'notice', 'dedupe_key' => $scene],
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

    private function recordFailure(int $logId, int $siteId, int $orderId, string $reason): void
    {
        try {
            if ($logId > 0) (new CoreRecycleNoticeLogService())->markFail($logId, $reason, [
                'transport' => 'notice', 'send_accepted' => null, 'customer_read' => null,
                'message' => $reason,
            ]);
        } catch (\Throwable $ignored) {
            // 日志表故障也不能改变已经落库的预约状态。
        }
        try { Log::error('【回收预约通知】' . $reason, ['site_id' => $siteId, 'order_id' => $orderId]); }
        catch (\Throwable $ignored) {}
    }
}
