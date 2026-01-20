<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\listener\pay;

use addon\tk_jhkd\app\service\core\OrderService;
use app\service\core\site\CoreSiteAccountService;
use think\facade\Log;

/**
 * 支付异步回调事件
 */
class PaySuccessListener
{
    public function handle(array $pay_info)
    {
        $trade_type = $pay_info['trade_type'] ?? '';
        if ($trade_type == 'jhkdOrderPay') {
            (new OrderService())->pay($pay_info);
        }
        if ($trade_type == 'jhkdOrderAddPay') {
            (new OrderService())->payAdd($pay_info);
        }
    }
}