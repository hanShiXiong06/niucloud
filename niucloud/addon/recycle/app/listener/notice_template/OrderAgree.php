<?php
declare (strict_types = 1);

namespace addon\recycle\app\listener\notice_template;

use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单验收通知（待确认通知）
 */
class OrderAgree extends BaseNoticeTemplate
{
    private $key = 'recycle_order_agree';

    public function handle(array $params)
    {
        if ($this->key !== ($params['key'] ?? '')) {
            return null;
        }

        $orderId = (int)($params['data']['order_id'] ?? 0);
        if ($orderId <= 0) {
            Log::error('【回收通知】OrderAgree 订单ID为空');
            return null;
        }

        $order = (new CoreRecycleOrderService())->getInfo($orderId);
        if (empty($order)) {
            Log::error('【回收通知】OrderAgree 订单不存在: ' . $orderId);
            return null;
        }

        $siteId = (int)($order['site_id'] ?? 0);
        $memberId = (int)($order['member_id'] ?? 0);
        $wapDomain = get_wap_domain($siteId);

        $deliveryType = $order['delivery_type'] ?? 1;
        $deliveryTypeName = $deliveryType == 1 ? '快递' : '自送';

        return $this->toReturn(
            [
                '__wechat_page' => $wapDomain . '/addon/recycle/pages/order/detail?id=' . $orderId,
                '__weapp_page' => 'addon/recycle/pages/order/detail?id=' . $orderId,
                'order_no' => $order['order_no'] ?? '',
                'time' => date('Y-m-d H:i:s'),
                'status' => '待确认',
                'delivery_type' => $deliveryType,
                'delivery_type_name' => $deliveryTypeName,
                'url' => '/mplink/a5f'
            ],
            [
                'member_id' => $memberId
            ]
        );
    }
}
