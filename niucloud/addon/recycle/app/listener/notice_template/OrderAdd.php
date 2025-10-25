<?php
declare (strict_types = 1);

namespace addon\recycle\app\listener\notice_template;

use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 回收订单下单通知
 */
class OrderAdd extends BaseNoticeTemplate
{
    private $key = 'recycle_order_add';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new CoreRecycleOrderService())->getInfo($params['data']['order_id']);
            if (!empty($order)) {
                $wap_domain = get_wap_domain($order['site_id']);
                return $this->toReturn(
                    [
                        '__wechat_page' => $wap_domain . '/addon/recycle/pages/order/detail?id=' . $order['order_id'],
                        '__weapp_page' => 'addon/recycle/pages/order/detail?id=' . $order['order_id'],
                        'order_no' => $order['order_no'],
                        'status_name' => $order['status_name'] ?? '待审核',
                        'create_time' => $order['create_time'],
                        'address' => $order['address'] ?? '暂无地址信息',
                        'remark' => '您的回收订单已提交，请等待工作人员联系。'
                    ],
                    [
                        'member_id' => $order['member_id']
                    ]
                );
            }
        }
    }
}
