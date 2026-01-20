<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\order;

use addon\tk_jhkd\app\dict\order\JhkdOrderAddDict;
use addon\tk_jhkd\app\job\notice\Webhook;
use addon\tk_jhkd\app\service\core\CommonService;
use addon\tk_jhkd\app\service\core\OrderLogService;
use app\service\core\notice\NoticeService;
use core\base\BaseJob;
use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\model\order\OrderAdd;
use think\facade\Cache;

/**
 * 订单催收
 */
class AddPay extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        try {
            $orderModel= new OrderAdd();
            $list = $orderModel->where([
                ['order_status', '=', JhkdOrderAddDict::WAIT_PAY],
//                ['create_time', '<=', time()-60]
            ])->select();
            if(!$list->isEmpty()){
                foreach($list as $v){
                    $data['order_id'] = $v['order_id'];
                    $data['site_id'] = $v['site_id'];
                    if(!Cache::get('tk_jhkd_order_add_'.$v['order_id'])){
                         (new NoticeService())->send($data['site_id'], 'tk_jhkd_order_add', ['order_id' => $data['order_id']]);
                        //更改通知次数
                        $orderModel->where(['order_id'=>$data['order_id'],'site_id'=>$data['site_id']])->update(['notice_num'=>$v['notice_num']+1]);
                        $config=(new CommonService())->getConfig($data['site_id']);
                        $text = '补差价订单号：'.$data['order_id'].',待补差价金额：'.$v['order_money'].'元，请及时催顾客付款';
                        Webhook::dispatch(['config' => $config, 'text' => $text]);
                        Cache::set('tk_jhkd_order_add_'.$v['order_id'], '补差价通知', 60*60*23);
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
