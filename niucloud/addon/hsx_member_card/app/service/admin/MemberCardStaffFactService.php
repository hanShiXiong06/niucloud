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
        return [
            'site_id' => (int)$fact['site_id'],
            'event_id' => (string)$fact['event_id'],
            'source_plugin' => 'hsx_member_card',
            'business_chain' => 'member_card',
            'action_key' => (string)$fact['metric_key'],
            'employee_uid' => (int)$fact['staff_uid'],
            'employee_name' => (string)$fact['staff_name'],
            'role_key' => (string)$fact['staff_role'],
            'business_type' => (string)$fact['biz_type'],
            'business_id' => (string)$fact['biz_id'],
            'business_no' => (string)$fact['biz_no'],
            'quantity' => (float)$fact['quantity'] * (int)$fact['direction'],
            'amount' => (float)$fact['amount'] * (int)$fact['direction'],
            'profit' => 0,
            'occurred_at' => (int)$fact['occurred_at'],
            'reversal_of_event_id' => (string)$fact['reversal_of_event_id'],
        ];
    }
}
