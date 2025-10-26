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

namespace addon\home_service\app\dict\account;


/**
 * 订单账单相关字典类
 * Class CardOrderDict
 * @package app\dict\card
 */
class AccountDict
{
    const MONEY = 'money';

    const COMMISSION = 'commission';

    const ORDER_COMMISSION = 'order_commission';

    const ORDER_REFUND_COMMISSION = 'order_refund_commission';

    const CASH_OUT = 'cash_out';


    /**
     *
     */
    public static function getType()
    {
        return [
            self::ORDER_COMMISSION => get_lang('dict_home_service_account.order_commission'),
            self::ORDER_REFUND_COMMISSION => get_lang('dict_home_service_account.order_refund_commission'),
            self::CASH_OUT => get_lang('dict_home_service_account.cash_out'),
        ];
    }


    const PENDING_SETTLEMENT = 0;

    const SETTLED = 1;

    /**
     *结算状态
     */
    public static function getStatus()
    {
        return [
            self::PENDING_SETTLEMENT => get_lang('dict_home_service_account_status.pending_settlement'),
            self::SETTLED => get_lang('dict_home_service_account_status.settled'),
        ];
    }

    /**
     *
     */
    public static function getAccountType()
    {
        return [
            self::MONEY => get_lang('dict_home_service_account_type.money'),
            self::COMMISSION => get_lang('dict_home_service_account_type.commission'),
        ];
    }

}
