<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** Tenant/owner-scoped operational ledger, never a notification broadcast API. */
final class CooperationNoticeReadService
{
    private Context $context;
    private ?\Closure $dispatcher;
    private const CHANNELS = ['queue', 'weapp', 'wechat', 'sms'];
    private const STATUSES = [
        'pending',
        'processing',
        'processed',
        'obsolete',
        'expired',
        'deferred',
        'failed',
        'disabled',
        'pending_configuration',
        'pending_recipient',
        'dispatching',
        'submitted',
        'accepted',
        'rejected',
        'not_submitted',
        'uncertain'
    ];
    public function __construct(Context $context, ?callable $dispatcher = null)
    {
        $this->context = $context;
        $this->dispatcher = $dispatcher === null ? null : \Closure::fromCallable($dispatcher);
    }
    public function lists(int $leadId, array $params = []): array
    {
        $context = $this->context;
        if ($context->uid <= 0)throw new CommonException('仅平台管理人员可以查看通知记录');
        Store::permission($context, 'notice_list');
        $lead = Store::adminLead($context, $leadId);
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $channel = Data::text($params, 'channel', 30);
        $status = Data::text($params, 'status', 40);
        $recipient = Data::text($params, 'recipient', 100);
        if ($channel !== '' && !in_array($channel, self::CHANNELS, true))throw new CommonException('通知渠道筛选无效');
        if ($status !== '' && !in_array($status, self::STATUSES, true))throw new CommonException('通知状态筛选无效');
        $query = Data::query('audit', $context->site)->whereIn('entity_type', ['collaboration_notice', CooperationNoticeOutbox::TYPE])->where('entity_id', $leadId);
        if ($channel === 'queue')$query->where('entity_type', CooperationNoticeOutbox::TYPE);
        elseif ($channel !== '')$query->where('entity_type', 'collaboration_notice')->whereRaw(self::jsonField('channel').' = :notice_channel', ['notice_channel' => $channel]);
        if ($status !== '')$query->whereRaw(self::jsonField('status').' = :notice_status', ['notice_status' => $status]);
        $memberId = max(0, (int) ($params['recipient_member_id'] ?? 0));
        if ($memberId > 0)$query->where('entity_type', 'collaboration_notice')->whereRaw(self::jsonField('member_id').' = :notice_member', ['notice_member' => (string) $memberId]);
        if ($recipient !== '') {
            // Resolve only safe identity fields, never telephone/OpenID/credentials.
            // Use a subquery rather than a bounded id list: common names must not
            // silently omit matching members, and SQL remains fully parameterized.
            $table = Db::name('member')->getTable();
            $field = self::jsonField('member_id');
            $query->where('entity_type', 'collaboration_notice')->whereRaw(
                "EXISTS (SELECT 1 FROM `".str_replace('`', '``', $table)."` notice_recipient WHERE notice_recipient.site_id = :recipient_site AND notice_recipient.member_id = CAST(".$field." AS UNSIGNED) AND (notice_recipient.nickname LIKE :recipient_name OR notice_recipient.member_no LIKE :recipient_no OR notice_recipient.member_id = :recipient_id))",
                [
                    'recipient_site' => $context->site,
                    'recipient_name' => '%'.$recipient.'%',
                    'recipient_no' => '%'.$recipient.'%',
                    'recipient_id' => ctype_digit($recipient) ? (int) $recipient : 0
                ]);
        }
        $total = (int) (clone $query)->count();
        $rows = $query->order('id desc')->page($page, $limit)->select()->toArray();
        return ['data' => $this->project($lead, $rows, $this->mayRetry()), 'total' => $total, 'current_page' => $page, 'per_page' => $limit];
    }
    public function retry(int $leadId, int $auditId, array $input = []): array
    {
        (new CooperationNoticeService($this->dispatcher))->retry($this->context, $leadId, $auditId, $input);
        $lead = Store::adminLead($this->context, $leadId);
        $row = Data::row('audit', $this->context->site, $auditId);
        return $this->project($lead, [$row], true)[0];
    }
    private function mayRetry(): bool
    {
        try {
            Store::permission($this->context, 'notice_retry');
            return true;
        } catch (CommonException $ignored) {
            return false;
        }
    }
    private static function jsonField(string $name): string
    {
        return "JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(detail_json),detail_json,'{}'),'$.".$name."'))";
    }
    private function project(array $lead, array $rows, bool $mayRetry): array
    {
        $states = [];
        $memberIds = [];
        $merchantIds = [];
        $site = $this->context->site;
        foreach ($rows as $row) {
            $state = Data::decode($row['detail_json']);
            $states[(int) $row['id']] = $state;
            if ($row['entity_type'] !== 'collaboration_notice')continue;
            if ((int) ($state['member_id'] ?? 0) > 0)$memberIds[] = (int) $state['member_id'];
            if ((int) ($state['merchant_id'] ?? 0) > 0)$merchantIds[] = (int) $state['merchant_id'];
        }
        $members = [];
        $merchants = [];
        if ($memberIds)foreach (Db::name('member')->where('site_id', $site)->whereIn('member_id', array_unique($memberIds))->field('member_id,nickname,member_no')->select()->toArray() as $member)$members[(int) $member['member_id']] = $member;
        if ($merchantIds)foreach (Data::query('merchant', $site)->whereIn('id', array_unique($merchantIds))->field('id,name')->select()->toArray() as $merchant)$merchants[(int) $merchant['id']] = $merchant;
        $result = [];
        $retryCache = [];
        foreach ($rows as $row) {
            $state = $states[(int) $row['id']];
            $queue = $row['entity_type'] === CooperationNoticeOutbox::TYPE;
            $memberId = $queue ? 0 : (int) ($state['member_id'] ?? 0);
            $merchantId = $queue ? 0 : (int) ($state['merchant_id'] ?? 0);
            $member = $members[$memberId] ?? [];
            $account = trim((string) ($member['nickname'] ?? '')) ?: trim((string) ($member['member_no'] ?? ''));
            $shop = trim((string) ($merchants[$merchantId]['name'] ?? '')) ?: ($merchantId > 0 ? '门店#'.$merchantId : '未记录门店');
            $block = $queue ? '事件任务由队列与计划任务补偿，不直接重发' : (!$mayRetry ? '当前账号没有通知重试权限' : CooperationNoticeService::retryBlockReason($site, $lead, $row, $state, $retryCache));
            $code = (string) ($state['provider_code'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_.-]{1,40}$/D', $code))$code = '';
            $result[] = ['id' => (int) $row['id'], 'channel' => $queue ? 'queue' : (string) ($state['channel'] ?? ''), 'status' => (string) ($state['status'] ?? 'uncertain'),
                'recipient_label' => $queue ? '业务事件任务' : $shop.' · '.($account !== '' ? $account : ($memberId > 0 ? '账号#'.$memberId : '未记录账号')),
                'recipient_member_id' => $memberId, 'recipient_merchant_id' => $merchantId, 'notice_key' => (string) ($state['notice_key'] ?? ''), 'template_id' => (string) ($state['template_id'] ?? ''),
                'phase' => (string) ($state['phase'] ?? ''), 'event_action' => (string) ($state['event_action'] ?? $state['action'] ?? ''), 'provider_code' => $code, 'message' => (string) ($state['message'] ?? '待核实'),
                'create_time' => (int) $row['create_time'], 'update_time' => (int) $row['update_time'], 'retry_count' => (int) ($state['retry_count'] ?? 0), 'attempts' => (int) ($state['attempts'] ?? ($queue ? 0 : 1)),
                'can_retry' => $block === '', 'retry_block_reason' => $block];
        }
        return $result;
    }
}
