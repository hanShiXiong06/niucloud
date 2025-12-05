<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\phone_shop\app\job\order;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\service\core\order\CoreOrderCloseService;
use core\base\BaseJob;

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
            $data = [];
            $data['main_type'] = OrderLogDict::SYSTEM;
            $data['main_id'] = 0;
            $data['close_type'] = OrderDict::AUTO_CLOSE;
            $list = (new Order())->where([
                ['status', '=', OrderDict::WAIT_PAY],
                ['timeout', '<=', time()],
                ['timeout', '>', 0]
            ])->select();
            if(!$list->isEmpty()){
                foreach($list as $v){
                    $data['order_id'] = $v['order_id'];
                    $data['site_id'] = $v['site_id'];
                    (new CoreOrderCloseService())->close($data);
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
