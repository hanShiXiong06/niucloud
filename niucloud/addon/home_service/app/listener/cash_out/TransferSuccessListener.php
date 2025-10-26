<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\listener\cash_out;

use addon\home_service\app\dict\cash_out\CashOutDict;
use addon\home_service\app\service\core\cash_out\CoreCashOutService;
use app\service\core\site\CoreSiteAccountService;

/**
 * 转账事件
 */
class TransferSuccessListener
{
    public function handle(array $info)
    {
        //添加账单记录
        (new CoreSiteAccountService())->addTransferLog($info['site_id'], $info['transfer_no']);
        //师傅零钱提现
        if (in_array($info['trade_type'],[CashOutDict::TECHNICIAN_CASH_OUT,CashOutDict::STORE_CASH_OUT])) {
            return (new CoreCashOutService())->transferFinish($info['site_id'], $info['transfer_no']);
        }
    }
}
