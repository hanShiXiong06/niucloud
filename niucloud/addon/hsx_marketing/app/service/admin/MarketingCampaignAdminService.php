<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\admin;

use addon\hsx_marketing\app\dict\MarketingDict;
use addon\hsx_marketing\app\model\MarketingCampaign;
use addon\hsx_marketing\app\model\MarketingCampaignReward;
use addon\hsx_marketing\app\model\MarketingClaim;
use addon\hsx_marketing\app\service\core\MarketingRewardProviderService;
use addon\hsx_marketing\app\service\core\MarketingQualificationService;
use app\model\member\MemberLevel;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

final class MarketingCampaignAdminService extends BaseAdminService
{
    public function page(array $where): array
    {
        $query = MarketingCampaign::where('site_id', '=', $this->site_id);
        if (($where['status'] ?? '') !== '') $query->where('status', '=', (int)$where['status']);
        if (!empty($where['keyword'])) $query->whereLike('title', '%' . trim((string)$where['keyword']) . '%');
        $page = $query->order('sort desc,id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        foreach ($page['data'] as &$row) {
            $row['status_name'] = MarketingDict::campaignStatuses()[(int)$row['status']] ?? '';
            $row['claim_count'] = MarketingClaim::where([['site_id', '=', $this->site_id], ['campaign_id', '=', (int)$row['id']]])->count();
            $row['completed_count'] = MarketingClaim::where([['site_id', '=', $this->site_id], ['campaign_id', '=', (int)$row['id']], ['status', 'in', [MarketingDict::CLAIM_COMPLETED, MarketingDict::CLAIM_REWARDED]]])->count();
        }
        return $page;
    }

    public function info(int $id): array
    {
        $campaign = $this->find($id);
        $data = $campaign->toArray();
        $data['rewards'] = MarketingCampaignReward::where([['site_id', '=', $this->site_id], ['campaign_id', '=', $id]])->order('sort asc,id asc')->select()->toArray();
        return $data;
    }

    public function save(array $data, int $id = 0): int
    {
        $payload = $this->validateData($data);
        return Db::transaction(function () use ($payload, $id) {
            $now = time();
            if ($id > 0) {
                $campaign = $this->find($id);
                $campaign->save(array_merge($payload['campaign'], ['update_at' => $now]));
            } else {
                $campaign = MarketingCampaign::create(array_merge($payload['campaign'], [
                    'site_id' => $this->site_id, 'campaign_no' => create_no('MK'), 'create_at' => $now, 'update_at' => $now,
                ]));
            }
            MarketingCampaignReward::where([['site_id', '=', $this->site_id], ['campaign_id', '=', (int)$campaign['id']]])->delete();
            foreach ($payload['rewards'] as $index => $reward) {
                MarketingCampaignReward::create(array_merge($reward, [
                    'site_id' => $this->site_id, 'campaign_id' => (int)$campaign['id'], 'sort' => $index,
                    'status' => 1, 'create_at' => $now, 'update_at' => $now,
                ]));
            }
            return (int)$campaign['id'];
        });
    }

    public function delete(int $id): bool
    {
        $campaign = $this->find($id);
        if (MarketingClaim::where([['site_id', '=', $this->site_id], ['campaign_id', '=', $id]])->count() > 0) {
            throw new CommonException('活动已有参与记录，不能删除，可改为暂停或结束');
        }
        return Db::transaction(function () use ($campaign, $id) {
            MarketingCampaignReward::where([['site_id', '=', $this->site_id], ['campaign_id', '=', $id]])->delete();
            $campaign->delete();
            return true;
        });
    }

    public function changeStatus(int $id, int $status): bool
    {
        if (!array_key_exists($status, MarketingDict::campaignStatuses())) throw new CommonException('活动状态不正确');
        return $this->find($id)->save(['status' => $status, 'update_at' => time()]);
    }

    public function metadata(): array
    {
        return [
            'campaign_statuses' => MarketingDict::campaignStatuses(),
            'fact_options' => MarketingDict::factOptions(),
            'providers' => (new MarketingRewardProviderService())->providers($this->site_id),
            'qualification_options' => (new MarketingQualificationService())->options($this->site_id),
            // 牛云会员等级以成长值 growth 决定顺序，核心表不存在 level_order 字段。
            'member_levels' => MemberLevel::where('site_id', '=', $this->site_id)->field('level_id,level_name,growth')->order('growth asc,level_id asc')->select()->toArray(),
        ];
    }

    public function providerOptions(string $providerKey, string $rewardType): array
    {
        return (new MarketingRewardProviderService())->options($this->site_id, $providerKey, $rewardType);
    }

    private function find(int $id): MarketingCampaign
    {
        $row = MarketingCampaign::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('营销活动不存在');
        return $row;
    }

    private function validateData(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') throw new CommonException('请输入活动名称');
        $startAt = $this->timestamp($data['start_at'] ?? 0, false);
        $endAt = $this->timestamp($data['end_at'] ?? 0, true);
        if ($startAt <= 0 || $endAt <= 0 || $endAt <= $startAt) throw new CommonException('请设置正确的活动起止时间');
        $factKey = trim((string)($data['fact_key'] ?? ''));
        $fact = null;
        foreach (MarketingDict::factOptions() as $option) {
            if (($option['key'] ?? '') === $factKey) {
                $fact = $option;
                break;
            }
        }
        if (!$fact || !empty($fact['disabled'])) throw new CommonException('当前任务事实尚未开放');
        $target = (float)($data['target_value'] ?? 0);
        if ($target <= 0) throw new CommonException('任务目标必须大于0');
        $qualificationKey = trim((string)($data['qualification_key'] ?? ''));
        if ($qualificationKey !== '') {
            $qualificationKeys = array_column((new MarketingQualificationService())->options($this->site_id), 'key');
            if (!in_array($qualificationKey, $qualificationKeys, true)) throw new CommonException('参与资格不可用，请刷新后重新选择');
        }
        $factFilter = (array)($data['fact_filter_json'] ?? []);
        $minAmount = max(0, (float)($factFilter['min_amount'] ?? 0));
        $maxAmount = max(0, (float)($factFilter['max_amount'] ?? 0));
        if ($maxAmount > 0 && $maxAmount < $minAmount) throw new CommonException('回收成交价上限不能小于下限');

        $providers = [];
        foreach ((new MarketingRewardProviderService())->providers($this->site_id) as $provider) {
            $providers[(string)$provider['provider_key'] . ':' . (string)$provider['reward_type']] = $provider;
        }
        $rewards = [];
        foreach ((array)($data['rewards'] ?? []) as $reward) {
            $key = trim((string)($reward['provider_key'] ?? '')) . ':' . trim((string)($reward['reward_type'] ?? ''));
            if (!isset($providers[$key])) throw new CommonException('奖励类型不可用，请刷新后重新选择');
            $value = (float)($reward['reward_value'] ?? 0);
            $quantity = max(1, (int)($reward['reward_quantity'] ?? 1));
            $config = (array)($reward['reward_config_json'] ?? []);
            if (str_starts_with($key, 'member:') && $value <= 0) throw new CommonException('积分或成长值必须大于0');
            if (!str_starts_with($key, 'member:') && empty($config['option_id'])) throw new CommonException('请选择具体奖励商品');
            $rewards[] = [
                'provider_key' => (string)$reward['provider_key'], 'reward_type' => (string)$reward['reward_type'],
                'reward_name' => trim((string)($reward['reward_name'] ?? $providers[$key]['name'])),
                'reward_value' => $value, 'reward_quantity' => $quantity, 'reward_config_json' => $config,
            ];
        }
        if ($rewards === []) throw new CommonException('请至少配置一项奖励');

        $campaign = [
            'title' => $title, 'subtitle' => trim((string)($data['subtitle'] ?? '')),
            'status' => (int)($data['status'] ?? MarketingDict::CAMPAIGN_DRAFT),
            'participation_mode' => in_array(($data['participation_mode'] ?? ''), ['manual', 'auto'], true) ? (string)$data['participation_mode'] : 'manual',
            'cycle_type' => in_array(($data['cycle_type'] ?? ''), ['calendar_month', 'fixed', 'rolling_days'], true) ? (string)$data['cycle_type'] : 'calendar_month',
            'cycle_days' => max(1, (int)($data['cycle_days'] ?? 30)), 'start_at' => $startAt, 'end_at' => $endAt,
            'allowed_level_ids' => array_values(array_unique(array_filter(array_map('intval', (array)($data['allowed_level_ids'] ?? []))))),
            'qualification_key' => $qualificationKey,
            'ineligible_action' => 'level_apply', 'application_url' => trim((string)($data['application_url'] ?? '')),
            'fact_key' => $factKey, 'fact_filter_json' => ['min_amount' => $minAmount, 'max_amount' => $maxAmount],
            'target_value' => $target, 'target_unit' => (string)($fact['unit'] ?? '次'),
            'grant_mode' => in_array(($data['grant_mode'] ?? ''), ['manual', 'auto'], true) ? (string)$data['grant_mode'] : 'manual',
            'claim_valid_days' => max(1, (int)($data['claim_valid_days'] ?? 7)),
            'expire_notice_days' => array_values(array_unique(array_filter(array_map('intval', (array)($data['expire_notice_days'] ?? [7, 3, 1])), fn($v) => $v > 0))),
            'notice_channels' => array_values((array)($data['notice_channels'] ?? ['weapp', 'wechat', 'sms'])),
            'description' => trim((string)($data['description'] ?? '')), 'sort' => (int)($data['sort'] ?? 0),
        ];
        return ['campaign' => $campaign, 'rewards' => $rewards];
    }

    private function timestamp(mixed $value, bool $endOfDay): int
    {
        if (is_numeric($value) && (int)$value > 0) return (int)$value;
        if (!is_string($value) || trim($value) === '') return 0;
        $text = trim($value);
        if ($endOfDay && !str_contains($text, ':')) $text .= ' 23:59:59';
        if (!$endOfDay && !str_contains($text, ':')) $text .= ' 00:00:00';
        return (int)strtotime($text);
    }
}
