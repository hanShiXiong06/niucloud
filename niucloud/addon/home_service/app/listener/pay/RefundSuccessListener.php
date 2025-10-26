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

namespace addon\home_service\app\listener\pay;

use addon\home_service\app\service\core\order\CoreOrderRefundService;

/**
 * 退款异步回调事件
 */
class RefundSuccessListener
{
    public function handle(array $params)
    {
        if($params['trade_type'] == 'home_service'){
            $res = (new CoreOrderRefundService())->refundSuccess($params['refund_no']);
            return true;
        }
    }
}
