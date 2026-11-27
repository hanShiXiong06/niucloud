<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardItem;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use addon\hsx_member_card\app\support\MemberCardHookResult;
use addon\hsx_member_card\app\support\MemberCardIdempotency;
use app\model\member\Member;
use app\service\admin\member\MemberService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class MemberCardMemberService extends BaseAdminService
{
    public function lists(array $where): array
    {
        $keyword = mb_substr(trim((string)($where['keyword'] ?? '')), 0, 100);
        $query = MemberCard::where([['site_id', '=', $this->site_id]])
            ->whereNotIn('status', ['cancelled', 'refunded']);
        if ($keyword !== '') {
            $query->whereLike('holder_name|holder_mobile|card_no|product_name', '%' . $keyword . '%');
        }
        $page = $query
            ->fieldRaw('member_id,MAX(holder_name) AS holder_name,MAX(holder_mobile) AS holder_mobile,COUNT(*) AS card_count,MAX(create_at) AS latest_card_at')
            ->group('member_id')
            ->order('latest_card_at desc')
            ->paginate([
                'list_rows' => min(100, max(1, (int)($where['limit'] ?? 15))),
                'page' => max(1, (int)($where['page'] ?? 1)),
            ])->toArray();

        $memberIds = array_map('intval', array_column($page['data'] ?? [], 'member_id'));
        $statusRows = $memberIds === [] ? [] : MemberCard::where([['site_id', '=', $this->site_id]])
            ->whereIn('member_id', $memberIds)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->fieldRaw('member_id,status,COUNT(*) AS status_count')
            ->group('member_id,status')->select()->toArray();
        $statusMap = [];
        foreach ($statusRows as $statusRow) {
            $statusMap[(int)$statusRow['member_id']][(string)$statusRow['status']] = (int)$statusRow['status_count'];
        }
        foreach ($page['data'] as &$row) {
            $statuses = $statusMap[(int)$row['member_id']] ?? [];
            $row['display_name'] = trim((string)$row['holder_name']) !== '' ? (string)$row['holder_name'] : (string)$row['holder_mobile'];
            $row['mobile_masked'] = $this->maskMobile((string)$row['holder_mobile']);
            $row['available_card_count'] = (int)($statuses['active'] ?? 0) + (int)($statuses['pending'] ?? 0);
            $row['expired_card_count'] = (int)($statuses['expired'] ?? 0) + (int)($statuses['exhausted'] ?? 0);
        }
        unset($row);
        return $page;
    }

    public function options(array $where): array
    {
        $keyword = mb_substr(trim((string)($where['keyword'] ?? '')), 0, 100);
        $query = Member::where([['site_id', '=', $this->site_id]])
            ->field('member_id,member_no,username,nickname,mobile,headimg,status,create_time');
        if ($keyword !== '') $query->whereLike('member_no|username|nickname|mobile', '%' . $keyword . '%');
        $rows = $query->order('member_id desc')->limit(min(50, max(1, (int)($where['limit'] ?? 20))))->select()->toArray();
        $memberIds = array_map('intval', array_column($rows, 'member_id'));
        $cardCountRows = $memberIds === [] ? [] : MemberCard::where([['site_id', '=', $this->site_id]])
            ->whereIn('member_id', $memberIds)->whereIn('status', ['pending', 'active', 'exhausted', 'expired', 'frozen'])
            ->fieldRaw('member_id,COUNT(*) AS card_count')->group('member_id')->select()->toArray();
        $cardCounts = [];
        foreach ($cardCountRows as $cardCountRow) {
            $cardCounts[(int)$cardCountRow['member_id']] = (int)$cardCountRow['card_count'];
        }
        foreach ($rows as &$row) {
            $row['display_name'] = $this->displayName($row);
            $row['mobile_masked'] = $this->maskMobile((string)($row['mobile'] ?? ''));
            $row['card_count'] = (int)($cardCounts[(int)$row['member_id']] ?? 0);
            unset($row['username']);
        }
        unset($row);
        return $rows;
    }

    public function quickCreate(array $data): array
    {
        MemberCardIdempotency::normalize($data['request_id'] ?? '');
        $name = mb_substr(trim((string)($data['name'] ?? '')), 0, 100);
        $mobile = trim((string)($data['mobile'] ?? ''));
        if ($name === '') throw new CommonException('请填写客户姓名');
        if (!preg_match('/^1\d{10}$/', $mobile)) throw new CommonException('请输入正确的11位手机号');

        // 快速创建默认使用手机号后六位，前端允许店员在创建前覆盖。
        // 该值仅用于新会员初始化；命中已有会员时绝不重置原密码。
        $password = trim((string)($data['password'] ?? ''));
        if ($password === '') $password = substr($mobile, -6);
        if (mb_strlen($password) < 6 || mb_strlen($password) > 32 || preg_match('/\s/', $password)) {
            throw new CommonException('初始密码需为6至32位且不能包含空格');
        }

        $member = Member::where([['site_id', '=', $this->site_id], ['mobile', '=', $mobile]])->findOrEmpty();
        $created = false;
        if ($member->isEmpty()) {
            $core = new MemberService();
            $memberNo = (string)$core->getMemberNo();
            $memberId = (int)$core->add([
                'nickname' => $name,
                'mobile' => $mobile,
                'member_no' => $memberNo,
                'init_member_no' => $memberNo,
                'password' => $password,
                'headimg' => '',
                'member_label' => [],
                'sex' => 0,
                'birthday' => '',
                'remark' => '会员服务卡快速创建',
                'id_card' => '',
            ]);
            $member = Member::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])->findOrEmpty();
            $created = true;
        } elseif (trim((string)$member->nickname) === '') {
            $member->save(['nickname' => $name]);
        }
        if ($member->isEmpty()) throw new CommonException('会员创建失败，请重试');

        $party = $this->resolveParty($member->toArray());
        return [
            'member_id' => (int)$member->member_id,
            'member_no' => (string)$member->member_no,
            'member_name' => $this->displayName($member->toArray()),
            'mobile' => (string)$member->mobile,
            'mobile_masked' => $this->maskMobile((string)$member->mobile),
            'party_id' => (int)($party['party_id'] ?? 0),
            'party_name' => (string)($party['party_name'] ?? ''),
            'created' => $created,
        ];
    }

    public function cards(int $memberId): array
    {
        return $this->info($memberId)['cards'];
    }

    public function info(int $memberId): array
    {
        $member = Member::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])
            ->field('member_id,member_no,username,nickname,mobile,headimg,status,register_channel,create_time')
            ->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('会员不存在');

        $cards = MemberCard::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])
            ->order('id desc')->select()->toArray();
        $cardIds = array_map('intval', array_column($cards, 'id'));
        $items = $cardIds === [] ? [] : MemberCardItem::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->whereIn('card_id', $cardIds)->order('id asc')->select()->toArray();
        $itemMap = [];
        foreach ($items as $item) $itemMap[(int)$item['card_id']][] = $item;
        foreach ($cards as &$card) {
            $card['items'] = $itemMap[(int)$card['id']] ?? [];
            $card['validity_text'] = $this->validityText($card);
            $card['status_text'] = $this->cardStatusText((string)$card['status']);
        }
        unset($card);

        $redemptions = MemberCardRedemption::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])
            ->order('occurred_at desc,id desc')->limit(100)->select()->toArray();
        $memberRow = $member->toArray();
        return [
            'member' => array_merge($memberRow, [
                'display_name' => $this->displayName($memberRow),
                'mobile_masked' => $this->maskMobile((string)($memberRow['mobile'] ?? '')),
                'card_count' => count($cards),
                'available_card_count' => count(array_filter($cards, static fn(array $card): bool => in_array((string)$card['status'], ['active', 'pending'], true))),
            ]),
            'cards' => $cards,
            'redemptions' => $redemptions,
        ];
    }

    public function resolveParty(array $member): array
    {
        $memberId = (int)($member['member_id'] ?? 0);
        if ($memberId <= 0) throw new CommonException('会员信息不完整');
        $memberName = $this->displayName($member);
        $mobile = trim((string)($member['mobile'] ?? ''));
        // 同一份客户资料可安全重试；姓名或手机号变更后使用新的资料版本同步 ERP。
        $profileVersion = substr(hash('sha256', implode('|', [$memberId, $memberName, $mobile, 'sale_customer'])), 0, 16);
        $result = MemberCardHookResult::firstOrNull(event('ErpPartyResolveRequested', [
            'event_name' => 'erp.party.resolve_requested.v1',
            'event_version' => 1,
            'event_id' => 'hsx_member_card:member:' . $memberId . ':party:' . $profileVersion,
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'member' => [
                'member_id' => $memberId,
                'name' => $memberName,
                'mobile' => $mobile,
            ],
            'role' => 'sale_customer',
            'occurred_at' => time(),
            'remark' => '会员服务卡客户',
        ]));
        if ($result !== null) return $result;
        return [
            'consumer' => 'hsx_member_card',
            'status' => 'local',
            'party_id' => $memberId,
            'party_name' => $memberName,
        ];
    }

    private function displayName(array $member): string
    {
        foreach (['nickname', 'username', 'mobile', 'member_no'] as $field) {
            $value = trim((string)($member[$field] ?? ''));
            if ($value !== '') return $value;
        }
        return '会员' . (int)($member['member_id'] ?? 0);
    }

    private function maskMobile(string $mobile): string
    {
        return preg_match('/^\d{11}$/', $mobile) ? substr($mobile, 0, 3) . '****' . substr($mobile, -4) : $mobile;
    }

    private function validityText(array $card): string
    {
        $startAt = (int)($card['valid_start_at'] ?? 0);
        $endAt = (int)($card['valid_end_at'] ?? 0);
        if ($endAt <= 0) return '永久有效';
        if ($startAt > time()) return date('Y-m-d', $startAt) . ' 生效';
        return '有效至 ' . date('Y-m-d', $endAt);
    }

    private function cardStatusText(string $status): string
    {
        return [
            'pending' => '待激活', 'active' => '可使用', 'exhausted' => '已用完', 'expired' => '已过期',
            'frozen' => '已冻结', 'refund_pending' => '退款中', 'refunded' => '已退款', 'cancelled' => '已取消',
        ][$status] ?? $status;
    }
}
