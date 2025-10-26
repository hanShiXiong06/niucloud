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

class HelpCategoryDict
{

    const YES = 1;
    const NO = 0;

    /**
     * 是否展示
     * @param $status
     * @return array|mixed|string
     */
    public static function getIsShow($type = '')
    {
        $list = [
            self::YES => get_lang('dict_home_service_help_category_is_show.yes'),
            self::NO => get_lang('dict_home_service_help_category_is_show.no'),
        ];
        if ($type == '') return $list;
        return $list[ $type ] ?? '';
    }



}
