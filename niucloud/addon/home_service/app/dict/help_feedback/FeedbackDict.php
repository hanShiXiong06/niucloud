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

namespace addon\home_service\app\dict\help_feedback;

class FeedbackDict
{

    const MEMBER = 'member';
    const STORE = 'store';
    const TECHNICIAN = 'technician';



    /**
     * 反馈来源
     * @param $status
     * @return array|mixed|string
     */
    public static function getSource($type = '')
    {
        $list = [
            self::MEMBER => get_lang('dict_home_service_feedback_type.member'),
            self::STORE => get_lang('dict_home_service_feedback_type.store'),
            self::TECHNICIAN => get_lang('dict_home_service_feedback_type.technician'),
        ];
        if ($type == '') return $list;
        return $list[ $type ] ?? '';
    }

}
