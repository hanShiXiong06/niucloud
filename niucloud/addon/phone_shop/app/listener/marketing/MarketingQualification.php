<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\marketing;

use addon\phone_shop\app\model\member\ForwardApplication;
use addon\phone_shop\app\service\core\member\ForwardApplicationSchemaService;
use addon\phone_shop\app\service\core\member\ForwardBenefitService;
use app\model\member\Member;

/** 商城同行权益对营销中心暴露的只读资格契约。 */
final class MarketingQualification
{
    public function handle(array $payload): array
    {
        if ((string)($payload['qualification_key'] ?? '') !== ForwardBenefitService::KEY) return ['handled' => false];
        $siteId = (int)($payload['site_id'] ?? 0);
        $memberId = (int)($payload['member_id'] ?? 0);
        if ($siteId <= 0 || $memberId <= 0) return ['handled' => true, 'eligible' => false, 'message' => '请先登录'];

        ForwardApplicationSchemaService::ensure();
        $member = Member::where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])
            ->field('member_level')->findOrEmpty();
        if ($member->isEmpty()) return ['handled' => true, 'eligible' => false, 'message' => '会员不存在'];

        $benefit = new ForwardBenefitService();
        if ($benefit->canUse($siteId, (int)$member['member_level'])) {
            return [
                'handled' => true,
                'eligible' => true,
                'can_apply' => false,
                'status' => 'approved',
                'application_url' => '',
                'message' => '您已拥有商城同行权益',
            ];
        }

        $target = $benefit->getApplicationTarget($siteId);
        $latest = ForwardApplication::where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])
            ->order('application_id desc')->findOrEmpty()->toArray();
        $status = (string)($latest['status'] ?? 'not_applied');
        $canApply = $target
            && (int)($target['config']['allow_apply'] ?? 0) === 1
            && (int)($target['config']['form_id'] ?? 0) > 0
            && (int)($target['config']['reviewer_uid'] ?? 0) > 0
            && $status !== 'pending';

        return [
            'handled' => true,
            'eligible' => false,
            'can_apply' => $canApply,
            'status' => $status,
            'application_id' => (int)($latest['application_id'] ?? 0),
            'review_reason' => (string)($latest['review_reason'] ?? ''),
            'application_url' => '/addon/phone_shop/pages/member/forward-application',
            'message' => $status === 'pending'
                ? '同行身份正在审核，通过后即可领取任务'
                : ($canApply ? '申请成为同行后即可领取任务' : '商家暂未开放同行身份申请'),
        ];
    }
}
