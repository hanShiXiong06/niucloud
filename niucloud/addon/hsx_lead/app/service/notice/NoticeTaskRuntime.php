<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

use addon\hsx_lead\app\service\{Context,FrameworkNoticeGateway,NoticeConfigService,NoticePortalEntry,NoticePreflightException};
use addon\hsx_lead\app\support\Data;
use core\exception\{CommonException,NoticeDeliveryException};
use think\facade\Db;

/** Shared typed notification delivery, leases, attempts and safe retries. Source adapters own business validation. */
final class NoticeTaskRuntime
{
    public const SAFE_RETRY = ['pending_configuration', 'pending_recipient', 'pending_authorization', 'paused', 'retry_wait', 'rejected', 'exhausted'];
    public const STATUSES = [
        'pending',
        'dispatching',
        'accepted',
        'unknown',
        'pending_configuration',
        'pending_recipient',
        'pending_authorization',
        'paused',
        'retry_wait',
        'rejected',
        'expired',
        'exhausted',
        'obsolete'
    ];
    private ?\Closure $dispatcher;
    public function __construct(?callable $dispatcher = null)
    {
        $this->dispatcher = $dispatcher === null ? null : \Closure::fromCallable($dispatcher);
    }
    public static function inTransaction(): bool
    {
        $connection = Db::connect();
        $pdo = $connection->getPdo();
        if (!$pdo instanceof \PDO) {
            $connection->query('SELECT 1 AS notice_transaction_probe', [], true);
            $pdo = $connection->getPdo();
        }
        if (!$pdo instanceof \PDO)throw new CommonException('通知数据库连接尚未就绪，未提交发送');
        return $pdo->inTransaction();
    }

    /** Caller holds the source row lock; all source adapters share the exact same delivery task contract. */
    public static function recordIntent(int $site, string $sourceType, int $sourceId, int $member, string $event, string $channel, array $snapshot, int $due, int $expires, string $discriminator = ''): int
    {
        if (!isset(NoticeEventCatalog::EVENTS[$event])
            || !in_array($channel, NoticeEventCatalog::CHANNELS, true)
            || $site <= 0
            || $sourceId <= 0
            || $member <= 0
            || $expires <= time())return 0;
        $identity = [$site, $sourceType, $sourceId, $member, $event, $channel];
        if ($discriminator !== '')$identity[] = $discriminator;
        $dedupe = hash('sha256', implode('|', $identity));
        if (Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('dedupe_key', $dedupe)->count())return 0;
        Db::name('hsx_lead_notice_task')->insert(['site_id' => $site, 'event_key' => $event, 'source_type' => $sourceType, 'source_id' => $sourceId, 'recipient_member_id' => $member, 'channel' => $channel,
            'dedupe_key' => $dedupe, 'status' => 'pending', 'payload_json' => Data::json($snapshot), 'attempt_count' => 0, 'manual_retry_count' => 0, 'next_retry_at' => $due,
            'lease_token' => '', 'lease_until' => 0, 'expires_at' => $expires, 'provider_code' => '', 'framework_log_id' => 0, 'last_message' => '业务已保存，等待通知计划执行', 'create_time' => time(), 'update_time' => time()]);
        return 1;
    }
    public function recoverSite(int $site, int $limit = 50): int
    {
        if (self::inTransaction())throw new CommonException('事务内不允许执行通知任务');
        Db::name('hsx_lead_notice_task')->where('site_id', $site)->whereIn('status', [
                'pending',
                'retry_wait',
                'pending_configuration',
                'pending_recipient',
                'pending_authorization',
                'paused',
                'rejected',
                'exhausted'
            ])->where('expires_at', '<=', time())->update([
                'status' => 'expired',
                'last_message' => '通知已超过时效，不再补发',
                'update_time' => time()
            ]);
        foreach (Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('status', 'dispatching')->where('lease_until', '<', time())->limit(max(1, min(200, $limit)))->column('id') as $id)$this->unknownExpiredLease($site, (int) $id);
        $ids = Db::name('hsx_lead_notice_task')->where('site_id', $site)->whereIn('status', [
                'pending',
                'retry_wait',
                'pending_configuration',
                'pending_recipient',
                'paused'
            ])->where('next_retry_at', '<=', time())->order('next_retry_at,id')->limit(max(1, min(200, $limit)))->column('id');
        foreach ($ids as $id)$this->process($site, (int) $id);
        return count($ids);
    }

    public function process(int $site, int $id): array
    {
        if (self::inTransaction())throw new CommonException('事务内不允许发送通知');
        $claim = Db::transaction(function () use ($site, $id) {
            $row = self::row($site, $id, true);
            if (!in_array($row['status'], ['pending', 'retry_wait', 'pending_configuration', 'pending_recipient', 'paused'], true))return null;
            if ((int) $row['next_retry_at'] > time())return null;
            if ((int) $row['expires_at'] <= time()) {
                $this->pause($site, $id, 'expired', '通知已超过时效，不再补发');
                return null;
            }
            $policy = AccountNoticePolicyService::raw($site);
            if ((int) $row['attempt_count'] >= (int) $policy['max_attempts']) {
                $this->pause($site, $id, 'exhausted', '已达到自动尝试上限');
                return null;
            }
            try {
                [$status,$message,$template] = $this->prepare($row, $policy);
            } catch (ObsoleteNoticeException $error) {
                $this->pause($site, $id, 'obsolete', '交付已变化或节点过时，不再发送');
                return null;
            } catch (NoticePreflightException $error) {
                $this->pause($site, $id, 'rejected', '原业务记录或通知快照不一致，未发送');
                return null;
            }
            if ($status !== 'ready') {
                $this->pause($site, $id, $status, $message);
                return null;
            }
            $next = NoticeDeliveryWindow::nextAllowed($policy);
            if ($next > time()) {
                $this->defer($row, $next);
                return null;
            }
            $queued = Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('task_id', $id)->where('status', 'queued')->lock(true)->order('id desc')->find();
            $token = bin2hex(random_bytes(16));
            $attemptNo = (int) Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('task_id', $id)->max('attempt_no') + 1;
            if ($attemptNo > 20) {
                $this->pause($site, $id, 'exhausted', '本条通知已达到安全发送上限');
                return null;
            }
            if ($queued) {
                $attemptNo = (int) $queued['attempt_no'];
                $attemptId = (int) $queued['id'];
                Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('id', $attemptId)->update(['status' => 'dispatching', 'lease_token' => $token]);
            } else $attemptId = (int) Db::name('hsx_lead_notice_attempt')->insertGetId([
                    'site_id' => $site,
                    'task_id' => $id,
                    'attempt_no' => $attemptNo,
                    'trigger_type' => 'automatic',
                    'operator_uid' => 0,
                    'reason' => '自动执行',
                    'status' => 'dispatching',
                    'provider_code' => '',
                    'message' => '正在提交渠道',
                    'framework_log_id' => 0,
                    'lease_token' => $token,
                    'request_key' => null,
                    'create_time' => time(),
                    'finish_time' => 0
                ]);
            Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('id', $id)->update([
                    'status' => 'dispatching',
                    'lease_token' => $token,
                    'lease_until' => time() + 180,
                    'last_message' => '正在提交本渠道，请勿重复发送',
                    'update_time' => time()
                ]);
            return ['site_id' => $site, 'key' => NoticeEventCatalog::key($row['event_key']), 'channel' => $row['channel'], 'template' => $template,
                'expected_recipient_member_id' => (int) $row['recipient_member_id'],
                'data' => ['financial_task_id' => $id, 'financial_attempt_id' => $attemptId, 'notice_attempt' => $attemptNo, 'lease_token' => $token]];
        });
        if (!$claim)return self::row($site, $id);
        $status = 'unknown';
        $code = '';
        $message = '发送结果尚未核实，不自动重发';
        $deferredUntil = 0;
        try {
            $result = $this->dispatcher ? ($this->dispatcher)($claim) : (new FrameworkNoticeGateway())->dispatch($claim);
            if (is_array($result)
                && ($result['accepted'] ?? null) === true
                && ($result['channel'] ?? '') === $claim['channel']
                && ($result['provider_code'] ?? null) === '0') {
                $status = 'accepted';
                $code = '0';
                $message = '服务商已受理，不代表用户已收到或查看';
            }
        } catch (NoticeDeliveryDeferredException $error) {
            $status = 'paused';
            $deferredUntil = $error->until();
            $message = $error->getMessage();
        } catch (ObsoleteNoticeException $error) {
            $status = 'obsolete';
            $message = '交付已变化，本次未提交，不再发送过时提醒';
        } catch (NoticePreflightException $error) {
            $status = 'pending_configuration';
            $message = '发送前校验未通过，未提交服务商；请核对原配置和接收身份';
        } catch (NoticeDeliveryException $error) {
            if ($error->channel() === $claim['channel']) {
                $code = $error->providerCode();
                if ($error->deliveryStatus() === 'not_submitted') {
                    $status = 'retry_wait';
                    $message = '渠道明确未提交，稍后自动重试';
                } elseif ($error->deliveryStatus() === 'rejected') {
                    [$status,$message] = match ($code) {
                        '43101' => ['pending_authorization', '用户未订阅或订阅次数已用尽，等待用户重新订阅'],
                        '40003' => ['pending_recipient', '接收用户微信身份无效，请重新登录对应渠道'],
                        '40037','41030','47003','40001','40014','42001' => ['pending_configuration', '模板、字段或渠道凭据未就绪，请管理员核对'],
                        '-1','45009' => ['retry_wait', '服务商明确拒绝且可恢复，等待退避重试'],
                        default => ['rejected', '服务商明确拒绝，需核对后由管理员安全重试']
                    };
                }
            }
        } catch (\Throwable $error) {
            $status = 'unknown';
            $message = '渠道处理异常，可能已经提交；请核对框架记录，不重复发送';
        }
        $this->finish($claim, $status, $code, $message, $deferredUntil);
        return self::row($site, $id);
    }

    private function prepare(array $row, array $policy): array
    {
        $site = (int) $row['site_id'];
        $event = $policy['events'][$row['event_key']] ?? [];
        $channel = $row['channel'];
        if (!AccountNoticePolicyService::installed($site)
            || empty($policy['enabled'])
            || empty($event['enabled'])
            || empty($event['channels'][$channel]))return ['paused', '本站已暂停本场景或渠道', null];
        NoticeSourceRegistry::verify($row);
        if (!NoticePreferenceService::enabled($site, (int) $row['recipient_member_id'], $row['event_key'], $channel))return ['paused', '用户已关闭本场景提醒', null];
        $member = Db::name('member')->where('site_id', $site)->where('member_id', $row['recipient_member_id'])->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->find();
        $identity = ['weapp' => 'weapp_openid', 'wechat' => 'wx_openid', 'sms' => 'mobile'][$channel];
        if (!$member || empty($member[$identity]))return ['pending_recipient', '接收会员尚未完成渠道身份绑定', null];
        $key = NoticeEventCatalog::key($row['event_key']);
        $config = Db::name('sys_notice')->where('site_id', $site)->where('key', $key)->find() ?: [];
        $map = $channel === 'sms' ? NoticeEventCatalog::smsDefinitions() : AccountNoticePolicyService::mapping($site, $channel);
        $field = $channel === 'sms' ? 'sms_id' : $channel.'_template_id';
        if (empty($config['is_'.$channel]) || empty($config[$field]) || empty($map[$key]))return ['pending_configuration', '请在牛云对应通知渠道获取本站真实模板并开启', null];
        if ($channel === 'sms' && !NoticeConfigService::smsAccountConfigured($site))return ['pending_configuration', '短信账号尚未开通或不可用', null];
        if ($channel === 'wechat' && !NoticePortalEntry::get($site)['h5_ready'])return ['pending_configuration', '本站公众号HTTPS入口未配置', null];
        return [
            'ready',
            '',
            array_replace(NoticeEventCatalog::definitions()[$key], [
                    'site_id' => $site,
                    'is_weapp' => 0,
                    'is_wechat' => 0,
                    'is_sms' => 0,
                    'is_'.$channel => 1,
                    $field => $config[$field],
                    $channel => $map[$key]
                ])
        ];
    }
    private function pause(int $site, int $id, string $status, string $message): void
    {
        Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('id', $id)->update([
                'status' => $status,
                'last_message' => $message,
                'next_retry_at' => time() + 300,
                'lease_token' => '',
                'lease_until' => 0,
                'update_time' => time()
            ]);
    }
    private function defer(array $row, int $until): void
    {
        $expired = $until >= (int) $row['expires_at'];
        Db::name('hsx_lead_notice_task')->where('site_id', $row['site_id'])->where('id', $row['id'])->update([
            'status' => $expired ? 'expired' : 'paused', 'last_message' => $expired ? '通知会在免打扰结束前过时，不再发送' : NoticeDeliveryWindow::message($until),
            'next_retry_at' => $until, 'lease_token' => '', 'lease_until' => 0, 'update_time' => time()]);
    }
    private function finish(array $claim, string $status, string $code, string $message, int $deferredUntil = 0): void
    {
        Db::transaction(function () use ($claim, $status, $code, $message, $deferredUntil) {
            $site = (int) $claim['site_id'];
            $id = (int) $claim['data']['financial_task_id'];
            $row = self::row($site, $id, true);
            if ($row['status'] !== 'dispatching' || $row['lease_token'] !== $claim['data']['lease_token'])return;
            $paused = $deferredUntil > 0 || in_array($status, ['pending_authorization', 'pending_configuration', 'pending_recipient'], true);
            $count = (int) $row['attempt_count'] + ($paused ? 0 : 1);
            $policy = AccountNoticePolicyService::raw($site);
            if ($deferredUntil >= (int) $row['expires_at']) {
                $status = 'expired';
                $message = '通知会在免打扰结束前过时，不再发送';
            }
            if ($status === 'retry_wait' && $count >= (int) $policy['max_attempts']) {
                $status = 'exhausted';
                $message = '已达到自动尝试上限，请核对明确失败原因';
            }
            $delay = [60, 300, 1800, 3600, 7200][min(4, max(0, $count - 1))];
            $logId = self::frameworkLogId($row, (int) $claim['data']['notice_attempt']);
            Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('id', $id)->update(['status' => $status, 'attempt_count' => $count, 'next_retry_at' => $deferredUntil > 0 ? $deferredUntil : time() + $delay,
                'lease_token' => '', 'lease_until' => 0, 'provider_code' => $code, 'framework_log_id' => $logId, 'last_message' => $message, 'update_time' => time()]);
            Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('id', $claim['data']['financial_attempt_id'])->where('lease_token', $claim['data']['lease_token'])->update([
                    'status' => $deferredUntil > 0 ? 'paused' : $status,
                    'provider_code' => $code,
                    'message' => $message,
                    'framework_log_id' => $logId,
                    'finish_time' => time()
                ]);
        });
    }
    public function unknownExpiredLease(int $site, int $id): void
    {
        Db::transaction(function () use ($site, $id) {
            $row = self::row($site, $id, true);
            if ($row['status'] !== 'dispatching' || (int) $row['lease_until'] >= time())return;
            Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('task_id', $id)->where('status', 'dispatching')->where('lease_token', $row['lease_token'])->update([
                    'status' => 'unknown',
                    'message' => '执行中断，无法确定服务商是否受理，不自动重发',
                    'finish_time' => time()
                ]);
            $this->pause($site, $id, 'unknown', '执行中断，结果待核实，不自动重发');
        });
    }
    private static function frameworkLogId(array $row, int $attempt): int
    {
        foreach (Db::name('sys_notice_log')->where('site_id', $row['site_id'])->where('key', NoticeEventCatalog::key($row['event_key']))->where('member_id', $row['recipient_member_id'])->order('id desc')->limit(10)->select()->toArray() as $log) {
            $params = Data::decode($log['params'] ?? '');
            $vars = $params['vars'] ?? $params['__hsx_lead_notice'] ?? [];
            if (($vars['__hsx_lead_notice_id'] ?? '') === (string) $row['id']
                && ($vars['__hsx_lead_notice_channel'] ?? '') === $row['channel']
                && ($vars['__hsx_lead_notice_attempt'] ?? '') === (string) $attempt)return (int) $log['id'];
        }
        return 0;
    }
    public static function templateData(int $site, int $id, int $attemptId, string $channel, string $key, string $token): array
    {
        $task = self::row($site, $id);
        $attempt = Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('id', $attemptId)->where('task_id', $id)->find();
        if (!$attempt
            || $task['status'] !== 'dispatching'
            || $attempt['status'] !== 'dispatching'
            || $task['lease_token'] !== $token
            || $attempt['lease_token'] !== $token
            || $task['channel'] !== $channel
            || NoticeEventCatalog::key($task['event_key']) !== $key)throw new NoticePreflightException('通知执行身份不一致，未发送');
        if ((int) $task['expires_at'] <= time())throw new ObsoleteNoticeException('通知已超过时效，未发送');
        NoticeDeliveryWindow::assertOpen($site);
        [$state] = (new self())->prepare($task, AccountNoticePolicyService::raw($site));
        if ($state !== 'ready')throw new NoticePreflightException('通知配置或接收身份已变化，未发送');
        $source = NoticeSourceRegistry::verify($task);
        $vars = NoticeSourceRegistry::variables($task, $source) + [
            '__hsx_lead_notice_id' => (string) $id, '__hsx_lead_notice_channel' => $channel, '__hsx_lead_notice_attempt' => (string) $attempt['attempt_no'], '__hsx_lead_notice_lease' => $token];
        $vars['url'] = $vars['__wechat_page'];
        $mapping = $channel === 'sms' ? [] : AccountNoticePolicyService::mapping($site, $channel);
        foreach ($mapping[$key]['content'] ?? [] as $field) {
            $variable = trim($field[1], '{}');
            if (!isset($vars[$variable]))continue;
            if (str_starts_with($field[2], 'thing'))$vars[$variable] = mb_substr((string) $vars[$variable], 0, 20);
            elseif (str_starts_with($field[2], 'date'))$vars[$variable] = substr((string) $vars[$variable], 0, 10);
        }
        return ['to' => ['member_id' => (int) $task['recipient_member_id']], 'vars' => $vars];
    }

    public function page(Context $context, array $filters = []): array
    {
        $context->permission('hsx_lead_notice_center_read');
        $query = Db::name('hsx_lead_notice_task')->alias('t')->leftJoin('member m', 'm.site_id=t.site_id AND m.member_id=t.recipient_member_id')->where('t.site_id', $context->site);
        foreach (['event_key', 'status', 'channel'] as $field)if (($filters[$field] ?? '') !== '')$query->where('t.'.$field, (string) $filters[$field]);
        if (!empty($filters['member_id']))$query->where('t.recipient_member_id', self::positive($filters['member_id']));
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($keyword, 0, 80)).'%';
            $query->where(function ($q) use ($keyword, $search) {
                $q->whereLike('m.nickname|m.mobile|m.member_no', $search);
                if (ctype_digit($keyword))$q->whereOr('t.recipient_member_id', (int) $keyword);
            });
        }
        if (!empty($filters['start_time']))$query->where('t.create_time', '>=', (int) $filters['start_time']);
        if (!empty($filters['end_time']))$query->where('t.create_time', '<=', (int) $filters['end_time']);
        $total = (clone $query)->count();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
        $rows = $query->field('t.*,m.nickname,m.member_no,m.mobile')->order('t.id desc')->page($page, $limit)->select()->toArray();
        foreach ($rows as &$row)$row = $this->present($row);
        return ['data' => $rows, 'total' => $total, 'current_page' => $page, 'per_page' => $limit];
    }
    public function detail(Context $context, int $id): array
    {
        $context->permission('hsx_lead_notice_center_read');
        $row = $this->present(self::row($context->site, $id));
        $row['attempts'] = Db::name('hsx_lead_notice_attempt')->where('site_id', $context->site)->where('task_id', $id)->field('id,attempt_no,trigger_type,operator_uid,reason,status,provider_code,message,framework_log_id,create_time,finish_time')->order('id desc')->select()->toArray();
        return $row;
    }
    private function present(array $row): array
    {
        $source = Data::decode($row['payload_json'] ?? '');
        $row['account_name'] = NoticeEventCatalog::EVENTS[$row['event_key']]['account_name'] ?? '';
        $row['group'] = str_starts_with($row['event_key'], 'account_') ? 'account' : 'cooperation';
        foreach (['task_no', 'response_deadline', 'remaining_minutes', 'store_name', 'notice_remark'] as $field)$row[$field] = $source[$field] ?? '';
        $row['change_amount'] = (string) ($source['account_data'] ?? '');
        $row['account_balance'] = (string) ($source['account_sum'] ?? '');
        $row['occurred_at'] = (int) ($source['occurred_at'] ?? 0);
        unset($row['lease_token'],$row['payload_json'],$row['dedupe_key']);
        $row['event_name'] = NoticeEventCatalog::EVENTS[$row['event_key']]['name'] ?? '资金变动';
        $row['status_name'] = self::statusName((string) $row['status']);
        $row['channel_name'] = ['weapp' => '小程序', 'wechat' => '公众号', 'sms' => '短信'][$row['channel']] ?? $row['channel'];
        $row['can_retry'] = in_array($row['status'], self::SAFE_RETRY, true) && (int) $row['manual_retry_count'] < 3 && (int) $row['expires_at'] > time();
        return $row;
    }
    public static function statusName(string $status): string
    {
        return ['pending' => '待处理', 'dispatching' => '提交中', 'accepted' => '服务商已受理', 'unknown' => '结果待核实', 'pending_configuration' => '待配置', 'pending_recipient' => '待绑定接收身份',
            'pending_authorization' => '待重新订阅', 'paused' => '已暂停', 'retry_wait' => '等待自动重试', 'rejected' => '明确未受理', 'expired' => '已过时', 'exhausted' => '尝试次数用尽', 'queued' => '重试已排队', 'obsolete' => '交付已变更'][$status] ?? '未知状态';
    }
    public function retry(Context $context, int $id, array $input): array
    {
        $context->permission('hsx_lead_notice_center_retry');
        if (($input['confirm'] ?? false) !== true)throw new CommonException('请确认本次仅重试明确未受理的通知');
        try {
            NoticeDeliveryWindow::assertOpen($context->site);
        } catch (NoticeDeliveryDeferredException$error) {
            throw new CommonException($error->getMessage());
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 200)throw new CommonException('请填写200字以内的重试原因');
        $requestKey = (string) ($input['request_key'] ?? bin2hex(random_bytes(16)));
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/D', $requestKey))throw new CommonException('重试请求编号格式不正确');
        Db::transaction(function () use ($context, $id, $reason, $requestKey) {
            $site = $context->site;
            $row = self::row($site, $id, true);
            if (Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('task_id', $id)->where('request_key', $requestKey)->count())return;
            if (!in_array($row['status'], self::SAFE_RETRY, true)
                || (int) $row['expires_at'] <= time()
                || (int) $row['manual_retry_count'] >= 3)throw new CommonException('该通知不可安全重试；已受理、结果不明、过时或次数用尽均不会重复发送');
            [$state,$message] = $this->prepare($row, AccountNoticePolicyService::raw($site));
            if ($state !== 'ready')throw new CommonException($message);
            $no = (int) Db::name('hsx_lead_notice_attempt')->where('site_id', $site)->where('task_id', $id)->max('attempt_no') + 1;
            Db::name('hsx_lead_notice_attempt')->insert([
                    'site_id' => $site,
                    'task_id' => $id,
                    'attempt_no' => $no,
                    'trigger_type' => 'manual',
                    'operator_uid' => $context->uid,
                    'reason' => $reason,
                    'status' => 'queued',
                    'provider_code' => '',
                    'message' => '管理员已安排安全重试',
                    'framework_log_id' => 0,
                    'lease_token' => '',
                    'request_key' => $requestKey,
                    'create_time' => time(),
                    'finish_time' => 0
                ]);
            Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('id', $id)->update([
                    'status' => 'pending',
                    'manual_retry_count' => (int) $row['manual_retry_count'] + 1,
                    'attempt_count' => 0,
                    'next_retry_at' => time(),
                    'last_message' => '管理员已安排重试，等待异步处理',
                    'update_time' => time()
                ]);
        });
        return $this->detail($context, $id);
    }
    private static function row(int $site, int $id, bool $lock = false): array
    {
        $query = Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('id', $id);
        if ($lock)$query->lock(true);
        $row = $query->find();
        if (!$row)throw new CommonException('通知记录不存在或不属于本站');
        return $row;
    }
    private static function positive($value): int
    {
        return (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,15}$/D', (string) $value) ? (int) $value : 0;
    }
}
