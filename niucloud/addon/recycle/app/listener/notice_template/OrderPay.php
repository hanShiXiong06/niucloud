<?php
declare (strict_types = 1);

namespace addon\recycle\app\listener\notice_template;

use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 订单打款通知
 */
class OrderPay extends BaseNoticeTemplate
{
    private $key = 'recycle_order_pay';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new CoreRecycleOrderService())->getInfo($params['data']['order_id']);
            if (!empty($order)) {
                $wap_domain = get_wap_domain($order['site_id']);
                return $this->toReturn(
                    [
                        '__wechat_page' => $wap_domain . '/addon/recycle/pages/order/detail?id=' . ($order['id'] ?? $params['data']['order_id']),
                        '__weapp_page' => 'addon/recycle/pages/order/detail?id=' . ($order['id'] ?? $params['data']['order_id']),
                        'order_no' => $order['order_no'],
                        'pay_type' => $order['pay_type_name'] ?? '线下支付',
                        'pay_account' => $order['pay_account'] ?? '请查看订单详情',
                        'pay_result' => '打款成功'
                    ],
                    [
                        'member_id' => $order['member_id']
                    ]
                );
            }
        }
    }
}
