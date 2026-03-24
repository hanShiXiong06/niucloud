<?php
declare (strict_types = 1);

namespace addon\recycle\app\listener\notice_template;

use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;
/**
 * 回收订单下单通知
 */
class OrderAdd extends BaseNoticeTemplate
{
    private $key = 'recycle_order_add';

    public function handle(array $params)
    {
        if ($this->key !== ($params['key'] ?? '')) {
            return null;
        }

        $orderId = (int)($params['data']['order_id'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $order = (new CoreRecycleOrderService())->getInfo($orderId);
        if (empty($order)) {
            return null;
        }

        $siteId = (int)($order['site_id'] ?? 0);
        $memberId = (int)($order['member_id'] ?? 0);
        $pageOrderId = (int)($order['id'] ?? $orderId);
        $wapDomain = get_wap_domain($siteId);

        // 获取域名，避免在多域名环境下报错
        $domain = request()->domain();

        

        $deliveryType = $order['delivery_type'] ?? 1;
        $deliveryTypeName = $deliveryType == 1 ? '快递' : '自送';

        return $this->toReturn(
            [
                '__wechat_page' => $wapDomain . '/addon/recycle/pages/order/detail?id=' . $pageOrderId,
                '__weapp_page' => 'addon/recycle/pages/order/detail?id=' . $pageOrderId,
                'order_no' => $order['order_no'] ?? '',
                'shop_name' => $params['data']['shop_name'] ?? '回收中心',
                'create_time' => $order['create_at'] ?? date('Y-m-d H:i:s'),
                'address' => $params['data']['address'] ?? ($order['address'] ?? '待确认'),
                'delivery_type' => $deliveryType,
                'delivery_type_name' => $deliveryTypeName,
                'remark' => '您的回收订单已提交，请等待工作人员联系。',
                'url' => '/mplink/a5f'
            ],
            [
                'member_id' => $memberId
            ]
        );
    }
}
