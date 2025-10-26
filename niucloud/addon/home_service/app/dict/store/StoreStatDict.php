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

namespace addon\home_service\app\dict\store;

/**
 * 师傅统计相关字典类
 * Class TechnicianDict
 */
class StoreStatDict
{
    const FINISH = 'finish';
    const REFUND = 'refund';
    const CLOSE = 'close';

    /**
     * 获取门店统计订单状态
     */
    public static function getStoreStatOrderStatus($type = '')
    {

        $list = [
            self::FINISH => get_lang('dict_home_service_order_stat_status.finish'),
            self::REFUND => get_lang('dict_home_service_order_stat_status.refund'),
            self::CLOSE => get_lang('dict_home_service_order_stat_status.close'),
        ];
        if ($type == '') return $list;
        return $list[ $type ] ?? '';
    }


}
