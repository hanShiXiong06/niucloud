<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\core;

use addon\hsx_marketing\app\model\MarketingNoticeLog;
use addon\hsx_marketing\app\model\MarketingRewardOrder;
use app\service\core\notice\NoticeService;
use think\facade\Log;

final class MarketingNoticeService
{
    public function send(array|MarketingRewardOrder $reward, string $noticeType, string $dedupeKey = ''): bool
    {
        $row = $reward instanceof MarketingRewardOrder ? $reward->toArray() : $reward;
        $rewardId = (int)($row['id'] ?? 0);
        if ($rewardId <= 0) return false;
        $dedupeKey = $dedupeKey !== '' ? $dedupeKey : $noticeType;
        $exists = MarketingNoticeLog::where([
            ['site_id', '=', (int)$row['site_id']], ['reward_order_id', '=', $rewardId],
            ['notice_type', '=', $noticeType], ['notice_key', '=', $dedupeKey],
        ])->findOrEmpty();
        if (!$exists->isEmpty()) return (string)$exists['status'] === 'success';

        $now = time();
        $log = MarketingNoticeLog::create([
            'site_id' => (int)$row['site_id'], 'reward_order_id' => $rewardId,
            'member_id' => (int)$row['member_id'], 'notice_type' => $noticeType,
            'notice_key' => $dedupeKey, 'status' => 'pending', 'create_at' => $now, 'update_at' => $now,
        ]);
        $noticeKey = match ($noticeType) {
            'available' => 'hsx_marketing_reward_available',
            'success' => 'hsx_marketing_reward_grant_success',
            'failed' => 'hsx_marketing_reward_grant_failed',
            'expiring' => 'hsx_marketing_reward_expiring',
            default => '',
        };
        if ($noticeKey === '') return false;
        try {
            $expireAt = (int)($row['reward_expire_at'] ?: $row['claim_expire_at']);
            $result = NoticeService::send((int)$row['site_id'], $noticeKey, [
                'member_id' => (int)$row['member_id'], 'reward_order_id' => $rewardId,
                'reward_no' => (string)$row['reward_no'], 'reward_name' => (string)$row['reward_name'],
                'reward_content' => $this->content($row), 'status_text' => $this->statusText((string)$row['status']),
                'expire_time' => $expireAt > 0 ? date('Y-m-d H:i', $expireAt) : '长期有效',
                'failure_reason' => (string)($row['failure_reason'] ?? ''),
                'url' => '/addon/hsx_marketing/pages/index',
            ]);
            $log->save(['status' => $result === false ? 'skipped' : 'success', 'result_json' => ['queued' => $result !== false], 'sent_at' => $now, 'update_at' => $now]);
            return $result !== false;
        } catch (\Throwable $e) {
            $log->save(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 500), 'update_at' => time()]);
            Log::error('营销奖励通知失败：' . $e->getMessage(), ['reward_order_id' => $rewardId, 'notice_type' => $noticeType]);
            return false;
        }
    }

    private function content(array $row): string
    {
        $quantity = max(1, (int)($row['reward_quantity'] ?? 1));
        if (in_array((string)$row['reward_type'], ['point', 'growth'], true)) {
            return $row['reward_name'] . ' ' . ((float)$row['reward_value'] * $quantity);
        }
        return $row['reward_name'] . ($quantity > 1 ? ' × ' . $quantity : '');
    }

    private function statusText(string $status): string
    {
        return ['claimable' => '待领取', 'pending' => '待发放', 'processing' => '发放中', 'success' => '已到账', 'failed' => '发放失败', 'expired' => '已失效'][$status] ?? $status;
    }
}
