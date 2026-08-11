<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\api;

use addon\hsx_marketing\app\dict\MarketingDict;
use addon\hsx_marketing\app\model\MarketingCampaign;
use addon\hsx_marketing\app\model\MarketingCampaignReward;
use addon\hsx_marketing\app\model\MarketingClaim;
use addon\hsx_marketing\app\model\MarketingRewardOrder;
use addon\hsx_marketing\app\service\core\MarketingEngineService;
use addon\hsx_marketing\app\service\core\MarketingRewardService;
use addon\hsx_marketing\app\service\core\MarketingQualificationService;
use core\base\BaseApiService;

final class MarketingPortalService extends BaseApiService
{
    public function overview(): array
    {
        return [
            'active_task_count' => MarketingCampaign::where([['site_id', '=', $this->site_id], ['status', '=', MarketingDict::CAMPAIGN_ACTIVE], ['start_at', '<=', time()], ['end_at', '>=', time()]])->count(),
            'running_task_count' => MarketingClaim::where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['status', '=', MarketingDict::CLAIM_RUNNING]])->count(),
            'claimable_reward_count' => MarketingRewardOrder::where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['status', 'in', [MarketingDict::REWARD_CLAIMABLE, MarketingDict::REWARD_FAILED]]])->count(),
        ];
    }

    public function tasks(array $where): array
    {
        $now = time();
        $query = MarketingCampaign::where([
            ['site_id', '=', $this->site_id], ['status', '=', MarketingDict::CAMPAIGN_ACTIVE],
            ['start_at', '<=', $now], ['end_at', '>=', $now],
        ]);
        $page = $query->order('sort desc,id desc')->paginate($this->pageOptions($where))->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $rows = (array)($page[$key] ?? []);
        foreach ($rows as &$row) {
            $engine = new MarketingEngineService();
            $campaign = new MarketingCampaign($row);
            $claim = $engine->findClaimAt($campaign, (int)$this->member_id, $now)->toArray();
            $qualification = (new MarketingQualificationService())->resolve($campaign, (int)$this->member_id);
            $row['eligible'] = !empty($qualification['eligible']);
            $row['can_apply'] = !empty($qualification['can_apply']);
            $row['qualification_status'] = (string)($qualification['status'] ?? '');
            $row['qualification_message'] = (string)($qualification['message'] ?? '');
            $row['application_url'] = (string)($qualification['application_url'] ?? '');
            $row['claimed'] = $claim !== [];
            $row['claim'] = $claim;
            $row['progress_value'] = (float)($claim['progress_value'] ?? 0);
            $row['progress_percent'] = min(100, round($row['progress_value'] / max(0.01, (float)$row['target_value']) * 100, 1));
            $row['rewards'] = MarketingCampaignReward::where([['site_id', '=', $this->site_id], ['campaign_id', '=', (int)$row['id']], ['status', '=', 1]])->field('reward_name,reward_value,reward_quantity,reward_type')->order('sort asc,id asc')->select()->toArray();
        }
        unset($row);
        $page[$key] = $rows;
        return $page;
    }

    public function claimTask(int $campaignId): array
    {
        return (new MarketingEngineService())->claimCampaign((int)$this->site_id, (int)$this->member_id, $campaignId);
    }

    public function rewards(array $where): array
    {
        $query = MarketingRewardOrder::where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]]);
        if (!empty($where['status'])) $query->where('status', '=', (string)$where['status']);
        $page = $query->order('id desc')->paginate($this->pageOptions($where))->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $campaignIds = array_values(array_unique(array_column((array)($page[$key] ?? []), 'campaign_id')));
        $titles = $campaignIds === [] ? [] : MarketingCampaign::where([['site_id', '=', $this->site_id], ['id', 'in', $campaignIds]])->column('title', 'id');
        $rows = (array)($page[$key] ?? []);
        foreach ($rows as &$row) {
            $row['campaign_title'] = $titles[(int)$row['campaign_id']] ?? '';
            $row['status_text'] = $this->rewardStatusText((string)$row['status']);
        }
        unset($row);
        $page[$key] = $rows;
        return $page;
    }

    public function claimReward(int $rewardId): array
    {
        return (new MarketingRewardService())->claim((int)$this->site_id, (int)$this->member_id, $rewardId);
    }

    private function pageOptions(array $where): array
    {
        return ['list_rows' => max(1, min(50, (int)($where['limit'] ?? 10))), 'page' => max(1, (int)($where['page'] ?? 1))];
    }

    private function rewardStatusText(string $status): string
    {
        return ['claimable' => '待领取', 'pending' => '待发放', 'processing' => '发放中', 'success' => '已到账', 'failed' => '发放失败', 'expired' => '已失效', 'cancelled' => '已冲红'][$status] ?? $status;
    }
}
