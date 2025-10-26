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

namespace addon\home_service\app\dict\cash_out;

class CashOutDict
{

    const STORE = 'store';
    const TECHNICIAN = 'technician';

    const WAIT_TRANSFER = 1;//待转账
    const TRANSFERED = 2;//已转账
    const CANCEL = -1;//已取消

    const TECHNICIAN_CASH_OUT = 'technician_cash_out';//师傅提现
    const STORE_CASH_OUT = 'store_cash_out';//门店提现

    /**
     * 提现来源
     * @param $status
     * @return array|mixed|string
     */
    public static function getSource($type = '')
    {
        $list = [
            self::STORE => get_lang('dict_home_service_cash_out_source.store'),
            self::TECHNICIAN => get_lang('dict_home_service_cash_out_source.technician'),
        ];
        if ($type == '') return $list;
        return $list[ $type ] ?? '';
    }

    /**
     * 提现状态
     * @return array
     */
    public static function getStatus()
    {
        return [
            self::WAIT_TRANSFER => get_lang('dict_home_service_cash_out.wait_transfer'),//待转账
            self::TRANSFERED => get_lang('dict_home_service_cash_out.transfered'),//已转账
            self::CANCEL => get_lang('dict_home_service_cash_out.cancel'),//已取消
        ];
    }


}
