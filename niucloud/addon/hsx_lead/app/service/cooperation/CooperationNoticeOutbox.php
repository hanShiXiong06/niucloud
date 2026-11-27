<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\job\DispatchCooperationNotice;
use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\service\notice\{AccountNoticePolicyService,NoticeDeliveryWindow,NoticeDeliveryDeferredException};
use think\facade\{Cache,Db,Log};

/** Durable business intents; NiuCloud owns the queue, templates and senders. */
final class CooperationNoticeOutbox
{
    public const TYPE = 'collaboration_notice_outbox';
    private const CURSOR = 'hsx_lead_notice_recovery_cursor:';
    public static function record(int $site, int $leadId, string $action): int
    {
        if (!in_array($action, ['assigned', 'accept', 'accepted', 'reject', 'withdraw', 'cancel', 'deal', 'no_deal', 'paid'], true))return 0;
        return Db::transaction(function () use ($site, $leadId, $action): int {
            $lead = Store::row('collaboration', $site, $leadId, true);
            $key = hash('sha256', implode('|', ['notice-outbox', $site, $leadId, $lead['assignment_version'], $lead['status'], $action]));
            $old = Data::query('audit', $site)->where('dedupe_key', $key)->find();
            if ($old)return (int) $old['id'];
            return (int) Data::query('audit', $site)->insertGetId(['site_id' => $site, 'entity_type' => self::TYPE, 'entity_id' => $leadId,
                'action' => 'notice_pending', 'dedupe_key' => $key, 'detail_json' => Data::json(['action' => $action, 'assignment_version' => (int) $lead['assignment_version'],
                    'lead_status' => $lead['status'], 'status' => 'pending', 'attempts' => 0, 'message' => '业务已保存，通知等待处理']),
                'create_time' => time(), 'update_time' => time()]);
        });
    }
    public static function enqueue(array $event): void
    {
        $site = (int) ($event['site_id'] ?? 0);
        $id = self::record($site, (int) ($event['lead_id'] ?? 0), (string) ($event['action'] ?? ''));
        if (!$id)return;
        // A PaySuccess listener may still be inside the framework's outer
        // transaction. Never send externally before that transaction commits.
        // Dispatch falls back to synchronous execution when the framework
        // queue is disabled. Keep the durable intent for scheduled recovery
        // instead of waiting on external channels in a customer's request.
        if (\addon\hsx_lead\app\service\notice\NoticeTaskRuntime::inTransaction() || !filter_var(env('queue.state', false), FILTER_VALIDATE_BOOLEAN))return;
        try {
            DispatchCooperationNotice::dispatch([['site_id' => $site, 'id' => $id]]);
        } catch (\Throwable $error) {
            Log::warning('协作通知已保留待补偿事件：site='.$site.', id='.$id.', type='.get_class($error));
        }
    }
    public static function process(int $site, int $id, ?callable $dispatcher = null): bool
    {
        // Dispatchers must run after the caller's business transaction commits,
        // even when an integration directly invokes this worker synchronously.
        if (\addon\hsx_lead\app\service\notice\NoticeTaskRuntime::inTransaction())return false;
        $event = Db::transaction(function () use ($site, $id): ?array {
            $row = Data::row('audit', $site, $id, true);
            if ($row['entity_type'] !== self::TYPE || $row['action'] !== 'notice_pending')return null;
            $state = Data::decode($row['detail_json']);
            if (($state['status'] ?? '') === 'processing' && (int) $row['update_time'] > time() - 180)return null;
            if ((int) ($state['next_retry_at'] ?? 0) > time())return null;
            $lead = Store::query('collaboration', $site)->where('id', (int) $row['entity_id'])->find();
            // Missing business facts are not a transient provider error. Keep a
            // visible terminal record so one orphan cannot poison every later
            // event in the bounded scheduled batch.
            if (!$lead) {
                self::finish($site, $id, $state, 'failed', '原协作记录已不存在，通知未发送，请平台核对');
                return null;
            }
            if (!is_string($state['action'] ?? null)
                || !in_array($state['action'], ['assigned', 'accept', 'accepted', 'reject', 'withdraw', 'cancel', 'deal', 'no_deal', 'paid'], true)
                || !is_numeric($state['assignment_version'] ?? null) || (int) $state['assignment_version'] <= 0 || !is_string($state['lead_status'] ?? null)) {
                self::finish($site, $id, $state, 'failed', '通知事件快照不完整，通知未发送，请平台核对');
                return null;
            }
            if ((int) $lead['assignment_version'] !== (int) $state['assignment_version'] || $lead['status'] !== $state['lead_status']) {
                self::finish($site, $id, $state, 'obsolete', '工单已更新，不发送过时提醒');
                return null;
            }
            $policy = AccountNoticePolicyService::raw($site);
            $expires = (int) $row['create_time'] + (int) $policy['ttl_seconds'];
            if ($expires <= time()) {
                self::finish($site, $id, $state, 'expired', '通知已超过时效，不再补发');
                return null;
            }
            $next = NoticeDeliveryWindow::nextAllowed($policy);
            if ($next > time()) {
                self::defer($site, $id, $state, $next, $expires);
                return null;
            }
            if ((int) ($state['attempts'] ?? 0) >= 10) {
                self::finish($site, $id, $state, 'failed', '通知处理多次未完成，请平台核对框架通知日志');
                return null;
            }
            $state['status'] = 'processing';
            $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
            $state['message'] = '通知任务处理中';
            Data::query('audit', $site)->where('id', $id)->update(['detail_json' => Data::json($state), 'update_time' => time()]);
            return ['site_id' => $site, 'lead_id' => (int) $row['entity_id'], 'action' => $state['action'],
                'assignment_version' => (int) $state['assignment_version'], 'lead_status' => $state['lead_status'], 'outbox_attempt' => $state['attempts']];
        });
        if (!$event)return false;
        try {
            // Each recipient/channel has its own persisted claim. Replaying
            // this event only fills missing claims; it never blindly resends an
            // already-submitted or uncertain SMS/WeChat notification.
            (new CooperationNoticeService($dispatcher))->changed($event);
            return Db::transaction(function () use ($site, $id, $event): bool {
                $row = Data::row('audit', $site, $id, true);
                $state = Data::decode($row['detail_json']);
                if (!self::ownsAttempt($row, $state, $event))return false;
                $lead = Store::row('collaboration', $site, (int) $row['entity_id']);
                if ((int) $lead['assignment_version'] !== (int) $event['assignment_version'] || $lead['status'] !== $event['lead_status']) {
                    self::finish($site, $id, $state, 'obsolete', '工单已更新，不再继续发送过时提醒');
                    return false;
                }
                self::finish($site, $id, $state, 'processed', '事件已处理，各渠道结果以通知日志为准');
                return true;
            });
        } catch (NoticeDeliveryDeferredException $error) {
            Db::transaction(function () use ($site, $id, $event, $error): void {
                $row = Data::row('audit', $site, $id, true);
                $state = Data::decode($row['detail_json']);
                if (!self::ownsAttempt($row, $state, $event))return;
                $state['attempts'] = max(0, (int) $state['attempts'] - 1);
                self::defer($site, $id, $state, $error->until(), (int) $row['create_time'] + (int) AccountNoticePolicyService::raw($site)['ttl_seconds']);
            });
            return false;
        } catch (\Throwable $error) {
            Db::transaction(function () use ($site, $id, $event): void {
                $row = Data::row('audit', $site, $id, true);
                $state = Data::decode($row['detail_json']);
                if (!self::ownsAttempt($row, $state, $event))return;
                $state['status'] = 'pending';
                $state['message'] = '业务已保存，通知等待再次处理';
                Data::query('audit', $site)->where('id', $id)->update(['detail_json' => Data::json($state), 'update_time' => time()]);
            });
            Log::warning('协作通知事件待补偿：site='.$site.', id='.$id.', type='.get_class($error));
            return false;
        }
    }
    private static function ownsAttempt(array $row, array $state, array $event): bool
    {
        return $row['action'] === 'notice_pending'
            && ($state['status'] ?? '') === 'processing'
            && (int) ($state['attempts'] ?? 0) === (int) $event['outbox_attempt'];
    }
    private static function defer(int $site, int $id, array $state, int $until, int $expires): void
    {
        if ($until >= $expires) {
            self::finish($site, $id, $state, 'expired', '通知会在免打扰结束前过时，不再发送');
            return;
        }
        $state['status'] = 'pending';
        $state['next_retry_at'] = $until;
        $state['message'] = NoticeDeliveryWindow::message($until);
        Data::query('audit', $site)->where('id', $id)->update(['detail_json' => Data::json($state), 'update_time' => time()]);
    }
    private static function finish(int $site, int $id, array $state, string $status, string $message): void
    {
        $state['status'] = $status;
        $state['message'] = $message;
        Data::query('audit', $site)->where('id', $id)->update(['action' => 'notice_'.$status, 'detail_json' => Data::json($state), 'update_time' => time()]);
    }
    /** A rotating site cursor plus existing (site_id,action,id) index bounds recovery. */
    public static function recover(int $siteLimit = 5, int $eventLimit = 20, ?callable $dispatcher = null): array
    {
        $db = Db::connect();
        $scope = (string) $db->getConfig('database').':'.(string) $db->getConfig('prefix');
        $lock = 'lead_notice_recover:'.substr(hash('sha256', $scope), 0, 35);
        $owned = $db->query('SELECT GET_LOCK(:key_name,0) AS acquired', ['key_name' => $lock], true);
        if ((int) ($owned[0]['acquired'] ?? 0) !== 1)return ['examined' => 0, 'processed' => 0, 'busy' => true];
        try {
            // This cursor is a rebuildable scheduling hint, not a business fact
            // or rule. Cache deletion restarts the fair scan; persisted intents
            // and per-channel dedupe still decide whether anything is sent.
            $cursorKey = self::CURSOR.hash('sha256', $scope);
            $cursor = 0;
            try {
                $value = Cache::get($cursorKey);
                if (is_int($value) || (is_string($value) && ctype_digit($value)))$cursor = max(0, (int) $value);
            } catch (\Throwable $error) {
                Log::warning('协作通知轮转缓存不可读，将从首个站点核对：type='.get_class($error));
            }
            $sites = Db::name('site')->where('status', 1)->where('site_id', '>', $cursor)->order('site_id')->limit(max(1, min(20, $siteLimit)))->column('site_id');
            if (!$sites)$sites = Db::name('site')->where('status', 1)->order('site_id')->limit(max(1, min(20, $siteLimit)))->column('site_id');
            $result = ['examined' => 0, 'processed' => 0, 'busy' => false];
            foreach ($sites as $site) {
                $site = (int) $site;
                // A fresh pending intent is safe on the next scheduled round.
                // Only an in-flight worker owns the 180-second processing lease.
                $ids = Data::query('audit', $site)->where('action', 'notice_pending')->where('entity_type', self::TYPE)
                    ->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(detail_json),detail_json,'{}'),'$.status')) = 'pending' OR update_time <= :lease_before)", ['lease_before' => time() - 180])
                    ->whereRaw("COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(detail_json),detail_json,'{}'),'$.next_retry_at')) AS UNSIGNED),0) <= :due_now", ['due_now' => time()])
                    ->order('id')->limit(max(1, min(50, $eventLimit)))->column('id');
                foreach ($ids as $id) {
                    $result['examined']++;
                    try {
                        if (self::process($site, (int) $id, $dispatcher))$result['processed']++;
                    } catch (\Throwable $error) {
                        Log::warning('协作通知单条恢复异常，后续事件继续核对：site='.$site.', id='.(int) $id.', type='.get_class($error));
                    }
                }
            }
            if ($sites)try {
                Cache::set($cursorKey, (int) end($sites), 86400);
            } catch (\Throwable $error) {
                Log::warning('协作通知轮转缓存不可写，业务通知记录未丢失：type='.get_class($error));
            }
            return $result;
        } finally {
            try {
                $db->query('SELECT RELEASE_LOCK(:key_name) AS released', ['key_name' => $lock], true);
            } catch (\Throwable $ignored) {
            }
        }
    }
}
