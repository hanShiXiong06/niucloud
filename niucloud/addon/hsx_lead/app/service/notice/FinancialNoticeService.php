<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\{Db,Cache,Log};

/** Durable post-commit delivery. A financial transaction can only record intent, never send. */
final class FinancialNoticeService
{
    public const SAFE_RETRY = NoticeTaskRuntime::SAFE_RETRY;
    public const STATUSES = NoticeTaskRuntime::STATUSES;
    private ?\Closure $dispatcher;
    public function __construct(?callable $dispatcher = null)
    {
        $this->dispatcher = $dispatcher === null ? null : \Closure::fromCallable($dispatcher);
    }

    public function recordAccountEvent(array $event): array
    {
        $site = self::positive($event['site_id'] ?? null);
        $member = self::positive($event['member_id'] ?? null);
        $id = self::positive($event['account_log_id'] ?? null);
        $type = $event['account_type'] ?? null;
        if (!is_string($type) || NoticeEventCatalog::fromAccount($type) === null)return ['recorded' => 0, 'skipped' => 'not_financial'];
        if (!$site || !$member || !$id)throw new \InvalidArgumentException('通知必须关联真实资金流水ID及本站会员');
        if (!AccountNoticePolicyService::installed($site))return ['recorded' => 0, 'skipped' => 'site_disabled'];
        $policy = AccountNoticePolicyService::raw($site);
        $key = NoticeEventCatalog::fromAccount($type);
        if (empty($policy['enabled']) || empty($policy['events'][$key]['enabled']))return ['recorded' => 0, 'skipped' => 'disabled'];
        $row = Db::name('member_account_log')->where('site_id', $site)->where('id', $id)->find();
        if (!$row || (int) $row['member_id'] !== $member || $row['account_type'] !== $type
            || NoticeEventCatalog::decimal($row['account_data']) !== NoticeEventCatalog::decimal($event['account_data'] ?? null)
            || NoticeEventCatalog::decimal($row['account_sum']) !== NoticeEventCatalog::decimal($event['account_sum'] ?? null))throw new \InvalidArgumentException('通知事件与真实资金流水不一致');
        if ((int) $row['id'] <= max((int) $policy['source_after_id'], (int) $policy['resume_after_id'])
            || (int) $row['create_time'] < (int) $policy['enabled_at'])return ['recorded' => 0, 'skipped' => 'before_activation'];
        $amount = NoticeEventCatalog::decimal($row['account_data']);
        if ($amount === '0.00')return ['recorded' => 0, 'skipped' => 'zero'];
        $expires = (int) $row['create_time'] + (int) $policy['ttl_seconds'];
        if ($expires <= time())return ['recorded' => 0, 'skipped' => 'expired'];
        $snapshot = ['source_id' => $id, 'site_id' => $site, 'member_id' => $member, 'account_type' => $type, 'account_data' => $amount,
            'account_sum' => NoticeEventCatalog::decimal($row['account_sum']), 'occurred_at' => (int) $row['create_time']];
        $recorded = 0;
        Db::transaction(function () use ($site, $member, $id, $key, $snapshot, $expires, $policy, &$recorded) {
            // Lock the ledger fact itself: duplicate event listeners, retries and a compensation scan serialize here.
            if (!Db::name('member_account_log')->where('site_id', $site)->where('id', $id)->lock(true)->find())return;
            foreach (NoticeEventCatalog::CHANNELS as $channel) {
                if (empty($policy['events'][$key]['channels'][$channel]))continue;
                $recorded += NoticeTaskRuntime::recordIntent($site, 'member_account_log', $id, $member, $key, $channel, $snapshot, time(), $expires);
            }
        });
        return ['recorded' => $recorded];
    }

    /** Called by a bounded schedule, never by MemberAccount. No runtime cursor is stored as business config. */
    public function recover(int $limit = 50, ?int $siteId = null): array
    {
        if (NoticeTaskRuntime::inTransaction())throw new CommonException('通知只能在事务提交后执行');
        $limit = max(1, min(200, $limit));
        $summary = ['sites' => 0, 'reconstructed' => 0, 'processed' => 0, 'failed_sites' => 0];
        $namespace = hash('sha256', (string) Db::connect()->getConfig('database').':'.(string) Db::connect()->getConfig('prefix'));
        $sites = [$siteId];
        if ($siteId === null) {
            $cursor = (int) Cache::get('hsx_lead_fund_sites_'.$namespace, 0);
            $sites = Db::name('site')->where('status', 1)->where('site_id', '>', $cursor)->order('site_id')->limit(10)->column('site_id');
            if (!$sites)$sites = Db::name('site')->where('status', 1)->order('site_id')->limit(10)->column('site_id');
            if ($sites)Cache::set('hsx_lead_fund_sites_'.$namespace, (int) end($sites), 3600);
        }
        foreach ($sites as $site) {
            $site = (int) $site;
            try {
                if (!AccountNoticePolicyService::installed($site))continue;
                $policy = AccountNoticePolicyService::raw($site);
                if (empty($policy['enabled']))continue;
                $summary['sites']++;
                $lock = 'hsx_fn_'.substr(hash('sha256', $namespace.':'.$site), 0, 48);
                if ((int) (Db::query('SELECT GET_LOCK(?,0) AS acquired', [$lock])[0]['acquired'] ?? 0) !== 1)continue;
                try {
                    $lower = max((int) $policy['source_after_id'], (int) $policy['resume_after_id']);
                    $cache = 'hsx_lead_fund_scan_'.$namespace.'_'.$site;
                    $cursor = max($lower, (int) Cache::get($cache, 0));
                    $rows = Db::name('member_account_log')->where('site_id', $site)->where('id', '>', $cursor)->where('create_time', '>=', max((int) $policy['enabled_at'], time() - (int) $policy['ttl_seconds']))->whereIn('account_type', [
                            'balance',
                            'money',
                            'commission'
                        ])->order('id')->limit($limit)->select()->toArray();
                    foreach ($rows as $row) {
                        $result = $this->recordAccountEvent($row + ['account_log_id' => (int) $row['id']]);
                        $summary['reconstructed'] += (int) $result['recorded'];
                        $cursor = (int) $row['id'];
                    }
                    Cache::set($cache, count($rows) < $limit ? $lower : $cursor, 3600);
                    $summary['reconstructed'] += CooperationResponseNotices::recoverSite($site, $limit);
                    $summary['processed'] += (new NoticeTaskRuntime($this->dispatcher))->recoverSite($site, $limit);
                } finally {
                    Db::query('SELECT RELEASE_LOCK(?) AS released', [$lock]);
                }
            } catch (\Throwable $error) {
                $summary['failed_sites']++;
                Log::warning('hsx_lead资金通知补偿未完成', ['site_id' => $site, 'error_type' => get_class($error)]);
            }
        }return $summary;
    }

    public function process(int $site, int $id): array
    {
        return (new NoticeTaskRuntime($this->dispatcher))->process($site, $id);
    }
    public function page(Context $context, array $filters = []): array
    {
        return (new NoticeTaskRuntime())->page($context, $filters);
    }
    public function detail(Context $context, int $id): array
    {
        return (new NoticeTaskRuntime())->detail($context, $id);
    }
    public function retry(Context $context, int $id, array $input): array
    {
        return (new NoticeTaskRuntime())->retry($context, $id, $input);
    }
    public static function templateData(int $site, int $id, int $attemptId, string $channel, string $key, string $token): array
    {
        return NoticeTaskRuntime::templateData($site, $id, $attemptId, $channel, $key, $token);
    }
    public static function statusName(string $status): string
    {
        return NoticeTaskRuntime::statusName($status);
    }
    private static function positive($value): int
    {
        return (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,15}$/D', (string) $value) ? (int) $value : 0;
    }
}
