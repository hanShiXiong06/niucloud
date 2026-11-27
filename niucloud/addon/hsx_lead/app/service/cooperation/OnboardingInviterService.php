<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\PayDict;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\{Cache,Db};

/** A one-time optional referral choice, atomically committed with the first store profile. */
final class OnboardingInviterService
{
    public const MARKER_PREFIX = 'HSX_LEAD_ONBOARDING_INVITER_';
    public function __construct(private Context $context)
    {
    }

    /** A GET only describes availability; it never consumes the optional choice. */
    public function state(?string $outcome = null, string $message = ''): array
    {
        $site = $this->context->site;
        $member = $this->member(false);
        $id = (int) $member['member_id'];
        $marker = $this->configuration(self::MARKER_PREFIX.$id);
        $hasProfile = Data::query('merchant', $site)->where('member_id', $id)->value('id') !== null;
        $staff = Db::name('hsx_lead_merchant_member')->where('site_id', $site)->where('member_id', $id)->whereIn('status', ['active', 'pending'])->value('id') !== null;
        $locked = (int) $member['pid'] > 0 || $marker !== null || $hasProfile || $staff;
        $current = null;
        if ((int) $member['pid'] > 0) {
            $inviter = Db::name('member')->where('site_id', $site)->where('member_id', (int) $member['pid'])->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->field('member_no,nickname,username')->find();
            if ($inviter)$current = ['member_no' => (string) $inviter['member_no'], 'name' => trim((string) $inviter['nickname']) ?: trim((string) $inviter['username']) ?: '本站会员'];
        }
        return ['editable' => !$locked, 'locked' => $locked, 'current_inviter' => $current, 'outcome' => $outcome, 'message' => $message];
    }

    /** PHP-only participant in saveProfile's existing transaction, not a standalone binding endpoint. */
    public function finishFirstProfile(int $merchantId, bool $isNewProfile, array $input): array
    {
        if (!Db::connect()->getPdo()->inTransaction())throw new CommonException('邀请关系须与门店资料一并保存');
        $site = $this->context->site;
        PromotionService::lockSite($site);
        $member = $this->member(true);
        $id = (int) $member['member_id'];
        $merchant = Data::query('merchant', $site)->where('id', $merchantId)->where('member_id', $id)->lock(true)->field('id')->find();
        if (!$merchant)throw new CommonException('门店负责人身份不一致，邀请关系未变更');
        if (!$isNewProfile || $this->configuration(self::MARKER_PREFIX.$id, true) !== null)return $this->state('locked');
        // Staff invitations only consume store seats. They never become referrals.
        if (Db::name('hsx_lead_merchant_member')->where('site_id', $site)->where('member_id', $id)->whereIn('status', ['active', 'pending'])->lock(true)->value('id') !== null)return $this->state('locked');

        $oldPid = (int) $member['pid'];
        $newPid = $oldPid;
        $candidate = null;
        $raw = $input['inviter_member_no'] ?? '';
        $code = is_string($raw) ? trim($raw) : null;
        // Existing registration/share attribution is already bound. A store
        // form cannot replace it, even if the original inviter is unavailable.
        $outcome = $oldPid > 0 ? 'unchanged' : 'empty';
        if ($oldPid === 0 && $code !== null && $code !== '') {
            if (!mb_check_encoding($code, 'UTF-8') || mb_strlen($code) > 255 || preg_match('/[\p{C}]/u', $code))$outcome = 'unavailable';
            else {
                // member_no is an opaque string. Never cast 0001 to 1 or fall back to member_id.
                $matches = Db::name('member')->where('site_id', $site)->whereRaw('BINARY member_no = BINARY ?', [$code])->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->field('member_id,member_no,pid')->lock(true)->limit(2)->select()->toArray();
                if (!$matches)$outcome = 'not_found';
                elseif (count($matches) !== 1)$outcome = 'unavailable';
                else {
                    $candidate = $matches[0];
                    $outcome = $this->candidateOutcome($id, $candidate);
                }
            }
        } elseif ($oldPid === 0 && $code === null)$outcome = 'unavailable';
        if ($outcome === 'eligible') {
            if ($this->hasFinancialHistory($id))$outcome = 'financial_locked';
            elseif ((int) $candidate['member_id'] === $oldPid)$outcome = 'unchanged';
            else {
                $relation = $this->configuration(PromotionService::RELATION_PREFIX.$id, true);
                $value = $relation === null ? [] : self::decodeConfiguration($relation);
                if ($value === null)$outcome = 'unavailable';
                else {
                    $newPid = (int) $candidate['member_id'];
                    $changed = Db::name('member')->where('site_id', $site)->where('member_id', $id)->where('pid', 0)->update(['pid' => $newPid]);
                    if ($changed !== 1)throw new CommonException('邀请关系刚刚发生变化，请重新保存门店资料');
                    $this->write(PromotionService::RELATION_PREFIX.$id, array_replace($value, ['pid' => $newPid, 'bound_at' => time(), 'source' => 'onboarding_manual']));
                    $outcome = 'bound';
                }
            }
        }
        // Always consume on a successful FIRST profile, including blank/unavailable input.
        // A later upload, capability validation or transaction failure rolls this back too.
        $this->write(self::MARKER_PREFIX.$id, ['schema_version' => 'hsx_lead.onboarding_inviter.v1', 'locked_at' => time(), 'merchant_id' => $merchantId, 'outcome' => $outcome]);
        $this->context->audit('promotion_relation', $id, 'onboarding_inviter_consumed', ['merchant_id' => $merchantId, 'old_inviter_member_id' => $oldPid, 'inviter_member_id' => $newPid, 'outcome' => $outcome]);
        $message = match ($outcome) {
            'bound' => '门店资料已保存，邀请人已设置',
            'unchanged' => '门店资料已保存，已保留原邀请人',
            'not_found' => '门店资料已保存，未找到可用的推广ID，原邀请关系保持不变',
            'self','cycle','unavailable' => '门店资料已保存，该推广ID暂不可用，邀请关系未变更',
            'financial_locked' => '门店资料已保存；已有付款或推广记录，原邀请关系保持不变',
            default => '门店资料已保存',
        };
        return $this->state($outcome, $message);
    }

    private function member(bool $lock): array
    {
        if ($this->context->memberId <= 0)throw new CommonException('请先登录会员账号');
        $query = Db::name('member')->where('site_id', $this->context->site)->where('member_id', $this->context->memberId)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->field('member_id,pid');
        if ($lock)$query->lock(true);
        $member = $query->find();
        if (!$member)throw new CommonException('会员身份已停用或失效，请重新登录');
        return $member;
    }
    private function configuration(string $key, bool $lock = false): ?array
    {
        $query = Db::name('sys_config')->where('site_id', $this->context->site)->where('config_key', $key)->field('id,value');
        if ($lock)$query->lock(true);
        $rows = $query->limit(2)->select()->toArray();
        if (count($rows) > 1)throw new CommonException('邀请关系记录重复，请联系平台核对');
        return $rows[0] ?? null;
    }
    private static function decodeConfiguration(array $row): ?array
    {
        if (is_array($row['value']))return $row['value'];
        try {
            $value = json_decode((string) $row['value'], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException$error) {
            return null;
        }return is_array($value) ? $value : null;
    }
    private function write(string $key, array $value): void
    {
        // The authoritative reads above bypass cache, and the site lock serializes
        // writers. Clear a stale cached miss before reusing the framework setter.
        Cache::tag(CoreConfigService::$cache_tag_name.$this->context->site)->clear();
        (new CoreConfigService())->setConfig($this->context->site, $key, $value);
    }
    private function candidateOutcome(int $own, array $candidate): string
    {
        $id = (int) $candidate['member_id'];
        if ($id === $own)return 'self';
        $seen = [];
        $cursor = $candidate;
        while ($cursor) {
            $current = (int) $cursor['member_id'];
            if ($current === $own || isset($seen[$current]) || count($seen) >= 1000)return 'cycle';
            $seen[$current] = true;
            $pid = (int) $cursor['pid'];
            if ($pid <= 0)break;
            $cursor = Db::name('member')->where('site_id', $this->context->site)->where('member_id', $pid)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->field('member_id,pid')->lock(true)->find();
            if (!$cursor)return 'unavailable';
        }
        try {
            $role = PromotionService::resolveRole($this->context->site, $id);
            if (!$role['promotion_allowed'])return 'unavailable';
        } catch (CommonException$error) {
            return 'unavailable';
        }
        return 'eligible';
    }
    private function hasFinancialHistory(int $memberId): bool
    {
        $site = $this->context->site;
        if (Db::name('hsx_lead_promotion_reward')->where('site_id', $site)->where('payer_member_id', $memberId)->value('id') !== null)return true;
        $orders = Store::query('coop_order', $site)->where('member_id', $memberId)->field('id,status,rule_json')->select()->toArray();
        if (!$orders)return false;
        foreach ($orders as $order) {
            $rule = Data::decode($order['rule_json']);
            if ($order['status'] === 'paid' || array_key_exists('promotion_snapshot', $rule) || !empty($rule['requires_manual_review']))return true;
        }
        if (!OrderPaymentReadService::hasTable('pay'))return true;
        $pays = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->whereIn('trade_id', array_column($orders, 'id'))->field('status,out_trade_no')->select()->toArray();
        $numbers = [];
        foreach ($pays as $pay) {
            if ((int) $pay['status'] !== (int) PayDict::STATUS_CANCEL)return true;
            if ((string) $pay['out_trade_no'] !== '')$numbers[] = $pay['out_trade_no'];
        }
        return $numbers && Db::name('member_account_log')->where('site_id', $site)->where('member_id', $memberId)->where('account_type', 'balance')->where('from_type', 'order')->where('account_data', '<', 0)->whereIn('related_id', $numbers)->value('id') !== null;
    }
}
