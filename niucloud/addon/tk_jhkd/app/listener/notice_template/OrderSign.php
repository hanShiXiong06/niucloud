<?php
declare (strict_types=1);

namespace addon\tk_jhkd\app\listener\notice_template;

use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单签收
 */
class OrderSign extends BaseNoticeTemplate
{
    private $key = 'tk_jhkd_order_sign';

    public function handle(array $params)
    {
        if ($this->key == $params['key']) {
            $order = (new Tkjhkdorder())->where(['order_id' => $params['data']['order_id']])->findOrEmpty();
            if (!$order->isEmpty()) {
                $wap_domain = get_wap_domain($order['site_id']);
                return $this->toReturn(
                    [
                        '__wechat_page' => $wap_domain . '/addon/tk_jhkd/pages/orderdetail?id=' . $order['id'],//模板消息链接
                        '__weapp_page' => 'addon/tk_jhkd/pages/orderdetail?id=' . $order['id'],//小程序链接
                        'remark' => '您的运单显示已签收，请及时关注运单信息',
                        'order_no' => $order['order_id'],
                    ],
                    [
                        'member_id' => $order['member_id']
                    ]
                );
            }
        }
    }
}
