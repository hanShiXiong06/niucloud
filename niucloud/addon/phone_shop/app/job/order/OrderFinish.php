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
use addon\phone_shop\app\service\core\order\CoreOrderFinishService;
use addon\phone_shop\app\service\core\order\CoreOrderCreditStateService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 订单自动收货完成
 */
class OrderFinish extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        $data = [];
        $data['main_type'] = OrderLogDict::SYSTEM;
        $data['main_id'] = 0;
        $list = (new Order())->where([
            ['status', '=', OrderDict::WAIT_TAKE],
            ['timeout', '<=', time()],
            ['timeout', '>', 0]
        ])->select();
        if(!$list->isEmpty()){
            foreach($list as $v){
                $data['order_id'] = $v['order_id'];
                $data['site_id'] = $v['site_id'];
                try {
                    // 到期不代表收到钱；待收款是正常状态，不作为队列故障反复报错。
                    if (($v['payment_mode'] ?? '') === 'offline_credit'
                        && empty((new CoreOrderCreditStateService())->getState($v->toArray())['can_complete'])) continue;
                    (new CoreOrderFinishService())->finish($data);
                } catch (\Throwable $e) {
                    Log::write(date('Y-m-d H:i:s'). ',订单自动收货完成失败:'. $e->getMessage(). '_'. $e->getFile(). '_'. $e->getLine());
                }
            }
        }
        return true;
    }

}
