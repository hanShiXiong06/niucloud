<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\listener\notice;

use addon\hsx_marketing\app\dict\MarketingDict;
use addon\hsx_marketing\app\model\MarketingCampaign;
use addon\hsx_marketing\app\model\MarketingRewardOrder;
use app\listener\notice_template\BaseNoticeTemplate;
use core\exception\NoticeException;

final class MarketingRewardNotice extends BaseNoticeTemplate
{
    private const KEYS = [
        'hsx_marketing_reward_available', 'hsx_marketing_reward_grant_success',
        'hsx_marketing_reward_grant_failed', 'hsx_marketing_reward_expiring',
    ];

    public function handle(array $params)
    {
        $key = (string)($params['key'] ?? '');
        if (!in_array($key, self::KEYS, true)) return null;
        $siteId = (int)($params['site_id'] ?? 0);
        $orderId = (int)($params['data']['reward_order_id'] ?? 0);
        if ($siteId <= 0 || $orderId <= 0) return null;
        $order = MarketingRewardOrder::where([['id', '=', $orderId], ['site_id', '=', $siteId]])->findOrEmpty()->toArray();
        if ($order === []) return null;
        $campaign = MarketingCampaign::where([['id', '=', (int)$order['campaign_id']], ['site_id', '=', $siteId]])->findOrEmpty()->toArray();
        $data = (array)($params['data'] ?? []);
        $rewardName = (string)($data['reward_name'] ?? $order['reward_name']);
        $quantity = max(1, (int)($order['reward_quantity'] ?? 1));
        $isNumberReward = in_array((string)($order['reward_type'] ?? ''), ['point', 'growth'], true);
        $rewardNumber = $isNumberReward
            ? rtrim(rtrim(number_format((float)($order['reward_value'] ?? 0) * $quantity, 2, '.', ''), '0'), '.')
            : (string)$quantity;
        $rewardContent = (string)($data['reward_content'] ?? ($rewardName . ($isNumberReward ? ' ' . $rewardNumber : ($quantity > 1 ? ' × ' . $quantity : ''))));
        // 未领取看领取截止时间；已到账看权益有效期，不能把旧的领取期限当成到账奖励的有效期。
        $isGranted = (string)($order['status'] ?? '') === MarketingDict::REWARD_SUCCESS;
        $expireAt = (int)($isGranted ? ($order['reward_expire_at'] ?? 0) : ($order['claim_expire_at'] ?? 0));
        if ($key === 'hsx_marketing_reward_expiring' && $expireAt <= 0) {
            throw new NoticeException('该奖励没有到期时间，无需发送到期提醒');
        }
        $status = [
            'hsx_marketing_reward_available' => '待领取', 'hsx_marketing_reward_grant_success' => '已到账',
            'hsx_marketing_reward_grant_failed' => '发放失败', 'hsx_marketing_reward_expiring' => '即将到期',
        ][$key];
        $remark = match ($key) {
            'hsx_marketing_reward_available' => '奖励已可领取，请点击查看',
            'hsx_marketing_reward_grant_success' => '奖励已到账，请到奖励中心查看',
            'hsx_marketing_reward_grant_failed' => '奖励暂未到账，请联系商家处理',
            default => ($isGranted ? '请及时使用：' : '请及时领取：') . $rewardContent,
        };
        $activityType = '营销任务奖励';
        foreach (MarketingDict::factOptions() as $option) {
            if ($option['key'] === ($campaign['fact_key'] ?? '')) { $activityType = $option['name']; break; }
        }
        $page = 'addon/hsx_marketing/pages/index';
        $wapDomain = get_wap_domain((int)$order['site_id']);
        return $this->toReturn([
            '__wechat_page' => $wapDomain . '/' . $page, '__weapp_page' => $page,
            'reward_name' => $rewardName, 'reward_content' => $rewardContent,
            'status_text' => (string)($data['status_text'] ?? $status),
            'expire_time' => $expireAt > 0 ? date('Y-m-d H:i', $expireAt) : '长期有效',
            'failure_reason' => (string)($data['failure_reason'] ?? ''),
            'url' => $wapDomain . '/' . $page,
            // 小程序模板字段有类型和字数限制；不截断短信/公众号使用的原始奖励内容。
            'campaign_name' => $this->shortText((string)($campaign['title'] ?? $rewardName)),
            'activity_type' => $this->shortText($activityType),
            'reward_summary' => $this->shortText($rewardContent),
            'notice_remark' => $this->shortText($remark),
            'status_short' => $status, 'reward_number' => $rewardNumber,
        ], ['member_id' => (int)$order['member_id']]);
    }

    private function shortText(string $text): string
    {
        $text = trim((string)preg_replace('/[\x00-\x20\x7f]+/u', ' ', strip_tags($text)));
        if ($text === '') $text = '任务奖励';
        return mb_strlen($text, 'UTF-8') > 20 ? mb_substr($text, 0, 19, 'UTF-8') . '…' : $text;
    }
}
