<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\{Context,OwnershipScope};
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** Per-person current direct relationships and lifetime original-provider awards. No writes. */
final class InviterSummaryReadService
{
    private const CHUNK = 500;
    public function __construct(private Context $context)
    {
    }

    public function lists(array $params = []): array
    {
        $this->context->permission('hsx_lead_promotion_relations');
        if (!$this->context->ownership()->scope()['all_data'])throw new CommonException('推广人汇总需要全站业务查看权限');
        $role = Data::text($params, 'role', 20);
        if (!in_array($role, ['', 'normal', 'provider'], true))throw new CommonException('请选择有效的推广身份');
        $site = $this->context->site;
        $query = Db::name('member')->where('site_id', $site)->where('is_del', 0)->where('delete_time', 0);
        $providerIds = $this->providerIds();
        if ($role === 'provider')$query->whereIn('member_id', $providerIds ?: [0]);
        elseif ($role === 'normal' && $providerIds)$query->whereNotIn('member_id', $providerIds);
        if (!in_array($params['provider_member_id'] ?? '', ['', 0, '0'], true))$query->where('member_id', OwnershipScope::ownerId($params['provider_member_id']));
        $keyword = Data::text($params, 'keyword', 100);
        if ($keyword !== '') {
            $like = '%'.addcslashes($keyword, '\\%_').'%';
            $owners = Data::query('merchant', $site)->whereLike('name|contact_name', $like)->column('member_id');
            $memberId = \addon\hsx_lead\app\support\AdminKeywordSearch::memberId($keyword);
            $query->where(function ($part) use ($like, $owners, $memberId): void {
                $part->whereLike('nickname|username|mobile|member_no', $like);
                if ($memberId !== null)$part->whereOr('member_id', $memberId);
                if ($owners)$part->whereOr('member_id', 'in', $owners);
            });
        }
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $total = (int) (clone $query)->count();
        $members = $query->field('member_id,member_no,nickname,username,mobile,last_visit_time,login_time,login_count')->order('member_id desc')->page($page, $limit)->select()->toArray();
        $ids = array_map('intval', array_column($members, 'member_id'));
        $relationships = $this->relationshipTotals($ids);
        $rewards = (new PromotionTreeReadService($this->context))->rewardTotals($ids);
        $indirect = [];
        $prefix = (string) config('database.connections.mysql.prefix');
        if ($ids)foreach (Db::name('member')->alias('c')->join([$prefix.'member' => 'p'], 'p.site_id=c.site_id AND p.member_id=c.pid')->where('c.site_id', $site)->whereIn('p.pid', $ids)->where('c.is_del', 0)->where('c.delete_time', 0)->where('p.is_del', 0)->where('p.delete_time', 0)->whereRaw('c.member_id<>p.pid AND p.member_id<>p.pid AND c.member_id<>c.pid')->field('p.pid,COUNT(*) AS n')->group('p.pid')->select()->toArray() as $row)$indirect[(int) $row['pid']] = (int) $row['n'];
        $rows = [];
        foreach ($members as $member) {
            $id = (int) $member['member_id'];
            $phone = (string) $member['mobile'];
            $rows[] = ['member_id' => $id, 'member_no' => (string) $member['member_no'], 'name' => trim((string) $member['nickname']) ?: trim((string) $member['username']) ?: '本站会员',
                'mobile_masked' => strlen($phone) >= 7 ? substr($phone, 0, 3).'****'.substr($phone, -4) : '',
                'role' => in_array($id, $providerIds, true) ? 'provider' : 'normal',
                'direct_invite_count' => $relationships[$id]['total'] ?? 0, 'indirect_invite_count' => $indirect[$id] ?? 0, 'paid_invitee_count' => $relationships[$id]['paid'] ?? 0, 'review_invitee_count' => $relationships[$id]['review'] ?? 0,
                'activity' => ['last_visit_time' => (int) $member['last_visit_time'], 'login_time' => (int) $member['login_time'], 'login_count' => (int) $member['login_count'], 'active_30d' => max((int) $member['last_visit_time'], (int) $member['login_time']) >= time() - 30 * 86400],
                'promotion' => $rewards[$id] ?? PromotionRelationsReadService::emptyRewards()];
        }
        return ['data' => $rows, 'total' => $total, 'current_page' => $page, 'per_page' => $limit,
            'scope' => ['relations' => 'current_direct', 'rewards' => 'lifetime_original_provider']];
    }

    /** Provider status is existing site configuration, not the account's personal member level. */
    private function providerIds(): array
    {
        $ids = [];
        $prefix = PromotionService::PROVIDER_PREFIX;
        foreach (Db::name('sys_config')->where('site_id', $this->context->site)->whereLike('config_key', addcslashes($prefix, '\\%_').'%')->field('config_key,value')->select()->toArray() as $row) {
            $id = substr($row['config_key'], strlen($prefix));
            if (!ctype_digit($id) || (int) $id <= 0)continue;
            $value = Data::decode($row['value']);
            $status = (string) ($value['status'] ?? (!empty($value['enabled']) ? 'approved' : 'disabled'));
            if ($status === 'approved')$ids[(int) $id] = (int) $id;
        }return array_values($ids);
    }

    private function relationshipTotals(array $providerIds): array
    {
        if (!$providerIds)return [];
        $totals = [];
        $cursor = 0;
        $facts = new PromotionRelationsReadService($this->context);
        $query = Db::name('member')->where('site_id', $this->context->site)->whereIn('pid', $providerIds)->where('is_del', 0)->where('delete_time', 0)->whereRaw('member_id<>pid');
        do {
            $children = (clone $query)->where('member_id', '>', $cursor)->field('member_id,pid')->order('member_id')->limit(self::CHUNK)->select()->toArray();
            if (!$children)break;
            $cursor = (int) end($children)['member_id'];
            $payments = $facts->memberPaymentFacts(array_map('intval', array_column($children, 'member_id')));
            foreach ($children as $child) {
                $parent = (int) $child['pid'];
                $totals[$parent] = $totals[$parent] ?? ['total' => 0, 'paid' => 0, 'review' => 0];
                $totals[$parent]['total']++;
                $payment = $payments[(int) $child['member_id']];
                if ($payment['status'] === 'review')$totals[$parent]['review']++;
                elseif ($payment['status'] === 'paid' && $payment['service_net'] !== null && Rules::cents($payment['service_net']) > 0)$totals[$parent]['paid']++;
            }
        } while (count($children) === self::CHUNK);
        return $totals;
    }

}
