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

namespace addon\phone_shop\app\listener\pay;


use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\service\core\refund\CoreRefundService;
use think\facade\Log;

/**
 * 退款异步回调事件
 */
class RefundSuccessListener
{
    public function handle(array $params)
    {
        
      
        if ($params[ 'trade_type' ] == OrderDict::TYPE) {
           
            ( new CoreRefundService() )->transferSuccess([
                'refund_no' => $params[ 'refund_no' ],
                'order_refund_no' => $params[ 'trade_id' ],
            ]);
            return true;
        }
    }
}