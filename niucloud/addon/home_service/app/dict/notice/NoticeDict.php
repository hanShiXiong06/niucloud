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

namespace addon\home_service\app\dict\notice;

/**
 * 评价相关字典类
 * Class EvaluateDict
 */
class NoticeDict
{
    //用户来源
    const TECHNICIAN = 'technician';
    const MEMBER = 'member';

    //消息来源
    const SYSTEM = 'system';
    const ORDER = 'order';
    const BILL = 'bill';

    //系统通知类型
    const AUDIT_PASS = 'audit_pass'; //审核通过

    //订单通知类型
    const GRAB_SUCCESS = 'grab_success'; //抢单成功
    const DISPATCH_SUCCESS = 'dispatch_success'; //派单成功
    const ABOUT_TO_TIMEOUT = 'about_to_timeout'; //即将超时
    const TIMEOUT = 'timeout'; //已超时
    const ITEM_PAY_SUCCESS = 'item_pay_success'; //附加项支付成功
    const REFUND = 'refund'; //发起售后
    const REFUND_SUCCESS = 'refund_success'; //退款成功
    const REFUND_FAIL = 'refund_fail'; //退款失败
    const REMINDER = 'reminder'; //催单

    //账单通知类型
    const COMMISSION_CREDITED = 'commission_credited'; //佣金到账
    const CASH_OUT_SUCCESS = 'cash_out_success'; //提现成功

    public static function getTechnicianNoticeText($notice_source = '', $status = '', $params = [])
    {
        $data = [
            self::SYSTEM => [
                self::AUDIT_PASS => [
                    'title' => get_lang('dict_home_service_technician_notice_title.audit_pass'),
                    'content' => '师傅审核通过',
                ],
            ],
            self::ORDER => [
                self::GRAB_SUCCESS => [
                    'title' => get_lang('dict_home_service_technician_notice_title.grab_success'),
                    'content' => '您在{time}成功抢单，名称为{order_name}',
                ],
                self::DISPATCH_SUCCESS => [
                    'title' => get_lang('dict_home_service_technician_notice_title.dispatch_success'),
                    'content' => '于{time}平台为您派单，名称为{order_name}',
                ],
                self::ABOUT_TO_TIMEOUT => [
                    'title' => get_lang('dict_home_service_technician_notice_title.about_to_timeout'),
                    'content' => '您的订单还有{time}超时',
                ],
                self::TIMEOUT => [
                    'title' => get_lang('dict_home_service_technician_notice_title.timeout'),
                    'content' => '您已超时{time}',
                ],
                self::ITEM_PAY_SUCCESS => [
                    'title' => get_lang('dict_home_service_technician_notice_title.item_pay_success'),
                    'content' => '客户已支付附加服务相关项',
                ],
                self::REFUND => [
                    'title' => get_lang('dict_home_service_technician_notice_title.refund'),
                    'content' => '客户已发起退款申请',
                ],
                self::REFUND_SUCCESS => [
                    'title' => get_lang('dict_home_service_technician_notice_title.refund_success'),
                    'content' => '用户退款成功',
                ],
                self::REFUND_FAIL => [
                    'title' => get_lang('dict_home_service_technician_notice_title.refund_fail'),
                    'content' => '退款申请被驳回',
                ],
                self::REMINDER => [
                    'title' => get_lang('dict_home_service_technician_notice_title.reminder'),
                    'content' => '您有一笔待处理订单（订单号：{order_no}），请尽快确认并安排服务。',
                ],
            ],
            self::BILL => [
                self::COMMISSION_CREDITED => [
                    'title' => get_lang('dict_home_service_technician_notice_title.commission_credited'),
                    'content' => '',
                ],
                self::CASH_OUT_SUCCESS => [
                    'title' => get_lang('dict_home_service_technician_notice_title.cash_out_success'),
                    'content' => '',
                ],
            ]
        ];

        if ($status == '') {
            return $data;
        }
        $notice = $data[$notice_source][$status] ?? '';
        if (is_array($notice) && !empty($params)) {
            foreach ($params as $k => $v) {
                $notice['content'] = str_replace("{" . $k . "}", $v, $notice['content']);
            }
        }

        return $notice;
    }

    /**
     * 消息来源
     * @param $type
     * @return array|mixed|string
     */
    public static function getNoticeSource($type = '')
    {
        $data = [
            self::ORDER => get_lang('dict_home_service_notice_source.order'),
            self::BILL => get_lang('dict_home_service_notice_source.bill'),
            self::SYSTEM => get_lang('dict_home_service_notice_source.system'),
        ];
        if (!$type) {
            return $data;
        }
        return $data[$type] ?? '';
    }

}
