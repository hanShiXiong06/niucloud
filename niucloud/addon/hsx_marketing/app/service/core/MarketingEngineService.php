<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\core;

use addon\hsx_marketing\app\dict\MarketingDict;
use addon\hsx_marketing\app\model\MarketingCampaign;
use addon\hsx_marketing\app\model\MarketingClaim;
use addon\hsx_marketing\app\model\MarketingFact;
use app\model\member\Member;
use core\exception\CommonException;
use think\facade\Db;

final class MarketingEngineService
{
    public function recordFact(array $payload): array
    {
        $siteId = (int)($payload['site_id'] ?? 0);
        $memberId = (int)($payload['member_id'] ?? 0);
        $eventId = trim((string)($payload['event_id'] ?? ''));
        $factKey = trim((string)($payload['fact_key'] ?? ''));
        if ($siteId <= 0 || $memberId <= 0 || $eventId === '' || $factKey === '') return ['handled' => false, 'reason' => 'invalid_payload'];
        $exists = MarketingFact::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
        if (!$exists->isEmpty()) {
            if ((string)$exists['process_status'] === 'success') {
                return ['handled' => true, 'duplicate' => true, 'fact_id' => (int)$exists['id']];
            }
            return $this->processFact((int)$exists['id']);
        }

        $isReversal = (string)($payload['fact_type'] ?? 'original') === 'reversal' || (int)($payload['direction'] ?? 1) < 0;
        $original = null;
        $quantity = abs((float)($payload['quantity'] ?? 1));
        $occurredAt = (int)($payload['occurred_at'] ?? time());
        if ($isReversal) {
            $originalEventId = trim((string)($payload['reversal_of_event_id'] ?? ''));
            $original = MarketingFact::where([['site_id', '=', $siteId], ['event_id', '=', $originalEventId], ['fact_type', '=', 'original']])->findOrEmpty();
            if ($original->isEmpty()) return ['handled' => true, 'ignored' => true, 'reason' => 'original_fact_not_found'];
            $quantity = -abs((float)$original['quantity']);
            $occurredAt = (int)$original['occurred_at'];
        }

        try {
            $fact = MarketingFact::create([
                'site_id' => $siteId, 'event_id' => $eventId, 'event_name' => (string)($payload['event_name'] ?? 'hsx.marketing.fact.recorded.v1'),
                'source_plugin' => (string)($payload['source_plugin'] ?? ''), 'fact_key' => $factKey,
                'fact_type' => $isReversal ? 'reversal' : 'original', 'direction' => $isReversal ? -1 : 1,
                'reversal_of_event_id' => (string)($payload['reversal_of_event_id'] ?? ''),
                'business_type' => (string)($payload['business_type'] ?? ''), 'business_id' => (string)($payload['business_id'] ?? ''),
                'business_no' => (string)($payload['business_no'] ?? ''), 'member_id' => $memberId,
                'quantity' => $quantity,
                // 冲红沿用原事实业务数据，确保成交价区间的判定与正向累计完全一致。
                'payload_json' => $isReversal && $original ? array_merge((array)$original['payload_json'], [
                    'fact_type' => 'reversal',
                    'direction' => -1,
                    'reversal_of_event_id' => (string)($payload['reversal_of_event_id'] ?? ''),
                ]) : $payload,
                'occurred_at' => $occurredAt,
                'received_at' => time(), 'process_status' => 'pending', 'next_retry_at' => time(),
                'create_at' => time(), 'update_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // 并发回调可能同时通过首次查询，唯一键负责兜底；只吞掉真实的重复事件。
            $fact = MarketingFact::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
            if ($fact->isEmpty()) throw $e;
        }
        return $this->processFact((int)$fact['id']);
    }

    public function processFact(int $factId): array
    {
        try {
            return Db::transaction(function () use ($factId) {
                $fact = MarketingFact::where('id', '=', $factId)->lock(true)->findOrEmpty();
                if ($fact->isEmpty()) return ['handled' => false, 'reason' => 'fact_not_found'];
                if ((string)$fact['process_status'] === 'success') {
                    return ['handled' => true, 'duplicate' => true, 'fact_id' => $factId];
                }
                if ((string)$fact['fact_type'] === 'reversal') {
                    $original = MarketingFact::where([
                        ['site_id', '=', (int)$fact['site_id']],
                        ['event_id', '=', (string)$fact['reversal_of_event_id']],
                        ['fact_type', '=', 'original'],
                    ])->findOrEmpty();
                    if ($original->isEmpty() || (string)$original['process_status'] !== 'success') {
                        throw new CommonException('原始营销事实尚未处理成功，冲红将在原事实恢复后重试');
                    }
                }
                $fact->save(['process_status' => 'processing', 'error_message' => '', 'update_at' => time()]);
                $siteId = (int)$fact['site_id'];
                $memberId = (int)$fact['member_id'];
                $factKey = (string)$fact['fact_key'];
                $occurredAt = (int)$fact['occurred_at'];
                $quantity = (float)$fact['quantity'];
                $isReversal = (string)$fact['fact_type'] === 'reversal' || (int)$fact['direction'] < 0;
                $campaigns = MarketingCampaign::where([
                    ['site_id', '=', $siteId], ['status', '=', MarketingDict::CAMPAIGN_ACTIVE], ['fact_key', '=', $factKey],
                    ['start_at', '<=', $occurredAt], ['end_at', '>=', $occurredAt],
                ])->select();
                $updated = [];
                foreach ($campaigns as $campaign) {
                    if (!$this->matchesFactFilter($campaign, $fact)) continue;
                    $claim = $this->claimForFact($campaign, $memberId, $occurredAt, !$isReversal);
                    if (!$claim) continue;
                    // 手动领取只累计领取后的事实；自动参与必须把触发建档的当前事实计入。
                    if (
                        !$isReversal
                        && (string)$campaign['participation_mode'] === 'manual'
                        && $occurredAt < (int)$claim['create_at']
                    ) continue;
                    $before = (float)$claim['progress_value'];
                    $after = max(0, $before + $quantity);
                    $target = (float)$claim['target_value'];
                    $wasCompleted = in_array((string)$claim['status'], [MarketingDict::CLAIM_COMPLETED, MarketingDict::CLAIM_REWARDED], true);
                    $nowCompleted = $after >= $target;
                    $claim->save([
                        'progress_value' => $after,
                        'status' => $nowCompleted ? ($wasCompleted ? (string)$claim['status'] : MarketingDict::CLAIM_COMPLETED) : MarketingDict::CLAIM_RUNNING,
                        'completed_at' => $nowCompleted ? ((int)$claim['completed_at'] ?: time()) : 0,
                        'update_at' => time(),
                    ]);
                    if (!$wasCompleted && $nowCompleted) {
                        $claim->refresh();
                        (new MarketingRewardService())->createForClaim($claim, $campaign);
                    }
                    if ($wasCompleted && !$nowCompleted && $isReversal) (new MarketingRewardService())->reverseClaimRewards((int)$claim['id']);
                    $updated[] = ['campaign_id' => (int)$campaign['id'], 'claim_id' => (int)$claim['id'], 'progress' => $after];
                }
                $fact->save(['process_status' => 'success', 'processed_at' => time(), 'next_retry_at' => 0, 'error_message' => '', 'update_at' => time()]);
                return ['handled' => true, 'fact_id' => $factId, 'updated' => $updated];
            });
        } catch (\Throwable $e) {
            $fact = MarketingFact::where('id', '=', $factId)->findOrEmpty();
            if (!$fact->isEmpty()) {
                $retry = (int)$fact['retry_count'] + 1;
                $fact->save([
                    'process_status' => 'failed', 'retry_count' => $retry,
                    'next_retry_at' => time() + min(3600, 60 * (2 ** min($retry, 6))),
                    'error_message' => mb_substr($e->getMessage(), 0, 500), 'update_at' => time(),
                ]);
            }
            return ['handled' => false, 'fact_id' => $factId, 'reason' => 'process_failed', 'message' => $e->getMessage()];
        }
    }

    public function claimCampaign(int $siteId, int $memberId, int $campaignId): array
    {
        $campaign = MarketingCampaign::where([['id', '=', $campaignId], ['site_id', '=', $siteId], ['status', '=', MarketingDict::CAMPAIGN_ACTIVE]])->findOrEmpty();
        if ($campaign->isEmpty()) throw new CommonException('活动不存在或未开始');
        $now = time();
        if ((int)$campaign['start_at'] > $now || (int)$campaign['end_at'] < $now) throw new CommonException('当前不在活动时间内');
        $this->assertEligible($campaign, $memberId);
        return Db::transaction(function () use ($campaign, $memberId, $now) {
            $exists = $this->findClaimAt($campaign, $memberId, $now, true);
            if (!$exists->isEmpty()) return $exists->toArray();
            $cycle = $this->cycle($campaign, $now, $now);
            return $this->createClaim($campaign, $memberId, $cycle)->toArray();
        });
    }

    private function claimForFact(MarketingCampaign $campaign, int $memberId, int $occurredAt, bool $allowCreate): ?MarketingClaim
    {
        $claim = $this->findClaimAt($campaign, $memberId, $occurredAt);
        if (!$claim->isEmpty()) return $claim;
        if (!$allowCreate || (string)$campaign['participation_mode'] !== 'auto') return null;
        try { $this->assertEligible($campaign, $memberId); } catch (\Throwable) { return null; }
        $cycle = $this->cycle($campaign, $occurredAt, $occurredAt);
        try { return $this->createClaim($campaign, $memberId, $cycle); }
        catch (\Throwable) {
            $claim = MarketingClaim::where([
                ['site_id', '=', (int)$campaign['site_id']], ['campaign_id', '=', (int)$campaign['id']],
                ['member_id', '=', $memberId], ['cycle_key', '=', $cycle['key']],
            ])->findOrEmpty();
            return $claim->isEmpty() ? null : $claim;
        }
    }

    /**
     * 滚动周期没有固定自然月 key，必须按“时间落在哪个已领取周期内”查找；
     * 否则每条事实都会按自己的发生秒数生成一个新周期。
     */
    public function findClaimAt(MarketingCampaign $campaign, int $memberId, int $at, bool $lock = false): MarketingClaim
    {
        $query = MarketingClaim::where([
            ['site_id', '=', (int)$campaign['site_id']],
            ['campaign_id', '=', (int)$campaign['id']],
            ['member_id', '=', $memberId],
        ]);
        if ((string)$campaign['cycle_type'] === 'rolling_days') {
            $query->where('cycle_start_at', '<=', $at)->where('cycle_end_at', '>=', $at)->order('id desc');
        } else {
            $cycle = $this->cycle($campaign, $at, $at);
            $query->where('cycle_key', '=', $cycle['key']);
        }
        if ($lock) $query->lock(true);
        return $query->findOrEmpty();
    }

    private function createClaim(MarketingCampaign $campaign, int $memberId, array $cycle): MarketingClaim
    {
        $member = Member::where([['site_id', '=', (int)$campaign['site_id']], ['member_id', '=', $memberId]])
            ->field('member_id,member_level,nickname,username,mobile')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('会员不存在');
        $now = time();
        return MarketingClaim::create([
            'site_id' => (int)$campaign['site_id'], 'claim_no' => create_no('MT'), 'campaign_id' => (int)$campaign['id'],
            'member_id' => $memberId, 'member_name' => (string)($member['nickname'] ?: $member['username']),
            'member_mobile' => (string)$member['mobile'], 'claimed_level_id' => (int)$member['member_level'],
            'cycle_key' => $cycle['key'], 'cycle_start_at' => $cycle['start'], 'cycle_end_at' => $cycle['end'],
            'status' => MarketingDict::CLAIM_RUNNING, 'progress_value' => 0, 'target_value' => (float)$campaign['target_value'],
            'create_at' => $now, 'update_at' => $now,
        ]);
    }

    private function assertEligible(MarketingCampaign $campaign, int $memberId): void
    {
        $qualification = (new MarketingQualificationService())->resolve($campaign, $memberId);
        if (empty($qualification['eligible'])) throw new CommonException((string)($qualification['message'] ?? '当前会员不满足活动领取条件'));
    }

    private function matchesFactFilter(MarketingCampaign $campaign, MarketingFact $fact): bool
    {
        $filter = (array)($campaign['fact_filter_json'] ?? []);
        if ($filter === []) return true;
        $payload = (array)($fact['payload_json'] ?? []);
        $amount = (float)($payload['amount'] ?? $payload['final_price'] ?? $payload['pay_amount'] ?? 0);
        $minAmount = max(0, (float)($filter['min_amount'] ?? 0));
        $maxAmount = max(0, (float)($filter['max_amount'] ?? 0));
        if ($minAmount > 0 && $amount < $minAmount) return false;
        if ($maxAmount > 0 && $amount > $maxAmount) return false;
        return true;
    }

    public function cycle(MarketingCampaign $campaign, int $at, int $claimAt = 0): array
    {
        $type = (string)$campaign['cycle_type'];
        if ($type === 'fixed') return ['key' => 'fixed:' . $campaign['id'], 'start' => (int)$campaign['start_at'], 'end' => (int)$campaign['end_at']];
        if ($type === 'rolling_days') {
            $start = $claimAt > 0 ? $claimAt : $at;
            return ['key' => 'rolling:' . date('YmdHis', $start), 'start' => $start, 'end' => min((int)$campaign['end_at'], $start + max(1, (int)$campaign['cycle_days']) * 86400 - 1)];
        }
        $start = strtotime(date('Y-m-01 00:00:00', $at));
        return ['key' => date('Y-m', $at), 'start' => $start, 'end' => strtotime('+1 month', $start) - 1];
    }
}
