<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\fast_pay\app\job\order;

use addon\fast_pay\app\dict\order\OrderDict;
use core\base\BaseJob;
use addon\fast_pay\app\model\order\Order;

/**
 * 订单自动关闭
 */
class OrderClose extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        try {
            $orderModel= new Order();
            $list = $orderModel->where([
                ['order_status', '=', OrderDict::WAIT_PAY],
                ['create_time', '<=', time()-60*30]
            ])->select();
            if(!$list->isEmpty()){
                foreach($list as $v){
                    $data['order_id'] = $v['order_id'];
                    $data['site_id'] = $v['site_id'];
                    $orderModel->where($data)->update(
                        [
                            'order_status'=>OrderDict::CLOSE,
                            'close_time'=>time(),
                            'close_reason'=>'订单超时未支付关闭'
                        ]);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
