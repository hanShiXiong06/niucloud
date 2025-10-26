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

namespace addon\home_service\app\listener\export;

/**
 * 订单导出数据类型查询
 * Class HomeServiceInvoiceExportTypeListener
 * @package app\listener\export
 */
class HomeServiceOrderExportTypeListener
{

    public function handle()
    {
        return [
            'home_service_order' => [
                'name' => '订单列表',
                'column' => [
                    'order_no' => ['name' => '订单号'],
                    'category_name' => ['name' => '服务类型'],
                    'order_name' => ['name' => '服务项目'],
                    'order_status_name' => ['name' => '订单状态'],
                    'order_money' => ['name' => '订单金额'],
                    'taker_name' => ['name' => '客户'],
                    'taker_mobile' => ['name' => '手机号'],
                    'member_message' => ['name' => '客户备注'],
                    'taker_full_address' => ['name' => '服务地址'],
                    'technician_name' => ['name' => '师傅名称'],
                    'technician_sum_commission' => ['name' => '师傅分成'],
                    'store_name' => ['name' => '门店名称'],
                    'store_sum_commission' => ['name' => '门店分成'],
                    'settlement_name' => ['name' => '是否结算'],
                    'create_time' => ['name' => '创建时间'],
                    'reserve_service_time' => ['name' => '预约时间'],
                    'service_time' => ['name' => '服务时间'],
                    'finish_time' => ['name' => '完成时间'],
                    'service_time_text' => ['name' => '服务时长'],
                ],
            ]
        ];
    }
}
