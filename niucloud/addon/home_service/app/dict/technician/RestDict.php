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
 *请假理由
 * Class TechnicianDict
 */
class RestDict
{



    // 请假理由常量
    const PERSONAL_AFFAIRS = 1;       // 个人事务
    const SICK_LEAVE = 2;             // 生病
    const FAMILY_EMERGENCY = 3;       // 家庭紧急事务
    const MARRIAGE = 4;               // 结婚
    const FUNERAL = 5;                // 奔丧
    const MATERNITY = 6;              // 产假
    const PATERNITY = 7;              // 陪产假
    const ANNUAL_LEAVE = 8;           // 年假
    const BUSINESS = 9;               // 公事
    const STUDY = 10;                 // 学习培训
    const TRAVEL = 11;                // 旅行
    const OTHER = 12;                 // 其他原因

    /**
     * 获取所有请假理由
     */
    public static function getRestType()
    {
        return [
            self::PERSONAL_AFFAIRS => get_lang('dict_home_service_rest.personal_affairs'),
            self::SICK_LEAVE => get_lang('dict_home_service_rest.sick_leave'),
            self::FAMILY_EMERGENCY => get_lang('dict_home_service_rest.family_emergency'),
            self::MARRIAGE => get_lang('dict_home_service_rest.marriage'),
            self::FUNERAL => get_lang('dict_home_service_rest.funeral'),
            self::MATERNITY => get_lang('dict_home_service_rest.maternity'),
            self::PATERNITY => get_lang('dict_home_service_rest.paternity'),
            self::ANNUAL_LEAVE => get_lang('dict_home_service_rest.annual_leave'),
            self::BUSINESS => get_lang('dict_home_service_rest.business'),
            self::STUDY => get_lang('dict_home_service_rest.study'),
            self::TRAVEL => get_lang('dict_home_service_rest.travel'),
            self::OTHER => get_lang('dict_home_service_rest.other'),
        ];
    }





}