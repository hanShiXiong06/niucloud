<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\core;

use addon\hsx_marketing\app\dict\MarketingDict;
use addon\hsx_marketing\app\model\MarketingCampaign;
use addon\hsx_marketing\app\model\MarketingFact;
use addon\hsx_marketing\app\model\MarketingRewardOrder;

final class MarketingTickService
{
    public function tick(): array
    {
        $now = time();
        $facts = MarketingFact::where([
            ['process_status', 'in', ['pending', 'failed']], ['retry_count', '<', 5], ['next_retry_at', '<=', $now],
        ])->order('id asc')->limit(100)->select();
        foreach ($facts as $fact) (new MarketingEngineService())->processFact((int)$fact['id']);

        $expired = MarketingRewardOrder::where([['status', '=', MarketingDict::REWARD_CLAIMABLE], ['claim_expire_at', '>', 0], ['claim_expire_at', '<', $now]])
            ->limit(200)->select();
        foreach ($expired as $row) $row->save(['status' => MarketingDict::REWARD_EXPIRED, 'update_at' => $now]);

        $retry = MarketingRewardOrder::where([['status', '=', MarketingDict::REWARD_FAILED], ['retry_count', '<', 5], ['next_retry_at', '<=', $now]])
            ->limit(50)->select();
        foreach ($retry as $row) (new MarketingRewardService())->grant((int)$row['id'], true);

        $notices = 0;
        $claimable = MarketingRewardOrder::where([['status', '=', MarketingDict::REWARD_CLAIMABLE], ['claim_expire_at', '>', $now]])->limit(500)->select();
        $campaignCache = [];
        foreach ($claimable as $row) {
            $campaignId = (int)$row['campaign_id'];
            if (!array_key_exists($campaignId, $campaignCache)) {
                $campaignCache[$campaignId] = MarketingCampaign::where('id', '=', $campaignId)->findOrEmpty()->toArray();
            }
            foreach ((array)($campaignCache[$campaignId]['expire_notice_days'] ?? [7, 3, 1]) as $day) {
                $day = (int)$day;
                if ($day <= 0) continue;
                $remaining = (int)$row['claim_expire_at'] - $now;
                if ($remaining > 0 && $remaining <= $day * 86400 && $remaining > ($day - 1) * 86400) {
                    if ((new MarketingNoticeService())->send($row, 'expiring', 'day:' . $day)) $notices++;
                }
            }
        }

        // 已发放的优惠券等权益也有自己的有效期；领取成功并不代表提醒链路结束。
        $granted = MarketingRewardOrder::where([
            ['status', '=', MarketingDict::REWARD_SUCCESS],
            ['reward_expire_at', '>', $now],
        ])->limit(500)->select();
        foreach ($granted as $row) {
            $campaignId = (int)$row['campaign_id'];
            if (!array_key_exists($campaignId, $campaignCache)) {
                $campaignCache[$campaignId] = MarketingCampaign::where('id', '=', $campaignId)->findOrEmpty()->toArray();
            }
            foreach ((array)($campaignCache[$campaignId]['expire_notice_days'] ?? [7, 3, 1]) as $day) {
                $day = (int)$day;
                if ($day <= 0) continue;
                $remaining = (int)$row['reward_expire_at'] - $now;
                if ($remaining > 0 && $remaining <= $day * 86400 && $remaining > ($day - 1) * 86400) {
                    if ((new MarketingNoticeService())->send($row, 'expiring', 'benefit-day:' . $day)) $notices++;
                }
            }
        }
        return ['facts_retried' => count($facts), 'expired' => count($expired), 'retried' => count($retry), 'notices' => $notices];
    }
}
