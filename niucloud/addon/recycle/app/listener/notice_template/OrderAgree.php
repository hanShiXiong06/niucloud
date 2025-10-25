<?php
declare (strict_types = 1);

namespace addon\recycle\app\listener\notice_template;

use addon\recycle\app\service\core\recycle_order\CoreRecycleOrderService;
use app\listener\notice_template\BaseNoticeTemplate;
use think\facade\Log;

/**
 * 订单同意通知
 */
class OrderAgree extends BaseNoticeTemplate
{
    private $key = 'recycle_order_agree';

    public function handle(array $params)
    {
        
        if ($this->key == $params['key']) {
            $order_id = $params['data']['order_id'] ?? 0;
            $order = (new CoreRecycleOrderService())->getInfo($order_id);
            
            Log::record('【回收通知】OrderAgree->handle 获取订单信息: ' . json_encode($order), 'notice');
            
            // 使用获取到的订单数据，但对于关键空字段使用静态数据
            $site_id = $order['site_id'] ?? 0;
            $member_id = $order['member_id'] ?? 0;
            
            Log::record('【回收通知】member_id  信息: ' .$member_id, 'notice');
            Log::record('【回收通知】site_id  信息: ' .$site_id, 'notice');
            

            
            $wap_domain = get_wap_domain($site_id);
            return $this->toReturn(
                [
                    '__wechat_page' => $wap_domain . '/addon/recycle/pages/order/detail?id=' . $order_id,
                    '__weapp_page' => 'addon/recycle/pages/order/detail?id=' . $order_id,
                    'order_no' => $order['order_no'],
                    'time' => date('Y-m-d H:i:s'),
                    'status' => '待确认'
                ],
                [
                    'member_id' => $member_id
                ]
            );
        }
        
        return null;
    }
}
