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
 * 旅游订单相关字典类
 * Class HotelOrderDict
 * @package app\dict\order
 */
class RefundDict
{
    // 退款相关状态
    // 待退款
    const WAIT_REFUND = 'wait_refund';
    // 退款完成
    const REFUND_COMPLETED = 'refund_completed';
    // 退款失败
    //const REFUND_FAIL = 'refund_fail';
    // 拒绝退款申请
    const REFUND_REFUSE = 'refund_refuse';

    const CANCEL = 'cancel';

    const SERVICE_NOT_COMPLETED_AGREED = 'service_not_completed_agreed';//服务未按约定完成
    const SERVICE_QUALITY_NOT_MEET = 'service_quality_not_meet';//服务质量不达标
    const ITEM_PRICE_UNREASONABLE = 'item_price_unreasonable';//额外收费不合理
    const SERVICE_PERSONNEL_ATTITUDE_PROBLEM = 'service_personnel_attitude_problem';//服务人员态度问题
    const OTHER_REASONS = 'other_reasons';//其他原因

    /**
     * 获取退款状态
     * @param string $status
     * @return array|array[]
     */
    public static function getRefundStatus(string $status = '')
    {
        $data = [
            self::WAIT_REFUND => [
                'name' => get_lang('dict_home_service_order_refund.wait_refund'),
                'status' => self::WAIT_REFUND
            ],
            self::REFUND_COMPLETED => [
                'name' => get_lang('dict_home_service_order_refund.refund_completed'),
                'status' => self::REFUND_COMPLETED
            ],
            self::REFUND_REFUSE => [
                'name' => get_lang('dict_home_service_order_refund.refund_refuse'),
                'status' => self::REFUND_REFUSE
            ],
            //            self::REFUND_FAIL => [
            //                'name' => get_lang('dict_home_service_order_refund.refund_fail'),
            //                'status' => self::REFUND_FAIL
            //            ] ,
            self::CANCEL => [
                'name' => get_lang('dict_home_service_order_refund.cancel'),
                'status' => self::CANCEL
            ]
        ];

        if ($status == '') {
            return $data;
        }
        return $data[$status] ?? [];
    }

    /**
     * 拒绝理由
     * @return array
     */
    public static function getRefundReason($status = '')
    {
        $data = [
            self::SERVICE_NOT_COMPLETED_AGREED =>get_lang('dict_home_service_order_refund_reason.service_not_completed_agreed'),//未按约定时间发货
            self::SERVICE_QUALITY_NOT_MEET =>get_lang('dict_home_service_order_refund_reason.service_quality_not_meet'),//服务质量不达标
            self::ITEM_PRICE_UNREASONABLE =>get_lang('dict_home_service_order_refund_reason.item_price_unreasonable'),//额外收费不合理
            self::SERVICE_PERSONNEL_ATTITUDE_PROBLEM =>get_lang('dict_home_service_order_refund_reason.service_personnel_attitude_problem'),//服务人员态度问题
            self::OTHER_REASONS =>get_lang('dict_home_service_order_refund_reason.other_reasons'),//其他原因
        ];

        if ($status == '') {
            return $data;
        }
        return $data[$status] ?? [];
    }
}
