<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\service\core\site\CoreSiteService;
use core\exception\CommonException;
use think\facade\{Db,Log};

/** Read-only, page-bounded projection of shared store rights onto member accounts. */
final class MemberEntitlementDisplayService
{
    public const KEY = 'hsx_lead_subject';
    public const TITLE = '门店会员权益';
    public const MAX_MEMBERS = 500;
    private const STATUS_TEXT = [
        'active' => '生效中', 'expired' => '已到期', 'not_opened' => '未开通', 'review' => '待核对',
        'inactive' => '已暂停', 'not_joined' => '未加入门店', 'unavailable' => '暂未读取',
    ];

    public function __construct(private Context $context)
    {
    }

    /** No personal level, partner, ledger, grant, cache, or membership state is written here. */
    public function display(array $memberIds): array
    {
        if (!$this->authorized())return [];
        if (count($memberIds) > self::MAX_MEMBERS)throw new CommonException('会员权益展示请按每批最多500个账号读取');
        $ids = [];
        foreach ($memberIds as $id) {
            if ((!is_int($id) && !is_string($id)) || preg_match('/^[1-9][0-9]{0,9}$/D', (string) $id) !== 1 || (float) $id > 4294967295)continue;
            $ids[(int) $id] = (int) $id;
        }
        $ids = array_values($ids);
        $members = [];
        try {
            $accounts = $ids ? Db::name('member')->where('site_id', $this->context->site)->whereIn('member_id', $ids)
                ->field('member_id,status,is_del,delete_time')->select()->toArray() : [];
            $ids = [];
            foreach ($accounts as $account) {
                $id = (int) $account['member_id'];
                $deleted = (int) $account['is_del'] !== 0 || (int) $account['delete_time'] > 0;
                if ($deleted || (int) $account['status'] !== 1) {
                    $members[$id] = self::card('inactive', $deleted ? '账号已删除' : '账号已停用', '', ($deleted ? '已删除' : '已停用').'账号不继承门店共享权益');
                    $members[$id]['status_text'] = $deleted ? '已删除' : '已停用';
                } else $ids[] = $id;
            }
            foreach ($ids as $id)$members[$id] = self::card('not_joined');
            if ($ids) {
                $subjects = $this->subjects($ids);
                $merchantIds = [];
                foreach ($subjects as $subject)if (!empty($subject['merchant_id']))$merchantIds[] = (int) $subject['merchant_id'];
                $merchantIds = array_values(array_unique($merchantIds));
                // Definitions only: do not hydrate business/ERP model catalogs on the member list.
                $config = ['membership_levels' => MembershipCatalogService::levels($this->context->site)];
                $states = $merchantIds ? EntityMembershipService::states($this->context->site, $merchantIds, $config) : [];
                foreach ($subjects as $id => $subject) {
                    if ($subject['review']) {
                        $members[$id] = self::card('review', '主体关联待核对', '', '当前账号存在多个有效门店关联，请管理员核对');
                        continue;
                    }
                    $merchantId = (int) $subject['merchant_id'];
                    if ($merchantId <= 0)continue;
                    if (!isset($states[$merchantId])) {
                        $members[$id] = self::card('unavailable');
                        continue;
                    }
                    $state = $states[$merchantId];
                    $status = (string) ($state['display_status'] ?? '');
                    if (!in_array($status, ['active', 'expired', 'not_opened', 'review', 'inactive'], true)) {
                        $members[$id] = self::card('unavailable');
                        continue;
                    }
                    $context = $subject['name'].' · '.($subject['owner'] ? '负责人' : '店员');
                    $reason = '';
                    if ($status === 'review')$reason = '会员付款或退款正在核对，请以原订单处理结果为准';
                    elseif (!empty($state['cooperation_exited']))$reason = '门店正在退出协作或已退出，当前权益不可使用';
                    $members[$id] = self::card($status, (string) ($state['level_name'] ?? '普通会员'), $context, $reason, (int) ($state['expires_at'] ?? 0));
                    $members[$id]['status_text'] = (string) $state['display_status_text'];
                }
            }
        } catch (\Throwable$error) {
            // Never let a plugin lookup break the official member list, or make
            // an unread payment record appear to be an unopened membership.
            Log::warning('会员门店权益展示读取失败：site_id='.$this->context->site.'，member_count='.count($ids).'，error='.get_class($error));
            foreach ($ids as $id)$members[$id] = self::card('unavailable');
        }
        return ['key' => self::KEY, 'title' => self::TITLE, 'members' => $members];
    }

    private function authorized(): bool
    {
        if ($this->context->uid <= 0)return false;
        try {
            $this->context->permission('hsx_lead_entity_manage');
        } catch (CommonException$error) {
            return false;
        }
        if (!Db::name('addon')->where('key', 'hsx_lead')->where('status', 1)->value('id'))return false;
        return in_array('hsx_lead', (new CoreSiteService())->getAddonKeysBySiteId($this->context->site), true);
    }

    /** Same active-owner/active-seat uniqueness rules as TeamService::resolve, in batches. */
    private function subjects(array $ids): array
    {
        $site = $this->context->site;
        $owned = [];
        $merchants = [];
        $links = [];
        foreach (Data::query('merchant', $site)->whereIn('member_id', $ids)->where('status', 1)->field('id,member_id,name')->select()->toArray() as $merchant) {
            $owned[(int) $merchant['member_id']][] = $merchant;
            $merchants[(int) $merchant['id']] = $merchant;
        }
        $rows = Db::name('hsx_lead_merchant_member')->where('site_id', $site)->whereIn('member_id', $ids)->where('status', 'active')->field('member_id,merchant_id')->select()->toArray();
        $missing = array_values(array_diff(array_unique(array_map('intval', array_column($rows, 'merchant_id'))), array_keys($merchants)));
        if ($missing)foreach (Data::query('merchant', $site)->whereIn('id', $missing)->where('status', 1)->field('id,member_id,name')->select()->toArray() as $merchant)$merchants[(int) $merchant['id']] = $merchant;
        foreach ($rows as $row)$links[(int) $row['member_id']][] = (int) $row['merchant_id'];
        $out = [];
        foreach ($ids as $id) {
            $own = $owned[$id] ?? [];
            $seats = $links[$id] ?? [];
            $review = count($own) > 1;
            if ($own) {
                foreach ($seats as $merchantId)if ($merchantId !== (int) $own[0]['id'])$review = true;
                $merchant = $review ? null : $own[0];
            } else {
                $review = count($seats) > 1;
                $merchant = $review || !$seats ? null : ($merchants[$seats[0]] ?? null);
            }
            $out[$id] = ['merchant_id' => (int) ($merchant['id'] ?? 0), 'name' => (string) ($merchant['name'] ?? ''), 'owner' => $merchant && (int) $merchant['member_id'] === $id, 'review' => $review];
        }
        return $out;
    }

    private static function card(string $status, string $label = '', string $context = '', string $reason = '', int $expires = 0): array
    {
        if ($label === '')$label = $status === 'unavailable' ? '权益暂未读取' : ($status === 'not_joined' ? '未加入门店' : '普通会员');
        if ($status === 'unavailable' && $reason === '')$reason = '门店会员权益暂未读取，请刷新重试；不会按未开通处理';
        $out = ['label' => $label, 'status' => $status, 'status_text' => self::STATUS_TEXT[$status], 'context' => $context, 'expires_at' => $expires];
        if ($reason !== '')$out['reason'] = $reason;
        return $out;
    }
}
