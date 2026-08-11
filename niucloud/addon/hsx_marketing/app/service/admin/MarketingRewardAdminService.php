<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\admin;

use addon\hsx_marketing\app\model\MarketingCampaign;
use addon\hsx_marketing\app\model\MarketingClaim;
use addon\hsx_marketing\app\model\MarketingRewardOrder;
use addon\hsx_marketing\app\service\core\MarketingRewardService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class MarketingRewardAdminService extends BaseAdminService
{
    public function page(array $where): array
    {
        $query = MarketingRewardOrder::where('site_id', '=', $this->site_id);
        if (!empty($where['status'])) $query->where('status', '=', (string)$where['status']);
        if (!empty($where['keyword'])) $query->where(function ($q) use ($where) {
            $keyword = '%' . trim((string)$where['keyword']) . '%';
            $q->whereLike('reward_no', $keyword)->whereOrLike('reward_name', $keyword);
        });
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $campaignIds = array_values(array_unique(array_column($page['data'], 'campaign_id')));
        $claimIds = array_values(array_unique(array_column($page['data'], 'claim_id')));
        $campaigns = $campaignIds === [] ? [] : MarketingCampaign::where([['site_id', '=', $this->site_id], ['id', 'in', $campaignIds]])->column('title', 'id');
        $claimRows = $claimIds === [] ? [] : MarketingClaim::where([['site_id', '=', $this->site_id], ['id', 'in', $claimIds]])
            ->field('id,member_name,member_mobile,progress_value,target_value')->select()->toArray();
        $claims = array_column($claimRows, null, 'id');
        foreach ($page['data'] as &$row) {
            $row['campaign_title'] = $campaigns[(int)$row['campaign_id']] ?? '';
            $claim = (array)($claims[(int)$row['claim_id']] ?? []);
            $row['member_name'] = (string)($claim['member_name'] ?? '');
            $row['member_mobile'] = (string)($claim['member_mobile'] ?? '');
        }
        unset($row);
        return $page;
    }

    public function retry(int $id): array
    {
        $row = MarketingRewardOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('奖励发放单不存在');
        if ((string)$row['status'] !== 'failed') throw new CommonException('只有失败的奖励才可重试');
        $result = (new MarketingRewardService())->grant($id, true);
        if ((string)($result['status'] ?? '') === 'failed') {
            throw new CommonException((string)($result['failure_reason'] ?? '奖励重新发放失败'));
        }
        return $result;
    }
}
