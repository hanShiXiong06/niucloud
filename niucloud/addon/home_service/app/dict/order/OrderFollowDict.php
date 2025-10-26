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
 *  回访 字典类
 * Class InvoiceDict
 */
class OrderFollowDict
{

    // 回访结果
    const FOLLOW_RESULT_RESOLVED = 'resolved';// 已解决
    const FOLLOW_RESULT_UNRESOLVED = 'unresolved';// 未解决

// 收费情况
    const FEE_SITUATION_CONSISTENT = 'consistent';// 收费一致
    const FEE_SITUATION_INCONSISTENT = 'inconsistent';// 有出入

    /**
     * 回访结果
     * @param $result
     * @return array|mixed|string
     */
    public static function getFollowResult($result = '')
    {
        $data = [
            self::FOLLOW_RESULT_RESOLVED => get_lang('dict_home_service_follow_result.resolved'),
            self::FOLLOW_RESULT_UNRESOLVED => get_lang('dict_home_service_follow_result.unresolved'),
        ];
        if (!$result) {
            return $data;
        }
        return $data[$result] ?? '';
    }

    /**
     * 收费情况
     * @param $situation
     * @return array|mixed|string
     */
    public static function getFeeSituation($situation = '')
    {
        $data = [
            self::FEE_SITUATION_CONSISTENT => get_lang('dict_home_service_fee_situation.consistent'),
            self::FEE_SITUATION_INCONSISTENT => get_lang('dict_home_service_fee_situation.inconsistent'),
        ];
        if (!$situation) {
            return $data;
        }
        return $data[$situation] ?? '';
    }


}
