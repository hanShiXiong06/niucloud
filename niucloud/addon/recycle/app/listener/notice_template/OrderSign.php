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
        
        
        if ($this->key == $params['key']) {
            $order_id = $params['data']['order_id'] ?? 0;
            
         
            
            // 2. 如果params中没有member_id，再从订单中获取
           
            $order = (new CoreRecycleOrderService())->getInfo($order_id);
            
            // 从订单中获取site_id和member_id
            $site_id = $order['site_id'] ?? 0;
            $member_id = $order['member_id'] ?? 0;
            
           
            
            // 3. 生成一个静态的订单编号（如果需要可以从订单中获取）
            $order_no =  $order_id = $params['data']['order_id'] ?? 0;
            
            // 4. 获取域名
            $wap_domain = get_wap_domain($site_id);
            
            
            return $this->toReturn(
                [
                    '__wechat_page' => $wap_domain . '/addon/recycle/pages/order/detail?id=' . $order_id,
                    '__weapp_page' => 'addon/recycle/pages/order/detail?id=' . $order_id,
                    'order_no' => $order_no,
                    'remark' => '您的回收订单已签收，请等待工作人员审核。',

                ],
                [
                    'member_id' => $member_id
                ]
            );
        }
        
        return null;
    }
}
