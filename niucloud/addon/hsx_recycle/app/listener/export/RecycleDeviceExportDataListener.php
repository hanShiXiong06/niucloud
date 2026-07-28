<?php

namespace addon\hsx_recycle\app\listener\export;

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
                    'color'=>[
                         'name' => '颜色'
                    ],
                    'package_type'=>[
                        'name' => '包装'
                    ],
                    
                    'capacity'=>[
                         'name' => '内存'
                    ],
                    'warranty_info'=>[
                        'name' => '保修'
                    ],
                    'final_price'=>[
                        'name' =>'最终价格'
                    ],
                    'sell_price'=>[
                        'name' =>'销售/挂牌价'
                    ],
                    'warehouse_type_name'=>[
                        'name' =>'入库类型'
                    ],
                    'is_merchant_owned'=>[
                        'name' =>'是否商家自有'
                    ],
                    'consignment_no'=>[
                        'name' =>'代卖单号'
                    ],
                    'nickname' => [
                        'name' => '供应商',
                    ],
                    'quoter_name' => [
                        'name' => '报价人',
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
