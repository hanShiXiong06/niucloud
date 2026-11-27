<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\core;

use addon\hsx_marketing\app\model\MarketingCampaign;
use app\model\member\Member;

/**
 * 营销参与资格契约。
 * 营销中心只识别资格键，不依赖商城的会员等级、表单和审核表结构。
 */
final class MarketingQualificationService
{
    public function options(int $siteId): array
    {
        $options = [];
        foreach ((array)event('HsxMarketingQualificationOptionsRequested', [
            'contract_version' => 'v1',
            'site_id' => $siteId,
        ]) as $result) {
            foreach ((array)$result as $option) {
                if (!is_array($option) || empty($option['key'])) continue;
                $options[(string)$option['key']] = $option;
            }
        }
        return array_values($options);
    }

    public function resolve(MarketingCampaign $campaign, int $memberId): array
    {
        $siteId = (int)$campaign['site_id'];
        $key = trim((string)($campaign['qualification_key'] ?? ''));
        if ($key !== '') {
            foreach ((array)event('HsxMarketingQualificationRequested', [
                'contract_version' => 'v1',
                'site_id' => $siteId,
                'member_id' => $memberId,
                'qualification_key' => $key,
                'scene' => 'marketing_campaign',
                'campaign_id' => (int)$campaign['id'],
            ]) as $result) {
                if (!is_array($result) || empty($result['handled'])) continue;
                return array_merge([
                    'handled' => true,
                    'eligible' => false,
                    'can_apply' => false,
                    'status' => 'unavailable',
                    'application_url' => '',
                    'message' => '当前暂不满足任务参与条件',
                ], $result);
            }
            return [
                'handled' => false,
                'eligible' => false,
                'can_apply' => false,
                'status' => 'provider_unavailable',
                'application_url' => '',
                'message' => '参与资格服务暂不可用，请联系商家',
            ];
        }

        // 兼容旧活动：未选择契约资格时，仍可按会员等级限制。
        $levels = array_values(array_filter(array_map('intval', (array)$campaign['allowed_level_ids'])));
        if ($levels === []) return ['handled' => true, 'eligible' => true, 'can_apply' => false, 'status' => 'approved', 'application_url' => '', 'message' => ''];
        $member = Member::where([['site_id', '=', $siteId], ['member_id', '=', $memberId]])->field('member_level')->findOrEmpty();
        $eligible = !$member->isEmpty() && in_array((int)$member['member_level'], $levels, true);
        return [
            'handled' => true,
            'eligible' => $eligible,
            'can_apply' => !$eligible && trim((string)$campaign['application_url']) !== '',
            'status' => $eligible ? 'approved' : 'not_applied',
            'application_url' => (string)$campaign['application_url'],
            'message' => $eligible ? '' : '当前会员等级不满足活动领取条件',
        ];
    }
}
