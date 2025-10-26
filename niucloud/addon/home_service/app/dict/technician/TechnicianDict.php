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

namespace addon\home_service\app\dict\technician;

/**
 * 师傅相关字典类
 * Class TechnicianDict
 */
class TechnicianDict
{
    const ON = 1;
    const OFF = 0;
    const depart = -1;

    /**
     * 获取师傅状态
     */
    public static function getTechnicianStatus()
    {
        return [
            self::ON => get_lang('dict_home_service_technician_status.status_on'),//正常
            self::OFF => get_lang('dict_home_service_technician_status.status_off'),//休息
//            self::depart => get_lang('dict_home_service_technician_status.status_depart'),//离职
        ];
    }

    const   REFUSE = -1;
    const   PENDING_EXAMINE = 0;
    const   PASS = 1;

    /**
     * 获取师傅状态  审核状态：0-待审核 1-通过  -1 -拒绝
     */
    public static function getTechnicianApplicationStatus()
    {
        return [
            self::PENDING_EXAMINE => get_lang('dict_home_service_technician_application_status.pending_examine'),//待审核
            self::PASS => get_lang('dict_home_service_technician_application_status.pass'),//正常
            self::REFUSE => get_lang('dict_home_service_technician_application_status.refuse'),//拒绝
        ];
    }


    const   DEFAULT = "default";
    const   CUSTOMIZE = "customize";


    /**
     * 获取师傅分配方式
     */
    public static function getTechnicianDistributeName()
    {
        return [
            self::DEFAULT => get_lang('dict_home_service_technician_distribute.default'),//默认
            self::CUSTOMIZE => get_lang('dict_home_service_technician_distribute.customize'),//自定义
        ];
    }


    const   INTERNAL = "internal";
    const   APPLICATION = "application";


    /**
     * 获取来源分配方式   source_name
     */
    public static function getTechnicianSourceName()
    {
        return [
            self::INTERNAL => get_lang('dict_home_service_technician_source_name.internal'),//默认
            self::APPLICATION => get_lang('dict_home_service_technician_source_name.application'),//自定义
        ];
    }


}
