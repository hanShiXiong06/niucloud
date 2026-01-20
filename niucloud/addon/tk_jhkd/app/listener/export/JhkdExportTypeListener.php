<?php
// +---------------------------------------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\listener\export;

/**
 * 导出数据类型查询
 */
class JhkdExportTypeListener
{

    public function handle()
    {
        return [
            'tk_jhkd_order' => [
                'name' => '聚合快递订单导出',
                'column' => [
                    'order_id' => ['name' => '订单号'],
                    'out_trade_no' => ['name' => '支付单号'],
                    'delivery_name' => ['name' => '快递渠道'],
                    'order_status_name' => ['name' => '订单状态'],
                    'order_money' => ['name' => '订单金额'],
                    'pay_money' => ['name' => '支付金额'],
                    'total_fee' => ['name' => '三方扣费'],
                    'add_fee' => ['name' => '补差价'],
                    'add_status_name' => ['name' => '补差支付状态'],
                    'profit' => ['name' => '预估收益'],
                    'create_time' => ['name' => '创建时间'],
                ]
            ]
        ];
    }
}