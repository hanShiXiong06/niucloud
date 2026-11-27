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
use addon\phone_shop\app\service\core\order\CoreOrderPaymentGuardService;

/**
 * 支付创建事件
 */
class PayCreateListener
{
    public function handle(array $params)
    {
        $trade_type = $params['trade_type'] ?? '';
        if (in_array($trade_type, [ OrderDict::TYPE ])) {
            return (new CoreOrderPaymentGuardService())->getOrderPayment(
                (int)($params['site_id'] ?? 0),
                (int)($params['trade_id'] ?? 0)
            );
        }
    }
}
