<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\listener\notice;

use addon\hsx_marketing\app\model\MarketingRewardOrder;
use app\listener\notice_template\BaseNoticeTemplate;

final class MarketingRewardNotice extends BaseNoticeTemplate
{
    private const KEYS = [
        'hsx_marketing_reward_available', 'hsx_marketing_reward_grant_success',
        'hsx_marketing_reward_grant_failed', 'hsx_marketing_reward_expiring',
    ];

    public function handle(array $params)
    {
        if (!in_array((string)($params['key'] ?? ''), self::KEYS, true)) return null;
        $orderId = (int)($params['data']['reward_order_id'] ?? 0);
        $order = MarketingRewardOrder::where('id', '=', $orderId)->findOrEmpty()->toArray();
        if ($order === []) return null;
        $data = (array)($params['data'] ?? []);
        $page = 'addon/hsx_marketing/pages/index';
        $wapDomain = get_wap_domain((int)$order['site_id']);
        return $this->toReturn([
            '__wechat_page' => $wapDomain . '/' . $page, '__weapp_page' => $page,
            'reward_name' => (string)($data['reward_name'] ?? $order['reward_name']),
            'reward_content' => (string)($data['reward_content'] ?? $order['reward_name']),
            'status_text' => (string)($data['status_text'] ?? ''),
            'expire_time' => (string)($data['expire_time'] ?? '长期有效'),
            'failure_reason' => (string)($data['failure_reason'] ?? ''),
            'url' => $wapDomain . '/' . $page,
        ], ['member_id' => (int)$order['member_id']]);
    }
}
