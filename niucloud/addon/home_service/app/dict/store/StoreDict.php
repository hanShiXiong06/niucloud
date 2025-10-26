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
 * 门店相关字典类
 * Class TechnicianDict
 */
class StoreDict
{
    const   REFUSE = -1;
    const   PENDING_EXAMINE = 0;
    const   PASS = 1;

    /**
     * 获取门店审核状态：0-待审核 1-通过  -1 -拒绝
     */
    public static function getStoreApplicationStatus()
    {
        return [
            self::PENDING_EXAMINE => get_lang('dict_home_service_store_application_status.pending_examine'),//待审核
            self::PASS => get_lang('dict_home_service_store_application_status.pass'),//正常
            self::REFUSE => get_lang('dict_home_service_store_application_status.refuse'),//拒绝
        ];
    }


}
