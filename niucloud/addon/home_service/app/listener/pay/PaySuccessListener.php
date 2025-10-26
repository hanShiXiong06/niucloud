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

use addon\home_service\app\dict\card\CardOrderDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\service\core\card\CoreCardOrderCreateService;
use addon\home_service\app\service\core\order\CoreOrderPayService;

/**
 * 支付异步回调事件
 */
class PaySuccessListener
{
    public function handle(array $pay_info)
    {
        $trade_type = $pay_info['trade_type'] ?? '';
        if ($trade_type == OrderDict::ORDER_TYPE_ORDER) {
            (new CoreOrderPayService())->pay($pay_info);
        }
        if ($trade_type == CardOrderDict::TYPE) {
            (new CoreCardOrderCreateService())->pay($pay_info);
        }
        if ($trade_type == OrderDict::ORDER_TYPE_ITEM) {
            (new CoreOrderPayService())->itemPay($pay_info);
        }
    }
}
