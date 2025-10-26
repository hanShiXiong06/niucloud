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

namespace addon\home_service\app\dict\card;

/**
 * 会员卡项相关字典类
 * Class MemberCardDict
 */
class MemberCardDict
{
    CONST WAIT_USE = 'wait_use';

    CONST USED = 'used';

    CONST EXPIRE = 'expire';

    public static function getStatus($status = '')
    {
        $data = [
            self::WAIT_USE => [
                'name' => get_lang('dict_home_service_membercard_status.wait_use'),
                'status' => self::WAIT_USE,
            ],
            self::USED => [
                'name' => get_lang('dict_home_service_membercard_status.used'),
                'status' => self::USED,
            ],
            self::EXPIRE => [
                'name' => get_lang('dict_home_service_membercard_status.expire'),
                'status' => self::EXPIRE,
            ],
        ];

        if ($status == '') {
            return $data;
        }
        return $data[ $status ] ?? '';
    }
}
