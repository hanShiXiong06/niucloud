<?php
declare (strict_types = 1);

namespace addon\recycle\app\listener\notice_template;

use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单签收通知
 */
class OrderSign extends BaseNoticeTemplate
{
    private $key = 'recycle_order_sign';

    public function handle(array $params)
    {
        if ($this->key !== ($params['key'] ?? '')) {
            return null;
        }

        $orderId = (int)($params['data']['order_id'] ?? 0);
        if ($orderId <= 0) {
            Log::error('【回收通知】OrderSign 订单ID为空');
            return null;
        }

        $order = (new CoreRecycleOrderService())->getInfo($orderId);
        if (empty($order)) {
            Log::error('【回收通知】OrderSign 订单不存在: ' . $orderId);
            return null;
        }

        $siteId = (int)($order['site_id'] ?? 0);
        $memberId = (int)($order['member_id'] ?? 0);
        $wapDomain = get_wap_domain($siteId);

        return $this->toReturn(
            [
                '__wechat_page' => $wapDomain . '/addon/recycle/pages/order/detail?id=' . $orderId,
                '__weapp_page' => 'addon/recycle/pages/order/detail?id=' . $orderId,
                'order_no' => $order['order_no'] ?? '',
                'sign_time' => date('Y-m-d H:i:s'),
                'remark' => '您的回收订单已签收，请等待工作人员审核。',
            ],
            [
                'member_id' => $memberId
            ]
        );
    }
}
