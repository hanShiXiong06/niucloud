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

namespace addon\home_service\app\dict\order;

/**
 * 评价相关字典类
 * Class EvaluateDict
 */
class EvaluateDict
{
    const AUDIT_NO = 0;
    const AUDIT = 1;
    const AUDIT_ADOPT = 2;
    const AUDIT_REFUSE = 3;

    /**
     * 评价状态
     * @param $type
     * @return array|mixed|string
     */
    public static function getStatus($type = '')
    {
        $data = [
            self::AUDIT_NO => get_lang('dict_home_service_goods_evaluate.audit_no'), // 无需审核
            self::AUDIT => get_lang('dict_home_service_goods_evaluate.audit'), // 待审核
            self::AUDIT_ADOPT => get_lang('dict_home_service_goods_evaluate.audit_adopt'), // 审核通过
            self::AUDIT_REFUSE => get_lang('dict_home_service_goods_evaluate.audit_refuse'), // 审核拒绝
        ];
        if (!$type) {
            return $data;
        }
        return $data[$type] ?? '';
    }

}
