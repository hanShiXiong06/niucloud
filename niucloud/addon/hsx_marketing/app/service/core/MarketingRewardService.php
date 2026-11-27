<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\core;

use addon\hsx_marketing\app\dict\MarketingDict;
use addon\hsx_marketing\app\model\MarketingCampaign;
use addon\hsx_marketing\app\model\MarketingCampaignReward;
use addon\hsx_marketing\app\model\MarketingClaim;
use addon\hsx_marketing\app\model\MarketingRewardOrder;
use core\exception\CommonException;
use think\facade\Db;

final class MarketingRewardService
{
    public function createForClaim(MarketingClaim $claim, MarketingCampaign $campaign): array
    {
        $rewards = MarketingCampaignReward::where([
            ['site_id', '=', (int)$claim['site_id']], ['campaign_id', '=', (int)$campaign['id']], ['status', '=', 1],
        ])->order('sort asc,id asc')->select();
        $created = [];
        foreach ($rewards as $reward) {
            $requestId = 'campaign:' . $campaign['id'] . ':claim:' . $claim['id'] . ':reward:' . $reward['id'];
            $exists = MarketingRewardOrder::where([['site_id', '=', (int)$claim['site_id']], ['request_id', '=', $requestId]])->findOrEmpty();
            if (!$exists->isEmpty()) { $created[] = $exists->toArray(); continue; }
            $now = time();
            $manual = (string)$campaign['grant_mode'] === 'manual';
            $order = MarketingRewardOrder::create([
                'site_id' => (int)$claim['site_id'], 'reward_no' => create_no('MR'), 'request_id' => $requestId,
                'campaign_id' => (int)$campaign['id'], 'claim_id' => (int)$claim['id'], 'campaign_reward_id' => (int)$reward['id'],
                'member_id' => (int)$claim['member_id'], 'provider_key' => (string)$reward['provider_key'],
                'reward_type' => (string)$reward['reward_type'], 'reward_name' => (string)$reward['reward_name'],
                'reward_value' => (float)$reward['reward_value'], 'reward_quantity' => max(1, (int)$reward['reward_quantity']),
                'reward_config_json' => (array)$reward['reward_config_json'], 'grant_mode' => (string)$campaign['grant_mode'],
                'status' => $manual ? MarketingDict::REWARD_CLAIMABLE : MarketingDict::REWARD_PENDING,
                'claim_expire_at' => $manual ? $now + max(1, (int)$campaign['claim_valid_days']) * 86400 : 0,
                'next_retry_at' => $now, 'create_at' => $now, 'update_at' => $now,
            ]);
            $created[] = $order->toArray();
            if ($manual) (new MarketingNoticeService())->send($order, 'available');
            else $this->grant((int)$order['id'], false);
        }
        return $created;
    }

    public function claim(int $siteId, int $memberId, int $rewardOrderId): array
    {
        $order = MarketingRewardOrder::where([
            ['id', '=', $rewardOrderId], ['site_id', '=', $siteId], ['member_id', '=', $memberId],
        ])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('奖励不存在或无权领取');
        if ((string)$order['status'] === MarketingDict::REWARD_SUCCESS) return $order->toArray();
        if ((string)$order['status'] !== MarketingDict::REWARD_CLAIMABLE && (string)$order['status'] !== MarketingDict::REWARD_FAILED) {
            throw new CommonException('当前奖励不可领取');
        }
        if ((int)$order['claim_expire_at'] > 0 && (int)$order['claim_expire_at'] < time()) {
            $order->save(['status' => MarketingDict::REWARD_EXPIRED, 'update_at' => time()]);
            throw new CommonException('奖励领取时间已结束');
        }
        $order->save(['claimed_at' => time(), 'status' => MarketingDict::REWARD_PENDING, 'next_retry_at' => time(), 'update_at' => time()]);
        $result = $this->grant($rewardOrderId, true);
        if ((string)($result['status'] ?? '') === MarketingDict::REWARD_FAILED) {
            throw new CommonException((string)($result['failure_reason'] ?? '奖励发放失败，请稍后重试'));
        }
        return $result;
    }

    public function grant(int $rewardOrderId, bool $notifyFailure = true): array
    {
        try {
            $result = Db::transaction(function () use ($rewardOrderId) {
                $order = MarketingRewardOrder::where([['id', '=', $rewardOrderId]])->lock(true)->findOrEmpty();
                if ($order->isEmpty()) throw new CommonException('奖励单不存在');
                if ((string)$order['status'] === MarketingDict::REWARD_SUCCESS) return $order->toArray();
                if (!in_array((string)$order['status'], [MarketingDict::REWARD_PENDING, MarketingDict::REWARD_FAILED], true)) {
                    throw new CommonException('奖励单状态不允许发放');
                }
                $order->save(['status' => MarketingDict::REWARD_PROCESSING, 'update_at' => time()]);
                $providerResult = (new MarketingRewardProviderService())->grant($order->toArray());
                if (empty($providerResult['success'])) throw new CommonException((string)($providerResult['message'] ?? '奖励发放失败'));
                $now = time();
                $order->save([
                    'status' => MarketingDict::REWARD_SUCCESS, 'granted_at' => $now,
                    'reward_expire_at' => (int)($providerResult['expire_at'] ?? 0),
                    'provider_no' => (string)($providerResult['provider_no'] ?? ''),
                    'provider_result_json' => $providerResult, 'failure_code' => '', 'failure_reason' => '', 'update_at' => $now,
                ]);
                $this->refreshClaimStatus((int)$order['claim_id']);
                $order->refresh();
                return $order->toArray();
            });
            (new MarketingNoticeService())->send($result, 'success');
            return $result;
        } catch (\Throwable $e) {
            $order = MarketingRewardOrder::where([['id', '=', $rewardOrderId]])->findOrEmpty();
            if (!$order->isEmpty() && (string)$order['status'] !== MarketingDict::REWARD_SUCCESS) {
                $retry = (int)$order['retry_count'] + 1;
                $order->save([
                    'status' => MarketingDict::REWARD_FAILED, 'retry_count' => $retry,
                    'next_retry_at' => time() + min(3600, 60 * (2 ** min($retry, 6))),
                    'failure_code' => 'PROVIDER_ERROR', 'failure_reason' => mb_substr($e->getMessage(), 0, 500), 'update_at' => time(),
                ]);
                $order->refresh();
                if ($notifyFailure && $retry >= 5) (new MarketingNoticeService())->send($order, 'failed');
                return $order->toArray();
            }
            throw $e;
        }
    }

    public function reverseClaimRewards(int $claimId): array
    {
        $results = [];
        $orders = MarketingRewardOrder::where([['claim_id', '=', $claimId], ['status', '=', MarketingDict::REWARD_SUCCESS]])->select();
        foreach ($orders as $order) {
            try {
                $result = (new MarketingRewardProviderService())->reverse($order->toArray());
                if (!empty($result['success'])) $order->save(['status' => 'cancelled', 'provider_result_json' => array_merge((array)$order['provider_result_json'], ['reversal' => $result]), 'update_at' => time()]);
                else $order->save(['failure_code' => 'REVERSAL_MANUAL_REQUIRED', 'failure_reason' => (string)($result['message'] ?? '冲红需人工处理'), 'update_at' => time()]);
                $results[] = $result;
            } catch (\Throwable $e) {
                $order->save(['failure_code' => 'REVERSAL_FAILED', 'failure_reason' => mb_substr($e->getMessage(), 0, 500), 'update_at' => time()]);
            }
        }
        return $results;
    }

    private function refreshClaimStatus(int $claimId): void
    {
        $pending = MarketingRewardOrder::where([['claim_id', '=', $claimId], ['status', 'not in', [MarketingDict::REWARD_SUCCESS, 'cancelled']]])->count();
        if ($pending === 0) MarketingClaim::where([['id', '=', $claimId]])->update(['status' => MarketingDict::CLAIM_REWARDED, 'rewarded_at' => time(), 'update_at' => time()]);
    }
}
