<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\support\MemberCardHookResult;
use addon\hsx_member_card\app\support\MemberCardIdempotency;
use app\model\member\Member;
use app\service\admin\member\MemberService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class MemberCardMemberService extends BaseAdminService
{
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
        $member = Member::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])->field('member_id')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('会员不存在');
        return MemberCard::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])->order('id desc')->select()->toArray();
    }

    public function resolveParty(array $member): array
    {
        $memberId = (int)($member['member_id'] ?? 0);
        if ($memberId <= 0) throw new CommonException('会员信息不完整');
        $memberName = $this->displayName($member);
        $mobile = trim((string)($member['mobile'] ?? ''));
        // 同一份客户资料可安全重试；姓名或手机号变更后使用新的资料版本同步 ERP。
        $profileVersion = substr(hash('sha256', implode('|', [$memberId, $memberName, $mobile, 'sale_customer'])), 0, 16);
        return MemberCardHookResult::first(event('ErpPartyResolveRequested', [
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
        ]), '会员往来主体解析');
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
}
