<?php
declare (strict_types = 1);

namespace addon\hsx_recycle\app\listener\notice_template;

use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单完成奖励通知
 */
class OrderReward extends BaseNoticeTemplate
{
    private $key = 'recycle_order_reward';

    public function handle(array $params)
    {
        if ($this->key !== ($params['key'] ?? '')) {
            return null;
        }

        $orderId = (int)($params['data']['order_id'] ?? 0);
        if ($orderId <= 0) {
            Log::error('【回收通知】OrderReward 订单ID为空');
            return null;
        }

        $order = (new CoreRecycleOrderService())->getInfo($orderId);
        if (empty($order)) {
            Log::error('【回收通知】OrderReward 订单不存在: ' . $orderId);
            return null;
        }

        $siteId = (int)($order['site_id'] ?? 0);
        $memberId = (int)($order['member_id'] ?? 0);
        $wapDomain = get_wap_domain($siteId);

        $rewardPoint = (int)($params['data']['reward_point'] ?? 0);
        if ($rewardPoint <= 0) {
            Log::error('【回收通知】OrderReward 奖励积分为空');
            return null;
        }

        return $this->toReturn(
            [
                '__wechat_page' => $wapDomain . '/addon/hsx_recycle/pages/member/point',
                '__weapp_page' => 'addon/hsx_recycle/pages/member/point',
                'order_no' => $order['order_no'] ?? '',
                'complete_time' => date('Y-m-d H:i:s', $order['complete_at'] ?? time()),
                'reward_point' => $rewardPoint,
                'remark' => '恭喜您获得' . $rewardPoint . '积分奖励，可用于兑换或抵扣',
                'url' => '/mplink/a5f'
            ],
            [
                'member_id' => $memberId
            ]
        );
    }
}
