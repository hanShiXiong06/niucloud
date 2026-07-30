<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\model\MemberCardOutboxEvent;
use addon\hsx_member_card\app\model\MemberCardStaffFact;
use core\base\BaseAdminService;
use think\facade\Db;

/** 本地不可变员工事实 + 可重试绩效事件；失败不阻断开卡和核销主流程。 */
final class MemberCardStaffFactService extends BaseAdminService
{
    public function record(array $data): int
    {
        $eventId = mb_substr(trim((string)($data['event_id'] ?? '')), 0, 80);
        if ($eventId === '' || (int)($data['staff_uid'] ?? 0) <= 0) return 0;
        $existing = MemberCardStaffFact::where([['site_id', '=', $this->site_id], ['event_id', '=', $eventId]])->findOrEmpty();
        if (!$existing->isEmpty()) {
            $this->dispatchByEventId($eventId);
            return (int)$existing->id;
        }

        $factId = 0;
        Db::transaction(function () use ($data, $eventId, &$factId): void {
            $now = time();
            $fact = MemberCardStaffFact::create([
                'site_id' => (int)$this->site_id,
                'event_id' => $eventId,
                'fact_type' => mb_substr((string)($data['fact_type'] ?? ''), 0, 30),
                'biz_type' => mb_substr((string)($data['biz_type'] ?? ''), 0, 30),
                'biz_id' => (int)($data['biz_id'] ?? 0),
                'biz_no' => mb_substr((string)($data['biz_no'] ?? ''), 0, 40),
                'staff_role' => mb_substr((string)($data['staff_role'] ?? ''), 0, 30),
                'staff_uid' => (int)$data['staff_uid'],
                'staff_name' => mb_substr((string)($data['staff_name'] ?? ''), 0, 60),
                'metric_key' => mb_substr((string)($data['metric_key'] ?? ''), 0, 40),
                'quantity' => round((float)($data['quantity'] ?? 1), 2),
                'amount' => round((float)($data['amount'] ?? 0), 2),
                'direction' => (int)($data['direction'] ?? 1) < 0 ? -1 : 1,
                'reversal_of_event_id' => mb_substr((string)($data['reversal_of_event_id'] ?? ''), 0, 80),
                'occurred_at' => max(1, (int)($data['occurred_at'] ?? $now)),
                'create_at' => $now,
            ]);
            $factId = (int)$fact->id;
            $payload = $this->performancePayload($fact->toArray());
            MemberCardOutboxEvent::create([
                'site_id' => (int)$this->site_id,
                'event_id' => $eventId,
                'event_name' => 'HsxPerformanceFactRecorded',
                'aggregate_type' => 'staff_fact',
                'aggregate_id' => $factId,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'pending',
                'attempts' => 0,
                'next_retry_at' => $now,
                'error' => '',
                'occurred_at' => (int)$fact->occurred_at,
                'create_at' => $now,
                'update_at' => $now,
            ]);
        });
        $this->dispatchByEventId($eventId);
        return $factId;
    }

    public function retryPending(int $limit = 50): int
    {
        $rows = MemberCardOutboxEvent::where([
            ['status', 'in', ['pending', 'failed']],
            ['next_retry_at', '<=', time()],
        ])->where('event_name', '=', 'HsxPerformanceFactRecorded')->order('id asc')->limit(max(1, min(200, $limit)))->select()->toArray();
        $done = 0;
        foreach ($rows as $row) if ($this->dispatch((int)$row['id'])) $done++;
        return $done;
    }

    private function dispatchByEventId(string $eventId): void
    {
        $row = MemberCardOutboxEvent::where([['site_id', '=', $this->site_id], ['event_id', '=', $eventId]])->findOrEmpty();
        if (!$row->isEmpty() && (string)$row->status !== 'done') $this->dispatch((int)$row->id);
    }

    private function dispatch(int $id): bool
    {
        $row = MemberCardOutboxEvent::where([['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty() || (string)$row->status === 'done') return true;
        $payload = json_decode((string)$row->payload_json, true);
        if (!is_array($payload)) return $this->fail($row, '绩效事件内容损坏');
        try {
            $row->save(['status' => 'processing', 'attempts' => (int)$row->attempts + 1, 'update_at' => time()]);
            $results = (array)event('HsxPerformanceFactRecorded', $payload);
            foreach ($results as $result) {
                if (is_array($result) && in_array((string)($result['status'] ?? ''), ['processed', 'duplicate'], true)) {
                    $row->save(['status' => 'done', 'error' => '', 'update_at' => time()]);
                    return true;
                }
            }
            return $this->fail($row, '绩效插件未安装或未确认接收，已进入重试队列');
        } catch (\Throwable $e) {
            return $this->fail($row, $e->getMessage());
        }
    }

    private function fail(MemberCardOutboxEvent $row, string $message): bool
    {
        $attempts = max(1, (int)$row->attempts);
        $row->save([
            'status' => 'failed',
            'next_retry_at' => time() + min(3600, 30 * 2 ** min(7, $attempts - 1)),
            'error' => mb_substr(trim($message), 0, 1000),
            'update_at' => time(),
        ]);
        return false;
    }

    private function performancePayload(array $fact): array
    {
        $reversalOfEventId = trim((string)($fact['reversal_of_event_id'] ?? ''));
        $isReversal = $reversalOfEventId !== '';
        $direction = $isReversal ? -1 : 1;
        $metricKey = (string)$fact['metric_key'];
        if ($isReversal) {
            $metricKey = [
                'member_card_issue_cancelled' => 'member_card_issued',
                'member_card_redeem_reversed' => 'member_card_redeemed',
            ][$metricKey] ?? $metricKey;
        }
        $metricMeta = [
            'member_card_issued' => ['name' => '会员卡开卡', 'scope' => 'outcome', 'unit' => 'card'],
            'member_card_redeemed' => ['name' => '会员卡核销服务', 'scope' => 'action', 'unit' => 'service'],
            'member_card_received' => ['name' => '会员卡确认收款', 'scope' => 'action', 'unit' => 'settlement'],
            'member_card_refund_applied' => ['name' => '会员卡退款申请', 'scope' => 'action', 'unit' => 'refund'],
            'member_card_refunded' => ['name' => '会员卡退款完成', 'scope' => 'outcome', 'unit' => 'refund'],
            'member_card_refund_paid' => ['name' => '会员卡退款付款', 'scope' => 'action', 'unit' => 'settlement'],
        ][$metricKey] ?? ['name' => $metricKey, 'scope' => 'action', 'unit' => 'item'];
        // 冲红撤销的是原员工的产出，不归到执行撤销动作的员工名下。
        // 退款申请、退款付款等独立动作虽然资金方向为负，仍是正向工作事实。
        $sourceFact = $fact;
        if ($isReversal) {
            $original = MemberCardStaffFact::where([
                ['site_id', '=', (int)$fact['site_id']],
                ['event_id', '=', $reversalOfEventId],
            ])->findOrEmpty();
            if (!$original->isEmpty()) $sourceFact = $original->toArray();
        }
        return [
            'event_name' => 'performance.fact.recorded.v1',
            'event_version' => 1,
            'site_id' => (int)$fact['site_id'],
            'event_id' => (string)$fact['event_id'],
            'source_plugin' => 'hsx_member_card',
            'business_chain' => 'member_card',
            'metric_key' => $metricKey,
            'metric_name' => $metricMeta['name'],
            'fact_scope' => $metricMeta['scope'],
            'fact_type' => $isReversal ? 'reversal' : 'original',
            'direction' => $direction,
            'employee_uid' => (int)$sourceFact['staff_uid'],
            'employee_name' => (string)$sourceFact['staff_name'],
            'role_key' => (string)$sourceFact['staff_role'],
            'business_type' => (string)$sourceFact['biz_type'],
            'business_id' => (string)$sourceFact['biz_id'],
            'business_no' => (string)$sourceFact['biz_no'],
            'quantity' => (string)$sourceFact['quantity'],
            'amount' => (string)$sourceFact['amount'],
            'profit' => '0.00',
            'unit' => $metricMeta['unit'],
            'occurred_at' => (int)$fact['occurred_at'],
            'reversal_of_event_id' => $reversalOfEventId,
            'source_route' => [
                'app' => 'adminapp',
                'path' => 'addon/hsx_member_card/pages/order/list',
                'query' => ['keyword' => (string)$sourceFact['biz_no']],
            ],
        ];
    }
}
