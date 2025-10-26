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
 * 师傅结算导出数据类型查询
 * Class HomeServiceStoreSettlementExportTypeListener
 * @package app\listener\export
 */
class HomeServiceTechnicianSettlementExportTypeListener
{

    public function handle()
    {
        return [
            'home_service_technician_settlement' => [
                'name' => '师傅结算列表',
                'column' => [
                    'technician_name' => [ 'name' => '师傅名称'],
                    'month' => [ 'name' => '结算周期'],
                    'total_count' => [ 'name' => '汇总订单数'],
                    'total_amount' => [ 'name' => '结算金额'],
                ],
            ]
        ];
    }
}
