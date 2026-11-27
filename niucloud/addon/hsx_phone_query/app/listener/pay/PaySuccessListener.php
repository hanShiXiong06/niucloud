<?php

namespace addon\hsx_phone_query\app\listener\pay;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryOrderDict;
use addon\hsx_phone_query\app\service\api\hsx_phone_query\HsxPhoneQueryService;

/**
 * 手机查询支付成功监听器
 */
class PaySuccessListener
{
    public function handle(array $payInfo)
    {
        if (($payInfo['trade_type'] ?? '') != HsxPhoneQueryOrderDict::TRADE_TYPE) {
            return true;
        }

        return (new HsxPhoneQueryService())->paySuccess($payInfo);
    }
}
