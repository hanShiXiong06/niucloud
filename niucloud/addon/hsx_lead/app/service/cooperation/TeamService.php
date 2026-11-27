<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\{Cache,Db};

/** A member authenticates; a tenant-scoped merchant owns its shared rights. */
final class TeamService
{
    private const TABLE = 'hsx_lead_merchant_member';
    public function __construct(private Context $context)
    {
    }

    public static function resolve(Context $ctx, bool $required = true, bool $lock = false): ?array
    {
        if ($ctx->memberId <= 0) throw new CommonException('请先登录会员账号');
        $memberQuery = Db::name('member')->where('site_id', $ctx->site)->where('member_id', $ctx->memberId)
            ->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->field('member_id');
        if ($lock) $memberQuery->lock(true);
        $member = $memberQuery->find();
        if (!$member) throw new CommonException('会员身份已停用或失效，请重新登录');
        $ownedQuery = Data::query('merchant', $ctx->site)->where('member_id', $ctx->memberId)->where('status', 1);
        if ($lock) $ownedQuery->lock(true);
        $owned = $ownedQuery->select()->toArray();
        if (count($owned) > 1) throw new CommonException('当前账号绑定多个负责门店，请联系平台核对，不会自动选择门店');
        $linkQuery = Db::name(self::TABLE)->where('site_id', $ctx->site)->where('member_id', $ctx->memberId)->where('status', 'active');
        if ($lock) $linkQuery->lock(true);
        $links = $linkQuery->select()->toArray();
        if ($owned) {
            foreach ($links as $link) if ((int) $link['merchant_id'] !== (int) $owned[0]['id']) throw new CommonException('当前账号同时关联多个主体，请联系平台核对，不会自动切换门店');
            return $owned[0] + ['team_role' => 'owner', 'is_owner' => true, 'owner_member_id' => $ctx->memberId];
        }
        if (count($links) > 1) throw new CommonException('账号存在多个有效主体，请联系平台核对');
        if ($links) {
            $merchantQuery = Data::query('merchant', $ctx->site)->where('id', $links[0]['merchant_id'])->where('status', 1);
            if ($lock) $merchantQuery->lock(true);
            $merchant = $merchantQuery->find();
            if ($merchant) return $merchant + ['team_role' => 'member', 'is_owner' => false, 'owner_member_id' => (int) $merchant['member_id']];
        }
        if ($required) throw new CommonException('尚未加入启用中的门店，请完善门店资料或申请加入');
        return null;
    }

    public static function assertOwner(Context $ctx, ?int $merchantId = null, bool $lock = false): array
    {
        $merchant = self::resolve($ctx, true, $lock);
        if (!$merchant['is_owner'] || ($merchantId !== null && (int) $merchant['id'] !== $merchantId)) throw new CommonException('此操作仅限当前门店负责人');
        return $merchant;
    }

    public function summary(): array
    {
        $merchant = self::resolve($this->context, false);
        if (!$merchant) return ['merchant_id' => 0, 'pending_entity' => $this->pending(), 'members' => [], 'is_owner' => false];
        return $this->present($merchant, false);
    }

    public function pending(bool $lock = false): ?array
    {
        $q = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('member_id', $this->context->memberId)->where('status', 'pending');
        if ($lock) $q->lock(true);
        $link = $q->order('id desc')->find();
        if (!$link) return null;
        $merchant = Data::query('merchant', $this->context->site)->where('id', $link['merchant_id'])->field('id,name')->find();
        return $merchant ? [
            'merchant_id' => (int) $merchant['id'],
            'name' => $merchant['name'],
            'status' => 'pending',
            'create_time' => (int) $link['create_time']
        ] : null;
    }

    public function lookup(string $code): array
    {
        // Identification is not an invitation secret; no contact, members or inventory is exposed.
        $this->throttle('lookup', 30);
        $merchant = $this->byCode($code);
        $membership = $this->membership((int) $merchant['id']);
        $canJoin = $membership['unlimited'] || self::usedAccounts($this->context->site, $merchant) < (int) $membership['account_limit'];
        return ['merchant_id' => (int) $merchant['id'], 'name' => $merchant['name'], 'can_join' => $canJoin,
            'join_message' => $canJoin ? '提交申请后，由门店负责人确认' : '这家门店暂时没有可用的店员名额，请联系负责人升级门店会员后再试'];
    }

    public function join(array $input): array
    {
        $this->throttle('join', 10);
        $code = $this->normalizeCode((string) ($input['subject_code'] ?? ''));
        Db::transaction(function () use ($code): void {
            $this->lockSite();
            $member = $this->lockMember($this->context->memberId);
            if (self::resolve($this->context, false, true)) throw new CommonException('当前账号已有门店，请先处理原门店关系，不会自动覆盖');
            $merchant = $this->byCode($code, true);
            $this->assertAvailable($merchant, true);
            $pending = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('member_id', $member['member_id'])->where('status', 'pending')->lock(true)->find();
            if ($pending && (int) $pending['merchant_id'] !== (int) $merchant['id']) throw new CommonException('已有待确认的加入申请，请先撤销原申请');
            $old = $this->link((int) $merchant['id'], (int) $member['member_id'], true);
            if ($old && $old['status'] === 'pending') return;
            $state = $this->membership((int) $merchant['id'], true);
            if (!$state['unlimited']
                && self::usedAccounts($this->context->site, $merchant, true) >= (int) $state['account_limit']) throw new CommonException('这家门店的账号名额已用完，请负责人升级会员后再加入');
            $values = [
                'role' => 'member',
                'status' => 'pending',
                'create_time' => time(),
                'update_time' => time(),
                'reviewed_at' => 0,
                'reviewer_member_id' => 0,
                'last_actor_member_id' => $this->context->memberId
            ];
            if ($old) Db::name(self::TABLE)->where('id', $old['id'])->update($values);
            else Db::name(self::TABLE)->insert($values + [
                    'site_id' => $this->context->site,
                    'merchant_id' => $merchant['id'],
                    'member_id' => $member['member_id']
                ]);
            $this->context->audit('merchant_team', (int) $merchant['id'], 'join_requested', ['member_id' => $member['member_id']]);
        });
        return $this->summary();
    }

    public function review(int $memberId, bool $approve): array
    {
        $merchant = self::assertOwner($this->context);
        $this->decide((int) $merchant['id'], $memberId, $approve, false);
        return $this->summary();
    }

    public function remove(int $memberId): array
    {
        $merchant = self::assertOwner($this->context);
        $this->removeFrom((int) $merchant['id'], $memberId, false);
        return $this->summary();
    }

    public function leave(): array
    {
        Db::transaction(function (): void {
            $this->lockSite();
            $this->lockMember($this->context->memberId);
            $merchant = self::resolve($this->context, false, true);
            if ($merchant && $merchant['is_owner']) throw new CommonException('负责人不能直接退出，请先联系平台处理主体责任和未结业务');
            $link = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('member_id', $this->context->memberId)->whereIn('status', ['active', 'pending'])->lock(true)->find();
            if (!$link) return;
            if ($link['status'] === 'active') $this->assertNoOpenInternalAssignments((int) $link['merchant_id'], $this->context->memberId);
            Db::name(self::TABLE)->where('id', $link['id'])->update([
                    'status' => 'removed',
                    'update_time' => time(),
                    'last_actor_member_id' => $this->context->memberId
                ]);
            $this->context->audit('merchant_team', (int) $link['merchant_id'], $link['status'] === 'pending' ? 'join_cancelled' : 'member_left', ['member_id' => $this->context->memberId]);
        });
        return $this->summary();
    }

    public function adminList(array $params = []): array
    {
        $this->context->permission('hsx_lead_entity_manage');
        $q = Data::query('merchant', $this->context->site);
        $keyword = Data::text($params, 'keyword', 100);
        \addon\hsx_lead\app\support\AdminKeywordSearch::merchants($q, $this->context->site, $keyword, true);
        if (empty($params['level_code'])) {
            // Present only the SQL page. In particular, do not generate subject
            // codes or calculate membership for every shop just to show 20 rows.
            $page = Data::page($q, $params);
            $config = $page['data'] ? (new ConfigService($this->context))->get() : null;
            $page['data'] = array_map(fn (array $merchant): array => $this->present($merchant, true, false, $config), $page['data']);
            return $page + ['tiers' => $config['membership_levels'] ?? MembershipCatalogService::levels($this->context->site)];
        }
        // Membership is derived from verified receipts/refunds, not the
        // possibly stale partner.membership_level cache. Scan bounded batches
        // using the existing read projection; only present the requested page.
        // This keeps complete filter totals without per-shop ledger queries or
        // generating subject codes for every matching shop.
        $page = Store::pageArray([], $params);
        $offset = ($page['current_page'] - 1) * $page['per_page'];
        $config = (new ConfigService($this->context))->get();
        $lastId = null;
        $selected = [];
        $now = time();
        do {
            $batchQuery = clone $q;
            if ($lastId !== null)$batchQuery->where('id', '<', $lastId);
            $batch = $batchQuery->order('id desc')->limit(200)->select()->toArray();
            if (!$batch)break;
            $states = EntityMembershipService::states($this->context->site, array_column($batch, 'id'), $config, $now);
            foreach ($batch as $merchant) {
                $state = $states[(int) $merchant['id']] ?? null;
                if ($state === null || $state['level_code'] !== $params['level_code'])continue;
                if ($page['total'] >= $offset && count($selected) < $page['per_page'])$selected[] = [$merchant, $state];
                $page['total']++;
            }
            $lastId = (int) $batch[count($batch) - 1]['id'];
        } while (count($batch) === 200);
        foreach ($selected as [$merchant,$state])$page['data'][] = $this->present($merchant, true, false, $config, $state);
        return $page + ['tiers' => $config['membership_levels'] ?? []];
    }

    public function adminDetail(int $id): array
    {
        $this->context->permission('hsx_lead_entity_detail');
        return $this->present(Data::row('merchant', $this->context->site, $id), true);
    }
    public function adminReview(int $merchantId, int $memberId, bool $approve): array
    {
        $this->context->permission('hsx_lead_entity_review');
        $this->decide($merchantId, $memberId, $approve, true);
        return $this->present(Data::row('merchant', $this->context->site, $merchantId), true);
    }
    public function adminRemove(int $merchantId, int $memberId): array
    {
        $this->context->permission('hsx_lead_entity_remove');
        $this->removeFrom($merchantId, $memberId, true);
        return $this->present(Data::row('merchant', $this->context->site, $merchantId), true);
    }

    public static function usedAccounts(int $site, array $merchant, bool $lock = false): int
    {
        $query = Db::name(self::TABLE)->where('site_id', $site)->where('merchant_id', $merchant['id'])->where('member_id', '<>', $merchant['member_id'])->where('status', 'active');
        // RR snapshots can predate a waited-on site lock. Seat decisions need
        // a current locking read, not an aggregate from that old snapshot.
        $staff = $lock ? count($query->lock(true)->column('member_id')) : (int) $query->count();
        return $staff + ((int) $merchant['member_id'] > 0 ? 1 : 0); // Include the responsible owner's login, if configured.
    }

    /** Real, currently usable accounts, not purchased seats or pending invitations. */
    public static function assignmentMembers(int $site, array $merchant, int $excludeMemberId = 0, bool $lock = false, int $onlyMemberId = 0): array
    {
        if ((int) ($merchant['site_id'] ?? 0) !== $site || (int) ($merchant['status'] ?? 0) !== 1) return [];
        $links = Db::name(self::TABLE)->where('site_id', $site)->where('merchant_id', (int) $merchant['id'])->where('status', 'active');
        if ($lock) $links->lock(true);
        $ids = array_values(array_unique(array_merge([(int) $merchant['member_id']], array_map('intval', $links->column('member_id')))));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0
                && $id !== $excludeMemberId
                && ($onlyMemberId <= 0 || $id === $onlyMemberId)));
        if (!$ids) return [];
        $query = Db::name('member')->where('site_id', $site)->whereIn('member_id', $ids)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->field('member_id,nickname,member_no');
        if ($lock) $query->lock(true);
        $result = [];
        foreach ($query->order('member_id')->select()->toArray() as $member) {
            // A stale or ambiguous cross-merchant binding is not an eligible recipient.
            try {
                $resolved = self::resolve(new Context($site, 0, (int) $member['member_id']), false, $lock);
            } catch (CommonException $ignored) {
                continue;
            }
            if (!$resolved || (int) $resolved['id'] !== (int) $merchant['id']) continue;
            $owner = (int) $merchant['member_id'] === (int) $member['member_id'];
            $result[] = ['member_id' => (int) $member['member_id'], 'name' => trim((string) $member['nickname']) ?: ((string) $member['member_no'] ?: '门店成员'),
                'role' => $owner ? 'owner' : 'employee', 'role_label' => $owner ? '负责人' : '店员'];
        }
        return $result;
    }

    /** Missing keys mean an untouched historic shared queue; malformed new snapshots fail closed. */
    public static function internalAssignment(array $lead, bool $includeReturned = false): ?array
    {
        $rule = Data::decode($lead['rule_json'] ?? '');
        $current = (int) ($lead['receiver_id'] ?? 0) > 0 && (int) $lead['receiver_id'] === (int) ($lead['publisher_id'] ?? 0);
        $returned = $includeReturned
            && (int) ($lead['receiver_id'] ?? 0) === 0
            && in_array($lead['status'] ?? '', ['selecting', 'cancelled'], true)
            && ($rule['internal_delivery'] ?? false) === true;
        if (!$current && !$returned) return null;
        if (!array_key_exists('internal_assignee_member_id', $rule)) return null;
        $assignee = $rule['internal_assignee_member_id'];
        $publisher = $rule['internal_publisher_member_id'] ?? 0;
        return ['assignee_member_id' => is_int($assignee) && $assignee > 0 ? $assignee : 0, 'publisher_member_id' => is_int($publisher) && $publisher > 0 ? $publisher : 0];
    }

    /** Use the same predicate before pagination and dashboard counts. */
    public static function scopeIncoming($query, int $memberId)
    {
        $json = "IF(JSON_VALID(rule_json),rule_json,'{}')";
        $assignee = "JSON_EXTRACT($json, '$.internal_assignee_member_id')";
        $publisher = "JSON_EXTRACT($json, '$.internal_publisher_member_id')";
        return $query->whereRaw("(publisher_id <> receiver_id OR JSON_CONTAINS_PATH($json, 'one', '$.internal_assignee_member_id') = 0 OR (JSON_TYPE($assignee) = 'INTEGER' AND JSON_TYPE($publisher) = 'INTEGER' AND CAST(JSON_UNQUOTE($publisher) AS SIGNED) > 0 AND JSON_UNQUOTE($publisher) <> JSON_UNQUOTE($assignee) AND CAST(JSON_UNQUOTE($assignee) AS UNSIGNED) = ?))", [$memberId]);
    }

    private function decide(int $merchantId, int $memberId, bool $approve, bool $admin): void
    {
        Db::transaction(function () use ($merchantId, $memberId, $approve, $admin): void {
            $this->lockSite();
            $merchant = Data::row('merchant', $this->context->site, $merchantId, true);
            if (!$admin) self::assertOwner($this->context, $merchantId, true);
            if ((int) $merchant['member_id'] === $memberId) throw new CommonException('负责人账号不需要审批');
            $link = $this->link($merchantId, $memberId, true);
            if (!$link || $link['status'] !== 'pending') throw new CommonException('申请已处理或已撤销，请刷新列表');
            if ($approve) {
                $this->lockMember($memberId);
                $this->assertAvailable($merchant, true);
                if (self::resolve(new Context($this->context->site, 0, $memberId), false, true)) throw new CommonException('该账号已加入其他门店，不能重复关联');
                $state = $this->membership($merchantId, true);
                if (!$state['unlimited']
                    && self::usedAccounts($this->context->site, $merchant, true) >= (int) $state['account_limit']) throw new CommonException('账号名额已用完，未批准；请先升级或移除不再使用的成员');
            }
            Db::name(self::TABLE)->where('id', $link['id'])->update([
                    'status' => $approve ? 'active' : 'rejected',
                    'reviewed_at' => time(),
                    'reviewer_member_id' => $this->context->memberId,
                    'last_actor_member_id' => $this->context->memberId,
                    'update_time' => time()
                ]);
            $this->context->audit('merchant_team', $merchantId, $approve ? 'member_approved' : 'member_rejected', ['member_id' => $memberId]);
        });
    }

    private function removeFrom(int $merchantId, int $memberId, bool $admin): void
    {
        Db::transaction(function () use ($merchantId, $memberId, $admin): void {
            $this->lockSite();
            $merchant = Data::row('merchant', $this->context->site, $merchantId, true);
            if (!$admin) self::assertOwner($this->context, $merchantId, true);
            if ((int) $merchant['member_id'] === $memberId) throw new CommonException('不能移除门店负责人');
            $link = $this->link($merchantId, $memberId, true);
            if (!$link || !in_array($link['status'], ['active', 'pending'], true)) throw new CommonException('成员已移除或申请已处理，请刷新');
            if ($link['status'] === 'active') $this->assertNoOpenInternalAssignments($merchantId, $memberId);
            Db::name(self::TABLE)->where('id', $link['id'])->update([
                    'status' => 'removed',
                    'last_actor_member_id' => $this->context->memberId,
                    'update_time' => time()
                ]);
            $this->context->audit('merchant_team', $merchantId, 'member_removed', ['member_id' => $memberId]);
        });
    }

    /** A selected colleague must finish or return their work before losing the only write authority. */
    private function assertNoOpenInternalAssignments(int $merchantId, int $memberId): void
    {
        $query = Store::query('collaboration', $this->context->site)->where('publisher_id', $merchantId)->where('receiver_id', $merchantId)->whereIn('status', ['assigned', 'following']);
        self::scopeIncoming($query, $memberId);
        foreach ($query->field('id,publisher_id,receiver_id,status,rule_json')->lock(true)->select()->toArray() as $lead) {
            $assignment = self::internalAssignment($lead);
            if ($assignment === null
                || $assignment['assignee_member_id'] !== $memberId
                || $assignment['publisher_member_id'] <= 0
                || $assignment['publisher_member_id'] === $memberId) continue;
            throw new CommonException($lead['status'] === 'assigned' ? '该成员还有待接单的本店交付，请先退回或由发布方撤回，再退出或移除成员' : '该成员还有正在跟进的本店交付，请先反馈成交或未成交结果，再退出或移除成员');
        }
    }

    private function present(array $merchant, bool $admin, bool $includeMembers = true, ?array $config = null, ?array $membershipState = null): array
    {
        $id = (int) $merchant['id'];
        $owner = (int) $merchant['member_id'];
        $isOwner = $owner === $this->context->memberId;
        if ($admin || $isOwner) $merchant['subject_code'] = $this->ensureCode($id);
        $state = $membershipState ?? $this->membership($id, false, $config);
        $state['used_accounts'] = self::usedAccounts($this->context->site, $merchant);
        $state['can_manage'] = $isOwner || $admin;
        $state['state'] = $state['active'] ? 'active' : ((int) $state['expires_at'] > 0 ? 'expired' : 'locked');
        $ownerMember = Db::name('member')->where('site_id', $this->context->site)->where('member_id', $owner)->field('member_id,nickname,mobile')->find();
        $row = ['id' => $id, 'merchant_id' => $id, 'name' => $merchant['name'], 'subject_code' => ($isOwner || $admin) ? ($merchant['subject_code'] ?? '') : '', 'owner_member_id' => $owner,
            'owner_name' => (string) ($ownerMember['nickname'] ?? $merchant['contact_name']), 'owner_mobile' => ($isOwner || $admin) ? (string) ($ownerMember['mobile'] ?? $merchant['mobile']) : '',
            'is_owner' => $isOwner, 'role' => $isOwner ? 'owner' : 'member', 'membership' => $state, 'members' => [],
            'pending_count' => (int) Db::name(self::TABLE)->where('site_id', $this->context->site)->where('merchant_id', $id)->where('status', 'pending')->count(),
            'permissions' => ['can_manage_members' => $isOwner, 'can_manage_billing' => $isOwner, 'can_manage_erp' => $isOwner, 'can_withdraw' => $isOwner]];
        $row += array_intersect_key(Data::regionNames($merchant), array_flip(['region_text', 'status']));
        if ($includeMembers) {
            $memberQuery = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('merchant_id', $id)->where('member_id', '<>', $owner);
            if (!$isOwner && !$admin) $memberQuery->where('status', 'active');
            $rows = $memberQuery->order('id desc')->select()->toArray();
            if ($owner > 0) $row['members'][] = [
                'member_id' => $owner,
                'nickname' => $row['owner_name'],
                'name' => $row['owner_name'],
                'mobile' => $row['owner_mobile'],
                'role' => 'owner',
                'status' => 'active',
                'create_time' => (int) $merchant['create_time'],
                'reviewed_at' => 0
            ];
            $ids = array_column($rows, 'member_id');
            $members = $ids ? Db::name('member')->where('site_id', $this->context->site)->whereIn('member_id', $ids)->field('member_id,nickname,mobile')->select()->toArray() : [];
            $members = array_column($members, null, 'member_id');
            foreach ($rows as $link) {
                $m = $members[$link['member_id']] ?? [];
                $row['members'][] = [
                    'member_id' => (int) $link['member_id'],
                    'nickname' => (string) ($m['nickname'] ?? '已注销账号'),
                    'name' => (string) ($m['nickname'] ?? '已注销账号'),
                    'mobile' => ($isOwner || $admin || (int) $link['member_id'] === $this->context->memberId) ? (string) ($m['mobile'] ?? '') : '',
                    'role' => 'member',
                    'status' => $link['status'],
                    'create_time' => (int) $link['create_time'],
                    'reviewed_at' => (int) $link['reviewed_at']
                ];
            }
        }
        return $row;
    }

    private function membership(int $merchantId, bool $lock = false, ?array $config = null): array
    {
        return EntityMembershipService::state($this->context->site, $merchantId, $config ?? (new ConfigService($this->context))->get($lock), null, $lock);
    }

    private function ensureCode(int $id): string
    {
        return Db::transaction(function () use ($id): string {
            $merchant = Data::row('merchant', $this->context->site, $id, true);
            if (!empty($merchant['subject_code'])) return (string) $merchant['subject_code'];
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            for ($attempt = 0;$attempt < 5;$attempt++) {
                $code = '';
                foreach (str_split(random_bytes(10)) as $char) $code .= $alphabet[ord($char) % 32];
                if (Data::query('merchant', $this->context->site)->where('subject_code', $code)->count()) continue;
                Data::query('merchant', $this->context->site)->where('id', $id)->update(['subject_code' => $code]);
                return $code;
            }
            throw new CommonException('门店码生成繁忙，请稍后重试');
        });
    }

    private function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-HJ-NP-Z2-9]{10}$/D', $code)) throw new CommonException('请输入10位门店码，可请负责人在“我的—门店与成员”复制');
        return $code;
    }
    private function byCode(string $code, bool $lock = false): array
    {
        $code = $this->normalizeCode($code);
        $q = Data::query('merchant', $this->context->site)->where('subject_code', $code)->where('status', 1);
        if ($lock) $q->lock(true);
        $merchant = $q->find();
        if (!$merchant) throw new CommonException('未找到这家门店，请核对门店码和当前站点');
        $this->assertAvailable($merchant, $lock);
        return $merchant;
    }
    private function assertAvailable(array $merchant, bool $lock = false): void
    {
        $partner = Store::partner($this->context->site, (int) $merchant['id'], $lock);
        if ((int) $merchant['status'] !== 1 || (int) $partner['blocked_at'] > 0) throw new CommonException('这家门店已暂停加入，请联系负责人');
        $ownerQuery = Db::name('member')->where('site_id', $this->context->site)->where('member_id', $merchant['member_id'])->where('status', 1)->where('is_del', 0)->where('delete_time', 0);
        if ($lock) $ownerQuery->lock(true);
        if ((int) $merchant['member_id'] <= 0 || !$ownerQuery->field('member_id')->find()) throw new CommonException('这家门店尚未绑定有效负责人，暂不能申请加入，请联系平台核对');
    }
    private function lockSite(): void
    {
        if (!Db::name('site')->where('site_id', $this->context->site)->lock(true)->field('site_id')->find()) throw new CommonException('站点不存在');
    }
    private function lockMember(int $id): array
    {
        $member = Db::name('member')->where('site_id', $this->context->site)->where('member_id', $id)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->lock(true)->find();
        if (!$member) throw new CommonException('账号已停用，未完成关联');
        return $member;
    }
    private function link(int $merchantId, int $memberId, bool $lock = false): ?array
    {
        $q = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('merchant_id', $merchantId)->where('member_id', $memberId);
        if ($lock) $q->lock(true);
        return $q->find() ?: null;
    }
    private function throttle(string $action, int $limit): void
    {
        $key = 'hsx_lead.team.'.$action.'.'.$this->context->site.'.'.$this->context->memberId.'.'.intdiv(time(), 60);
        if (!Cache::has($key)) Cache::set($key, 0, 70);
        if ((int) Cache::inc($key) > $limit) throw new CommonException('操作较频繁，请稍后再试');
    }
}
