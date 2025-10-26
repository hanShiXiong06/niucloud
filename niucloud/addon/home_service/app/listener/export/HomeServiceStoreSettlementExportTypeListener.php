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
 * 门店结算导出数据类型查询
 * Class HomeServiceStoreSettlementExportTypeListener
 * @package app\listener\export
 */
class HomeServiceStoreSettlementExportTypeListener
{

    public function handle()
    {
        return [
            'home_service_store_settlement' => [
                'name' => '门店结算列表',
                'column' => [
                    'store_name' => [ 'name' => '机构名称'],
                    'month' => [ 'name' => '结算周期'],
                    'total_count' => [ 'name' => '汇总订单数'],
                    'total_amount' => [ 'name' => '结算金额'],
                ],
            ]
        ];
    }
}
