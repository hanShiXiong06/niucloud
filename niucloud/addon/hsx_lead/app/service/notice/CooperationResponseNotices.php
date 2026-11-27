<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

use addon\hsx_lead\app\service\{NoticePortalEntry,NoticePreflightException};
use addon\hsx_lead\app\service\cooperation\{CooperationNoticeService,Store,TeamService};
use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\job\DispatchNoticeTask;
use think\facade\{Db,Cache,Log};

/** Source adapter and planner; deadlines are reminders, never an automatic reassignment policy. */
final class CooperationResponseNotices
{
    public const REMINDER = 'cooperation_acceptance_reminder';
    public const OVERDUE = 'cooperation_response_overdue';
    public const ACCEPTED = 'cooperation_assignment_accepted';
    public static function plan(int $site, int $version, int $assignedAt, int $deadline, int $publisherMember): array
    {
        if (!AccountNoticePolicyService::installed($site))return [];
        $policy = AccountNoticePolicyService::raw($site);
        if (empty($policy['enabled']))return [];
        $events = [];
        foreach ([self::REMINDER, self::OVERDUE, self::ACCEPTED] as $key)if (!empty($policy['events'][$key]['enabled'])) {
            $channels = array_keys(array_filter($policy['events'][$key]['channels'] ?? []));
            if ($channels)$events[$key] = $channels;
        }
        if (!$events)return [];
        return ['version' => $version, 'assigned_at' => $assignedAt, 'deadline' => $deadline, 'publisher_member_id' => $publisherMember,
            'nodes' => $policy['response_reminder_minutes'], 'events' => $events];
    }
    /** Called after the business row is locked and before its acceptance update commits. */
    public static function acceptedFact(int $site, array $plan, int $acceptedAt, array $lead): array
    {
        $policy = AccountNoticePolicyService::raw($site);
        $channels = array_keys(array_filter($policy['events'][self::ACCEPTED]['channels'] ?? []));
        $typed = AccountNoticePolicyService::installed($site)
            && !empty($policy['enabled'])
            && !empty($policy['events'][self::ACCEPTED]['enabled'])
            && $channels !== [];
        if (!$plan && !$typed)return [];
        if (!$plan) {
            $rule = Data::decode($lead['rule_json'] ?? '');
            $member = (int) ($rule['publisher_member_id'] ?? 0);
            if ($member <= 0)$member = (int) Data::row('merchant', $site, (int) $lead['publisher_id'])['member_id'];
            $plan = [
                'version' => (int) $lead['assignment_version'],
                'assigned_at' => (int) $lead['create_time'],
                'deadline' => (int) $lead['response_deadline'],
                'publisher_member_id' => $member,
                'nodes' => [],
                'events' => []
            ];
        }
        if ($typed)$plan['events'][self::ACCEPTED] = $channels;
        $plan['acceptance'] = ['accepted_at' => $acceptedAt, 'typed' => $typed, 'recordable' => $typed];
        return $plan;
    }
    public static function typedAccept(array $lead): bool
    {
        $plan = Data::decode($lead['rule_json'] ?? '')['response_notice_plan'] ?? [];
        return (int) ($plan['version'] ?? 0) === (int) $lead['assignment_version'] && !empty($plan['acceptance']['typed']);
    }
    public static function recordCurrent(int $site, int $id): int
    {
        return Db::transaction(function () use ($site, $id) {
            $lead = Store::row('collaboration', $site, $id, true);
            $rule = Data::decode($lead['rule_json'] ?? '');
            $plan = $rule['response_notice_plan'] ?? [];
            if (!is_array($plan) || (int) ($plan['version'] ?? 0) !== (int) $lead['assignment_version'] || empty($plan['assigned_at']))return 0;
            $policy = AccountNoticePolicyService::raw($site);
            if (empty($policy['enabled']) || !AccountNoticePolicyService::installed($site))return 0;
            $deadline = (int) $lead['response_deadline'];
            $count = 0;
            if ($lead['status'] === 'assigned' && (int) $lead['accepted_at'] === 0 && $deadline > 0 && $deadline === (int) ($plan['deadline'] ?? 0)) {
                $nodes = array_values(array_filter($plan['nodes'] ?? [], static fn ($n) => is_int($n) && $n > 0));
                rsort($nodes, SORT_NUMERIC);
                foreach ($nodes as $index => $node) {
                    $due = $deadline - $node * 60;
                    $expires = isset($nodes[$index + 1]) ? $deadline - $nodes[$index + 1] * 60 : $deadline;
                    // Assignment already has its own immediate notification. Never send the same 30-minute initial reminder again.
                    if ($due <= (int) $plan['assigned_at'] || $expires <= time())continue;
                    $count += self::record($lead, $plan, self::REMINDER, 'receiver', $node, $due, $expires, $policy);
                }
                $count += self::record($lead, $plan, self::OVERDUE, 'publisher', 0, $deadline, $deadline + (int) $policy['ttl_seconds'], $policy);
            }
            if ((int) $lead['accepted_at'] > 0 && in_array($lead['status'], ['following', 'unpaid', 'paid', 'no_deal'], true)
                && !empty($plan['acceptance']['recordable']) && (int) $plan['acceptance']['accepted_at'] === (int) $lead['accepted_at'])
                    $count += self::record($lead, $plan, self::ACCEPTED, 'publisher', 0, (int) $lead['accepted_at'], (int) $lead['accepted_at'] + (int) $policy['ttl_seconds'], $policy);
            return $count;
        });
    }
    private static function record(array $lead, array $plan, string $event, string $party, int $node, int $due, int $expires, array $policy): int
    {
        if ($expires <= time() || empty($policy['events'][$event]['enabled']))return 0;
        $site = (int) $lead['site_id'];
        $count = 0;
        foreach (self::recipients($site, $lead, $plan, $party) as $member => $identity)foreach ($plan['events'][$event] ?? [] as $channel) {
            if (empty($policy['events'][$event]['channels'][$channel]))continue;
            $snapshot = ['site_id' => $site, 'lead_id' => (int) $lead['id'], 'assignment_version' => (int) $lead['assignment_version'],
                'publisher_id' => (int) $lead['publisher_id'], 'receiver_id' => (int) $lead['receiver_id'], 'recipient_member_id' => (int) $member,
                'merchant_id' => $identity['merchant_id'], 'recipient_role' => $identity['role'], 'party' => $party, 'response_deadline' => (int) $lead['response_deadline'],
                'node_minutes' => $node, 'remaining_minutes' => $node, 'planned_at' => $due, 'occurred_at' => $event === self::ACCEPTED ? (int) $lead['accepted_at'] : $due,
                'accepted_at' => $event === self::ACCEPTED ? (int) $lead['accepted_at'] : 0, 'task_no' => $lead['lead_no'],
                'store_name' => (string) Db::name('hsx_lead_merchant')->where('site_id', $site)->where('id', $lead['receiver_id'])->value('name'),
                'notice_remark' => self::remark($event)];
            $count += NoticeTaskRuntime::recordIntent($site, 'collaboration_assignment', (int) $lead['id'], (int) $member, $event, $channel, $snapshot, $due, $expires, (string) $lead['assignment_version'].'|'.$node);
        }return $count;
    }
    private static function recipients(int $site, array $lead, array $plan, string $party): array
    {
        $merchantId = (int) $lead[$party === 'publisher' ? 'publisher_id' : 'receiver_id'];
        if ($merchantId <= 0)return [];
        $merchant = Data::row('merchant', $site, $merchantId);
        $internal = TeamService::internalAssignment($lead, true);
        $result = [];
        if ($party === 'publisher') {
            $member = $internal !== null ? (int) $internal['publisher_member_id'] : (int) ($plan['publisher_member_id'] ?? 0);
            $role = $member === (int) $merchant['member_id'] ? 'owner' : 'employee';
            if (CooperationNoticeService::recipient($site, $merchant, $member, $role))$result[$member] = ['merchant_id' => $merchantId, 'role' => $role];
            return $result;
        }
        $ids = $internal !== null ? [(int) $internal['assignee_member_id'] => 'employee'] : CooperationNoticeService::recipientIds($site, $merchant);
        foreach ($ids as $member => $role) {
            if ((int) $member === (int) $merchant['member_id'])$role = 'owner';
            if (CooperationNoticeService::recipient($site, $merchant, (int) $member, $role))$result[(int) $member] = ['merchant_id' => $merchantId, 'role' => $role];
        }
        return $result;
    }
    public static function invalidatePending(int $site, int $leadId): void
    {
        Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('source_type', 'collaboration_assignment')->where('source_id', $leadId)
            ->whereIn('event_key', [self::REMINDER, self::OVERDUE])->whereIn('status', [
                    'pending',
                    'retry_wait',
                    'pending_configuration',
                    'pending_recipient',
                    'pending_authorization',
                    'paused',
                    'rejected',
                    'exhausted'
                ])
            ->update(['status' => 'obsolete', 'last_message' => '交付已处理或已更换，不再发送原提醒', 'update_time' => time()]);
    }
    public static function enqueue(int $site, int $id): void
    {
        if (NoticeTaskRuntime::inTransaction() || !filter_var(env('queue.state', false), FILTER_VALIDATE_BOOLEAN))return;
        try {
            foreach (Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('source_type', 'collaboration_assignment')->where('source_id', $id)->where('status', 'pending')->field('id,next_retry_at')->select()->toArray() as $task)
                DispatchNoticeTask::dispatch([['site_id' => $site, 'id' => (int) $task['id']]], [], max(0, (int) $task['next_retry_at'] - time()));
        } catch (\Throwable $error) {
            Log::warning('接单提醒已保留，延时队列待计划任务补偿', ['site_id' => $site, 'lead_id' => $id, 'error_type' => get_class($error)]);
        }
    }
    public static function recoverSite(int $site, int $limit = 50): int
    {
        $scope = (string) Db::connect()->getConfig('database').':'.(string) Db::connect()->getConfig('prefix');
        $cache = 'lead_response_scan_'.hash('sha256', $scope.':'.$site);
        $cursor = (int) Cache::get($cache, 0);
        $cutoff = time() - (int) AccountNoticePolicyService::raw($site)['ttl_seconds'];
        // Only this release's explicit plan is recoverable. SQL excludes old unplanned/history rows before any source lock.
        // Existing collaboration indexes do not cover the JSON marker/deadline, so LIMIT bounds returned candidates, not all examined rows.
        $rows = Db::name('hsx_lead_collaboration')->where('site_id', $site)->where('id', '>', $cursor)->whereIn('status', [
                'assigned',
                'following',
                'unpaid',
                'paid',
                'no_deal'
            ])
            ->whereLike('rule_json', '%"response_notice_plan":{%')->where(function ($q) use ($cutoff) {$q->where('accepted_at', '>=', $cutoff)->whereOr(function ($pending) use ($cutoff) {$pending->where('status', 'assigned')->where('response_deadline', '>=', $cutoff);});})
            ->order('id')->limit(max(1, min(200, $limit)))->column('id');
        $count = 0;
        foreach ($rows as $id)$count += self::recordCurrent($site, (int) $id);
        Cache::set($cache, count($rows) < $limit ? 0 : (int) end($rows), 3600);
        return $count;
    }
    public static function verify(array $task): array
    {
        $snapshot = Data::decode($task['payload_json'] ?? '');
        $lead = Store::query('collaboration', (int) $task['site_id'])->where('id', $task['source_id'])->find();
        if (!$lead || (int) ($snapshot['site_id'] ?? 0) !== (int) $task['site_id'] || (int) ($snapshot['lead_id'] ?? 0) !== (int) $task['source_id']
            || (int) ($snapshot['assignment_version'] ?? 0) !== (int) $lead['assignment_version']
                || (int) ($snapshot['receiver_id'] ?? 0) !== (int) $lead['receiver_id']
                || (int) ($snapshot['publisher_id'] ?? 0) !== (int) $lead['publisher_id'])throw new ObsoleteNoticeException('原交付已变化');
        $plan = Data::decode($lead['rule_json'] ?? '')['response_notice_plan'] ?? [];
        if ((int) ($plan['version'] ?? 0) !== (int) $lead['assignment_version'] || empty($plan['events'][$task['event_key']])
            || !in_array($task['channel'], $plan['events'][$task['event_key']], true))throw new ObsoleteNoticeException('原通知计划不存在');
        if ($task['event_key'] === self::ACCEPTED) {
            if (empty($plan['acceptance']['recordable'])
                || (int) $lead['accepted_at'] <= 0
                || (int) $lead['accepted_at'] !== (int) ($snapshot['accepted_at'] ?? 0)
                || !in_array($lead['status'], ['following', 'unpaid', 'paid', 'no_deal'], true))throw new ObsoleteNoticeException('接单事实未确认');
        } elseif ($lead['status'] !== 'assigned'
            || (int) $lead['accepted_at'] !== 0
            || (int) $lead['response_deadline'] !== (int) ($snapshot['response_deadline'] ?? 0))throw new ObsoleteNoticeException('原交付已处理');
        elseif ($task['event_key'] === self::REMINDER
            && ((int) $snapshot['planned_at'] > time() || (int) $task['expires_at'] <= time() || (int) $lead['response_deadline'] <= time()))throw new ObsoleteNoticeException('原提醒节点已过时');
        elseif ($task['event_key'] === self::OVERDUE && (int) $lead['response_deadline'] > time())throw new NoticePreflightException('尚未到超时提醒时刻');
        $party = $task['event_key'] === self::REMINDER ? 'receiver' : 'publisher';
        $recipients = self::recipients((int) $task['site_id'], $lead, $plan, $party);
        $recipient = $recipients[(int) $task['recipient_member_id']] ?? null;
        if (!$recipient
            || (int) $recipient['merchant_id'] !== (int) ($snapshot['merchant_id'] ?? 0)
            || $recipient['role'] !== ($snapshot['recipient_role'] ?? ''))throw new ObsoleteNoticeException('接收成员已变化');
        if ((int) ($snapshot['recipient_member_id'] ?? 0) !== (int) $task['recipient_member_id'])throw new NoticePreflightException('通知接收会员快照不一致');
        return $lead;
    }
    public static function variables(array $task, array $lead): array
    {
        $snapshot = Data::decode($task['payload_json'] ?? '');
        $entry = NoticePortalEntry::forLead((int) $task['site_id'], (int) $lead['id']);
        $variables = ['task_no' => mb_substr((string) $lead['lead_no'], 0, 32), 'status_text' => $task['event_key'] === self::ACCEPTED ? '已接单' : '待接单',
            'notice_remark' => self::remark($task['event_key']), 'occurred_at' => date('Y-m-d H:i:s', (int) $snapshot['occurred_at']),
            'store_name' => mb_substr((string) $snapshot['store_name'], 0, 20), '__weapp_page' => $entry['miniapp_path'], '__wechat_page' => $entry['h5_ready'] ? $entry['h5_url'] : ''];
        if ($task['event_key'] !== self::ACCEPTED) {
            if ((int) $lead['response_deadline'] <= 0)throw new NoticePreflightException('原交付未设置接单提醒时间，未发送');
            $variables['response_deadline'] = date('Y-m-d H:i:s', (int) $lead['response_deadline']);
            $variables['remaining_minutes'] = (string) max(0, (int) ceil(((int) $lead['response_deadline'] - time()) / 60));
        }
        return $variables;
    }
    private static function remark(string $event): string
    {
        return match ($event) {
            self::REMINDER => '您有待接单线索，请及时查看',self::OVERDUE => '门店尚未接单，可继续等候或重新选店',default => '交付门店已接单，可查看后续进度'
        };
    }
}
