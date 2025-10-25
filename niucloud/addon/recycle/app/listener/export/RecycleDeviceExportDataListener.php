<?php

namespace addon\recycle\app\listener\export;

use addon\recycle\app\model\RecycleDevice;

/**
 * 回收设备导出数据监听器
 */
class RecycleDeviceExportDataListener
{
    /**
     * 数据类型定义
     * @param array|null $param
     * @return array
     */
    public function handle($param = [])
    {
        // 确保参数是数组类型
        if (!is_array($param)) {
            $param = [];
        }
        
        return [
            'recycle_device' => [
                'name' => '回收设备',
                'column' => [
                    'order_no' => [
                        'name' => '订单号',
                    ],
                    'imei' => [
                        'name' => 'imei',
                    ],
                    'imei2' => [
                        'name' => 'imei2',
                    ],
                    'sn' => [
                        'name' => 'sn',
                    ],
                    // 'brand_name' => [
                    //     'name' => '品牌',
                    // ],
                    'model' => [
                        'name' => '品牌型号',
                    ],
                    'nickname' => [
                        'name' => '供应商',
                    ],
                    // 'category_name' => [
                    //     'name' => '设备分类',
                    // ],
                    'check_result' => [
                        'name' => '备注',
                    ],
                    'create_at' => [
                        'name' => '创建时间',
                    ],
                    'code' => [
                        'name' => '条形码',
                    ],
                   
                    
                ]
            ]
        ];
    }
}
