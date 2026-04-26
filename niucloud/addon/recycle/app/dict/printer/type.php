<?php

use addon\recycle\app\service\core\delivery\CoreDeliveryService;

// 配送方式列表
$print_delivery_list = [
    // [
    //     'name' => '自提回收',
    //     'value' => 'self'
    // ],
    // [
    //     'name' => '上门回收',
    //     'value' => 'home'
    // ]
];

return [
    // [
    //     'key' => 'recycleOrder',
    //     'title' => '回收订单', // 模板类型名称
    //     'sort' => 10000,
    //     //  触发打印时机，定义何时触发调用
    //     'trigger' => [
    //         'submit_after' => '提交后',
    //         'complete_after' => '完成后',
    //         'manual' => '手动'
    //     ],
    //     // 根据业务可自行扩展筛选条件
    //     'condition' => [
    //         [
    //             'key' => 'print_delivery_type',
    //             'title' => '回收方式',
    //             'type' => 'checkbox',
    //             'list' => $print_delivery_list
    //         ],
    //     ],
    //     'path' => 'preview-recycle-order', // 实时预览组件名称
    //     // 模板内容
    //     'template' => [
    //         [
    //             'key' => 'head_info',
    //             'title' => '票头',
    //             'list' => [
    //                 [
    //                     'key' => 'ticket_name',
    //                     'label' => '小票名称',
    //                     'value' => '回收订单', // 存储的初始值，可以是字符串、数组格式
    //                     'placeholder' => '', // 输入框占位符
    //                     'type' => 'input', // 类型，空：无需设置，input：输入框、checkbox：复选框，select：下拉框
    //                     'status' => 1, // 状态（1：显示，0：隐藏）
    //                     'disabled' => false, // 是否禁止操作显示隐藏
    //                     'fontSize' => 'normal', // 字号，normal：正常，small:，big：大
    //                     'fontWeight' => 'normal', // 粗细，normal：正常，weight：加粗
    //                     'remark' => '', // 说明
    //                 ],
    //                 [
    //                     'key' => 'shop_name',
    //                     'label' => '平台名称',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'big',
    //                     'fontWeight' => 'bold',
    //                     'remark' => '',
    //                 ],
    //             ]
    //         ],
    //         [
    //             'key' => 'order_info',
    //             'title' => '订单信息',
    //             'list' => [
    //                 [
    //                     'key' => 'order_item',
    //                     'label' => '',
    //                     'value' => ['order_no', 'order_from', 'payment_type', 'order_status', 'recycle_type', 'create_time', 'pay_time', 'recycle_money', 'discount_money', 'order_money'],
    //                     'type' => 'checkbox',
    //                     'status' => 1,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'disabled' => false,
    //                     'remark' => '',
    //                     'list' => [
    //                         'order_no' => '订单编号',
    //                         'order_from' => '订单来源',
    //                         'order_status' => '订单状态',
    //                         'payment_type' => '支付方式',
    //                         'recycle_type' => '回收方式',
    //                         'create_time' => '下单时间',
    //                         'pay_time' => '完成时间',
    //                         'recycle_money' => '回收金额',
    //                         'discount_money' => '优惠金额',
    //                     ]
    //                 ],
    //                 [
    //                     'key' => 'order_money',
    //                     'label' => '实付金额',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'fontSize' => 'big',
    //                     'fontWeight' => 'bold',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'admin_remark',
    //                     'label' => '管理员备注',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'normal',
    //                     'fontWeight' => 'bold',
    //                     'remark' => '',
    //                 ],
    //             ]
    //         ],
    //         [
    //             'key' => 'goods_info',
    //             'title' => '回收物品信息',
    //             'list' => [
    //                 [
    //                     'key' => 'goods_name',
    //                     'label' => '物品名称',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => true,
    //                     'fontSize' => 'normal',
    //                     'fontWeight' => 'normal',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'goods_num',
    //                     'label' => '物品数量',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => true,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'goods_price',
    //                     'label' => '回收金额',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'normal',
    //                     'fontWeight' => 'normal',
    //                     'remark' => '',
    //                 ],
    //             ]
    //         ],
    //         [
    //             'key' => 'user_info',
    //             'title' => '用户/回收信息',
    //             'list' => [
    //                 [
    //                     'key' => 'member_basic_info',
    //                     'label' => '',
    //                     'value' => ['nickname', 'account_balance', 'account_point'],
    //                     'type' => 'checkbox',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                     'list' => [
    //                         'nickname' => '用户昵称',
    //                         'account_balance' => '账户余额',
    //                         'account_point' => '账户积分',
    //                     ]
    //                 ],
    //                 [
    //                     'key' => 'user_mobile',
    //                     'label' => '用户手机号',
    //                     'value' => 'yes',
    //                     'type' => 'select',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'normal',
    //                     'fontWeight' => 'normal',
    //                     'remark' => '手机号脱敏可以有效保护用户隐私',
    //                     'text' => '脱敏',
    //                     'list' => [
    //                         'yes' => '是',
    //                         'no' => '否'
    //                     ]
    //                 ],
    //                 [
    //                     'key' => 'contact_name',
    //                     'label' => '联系人姓名',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'big',
    //                     'fontWeight' => 'bold',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'contact_mobile',
    //                     'label' => '联系人手机号',
    //                     'value' => 'yes',
    //                     'type' => 'select',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'big',
    //                     'fontWeight' => 'bold',
    //                     'remark' => '手机号脱敏可以有效保护用户隐私',
    //                     'text' => '脱敏',
    //                     'list' => [
    //                         'yes' => '是',
    //                         'no' => '否'
    //                     ]
    //                 ],
    //                 [
    //                     'key' => 'recycle_address',
    //                     'label' => '回收地址',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'big',
    //                     'fontWeight' => 'bold',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'user_remark',
    //                     'label' => '用户备注',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'normal',
    //                     'fontWeight' => 'normal',
    //                     'remark' => '',
    //                 ],
    //             ]
    //         ],
    //         [
    //             'key' => 'bottom_info',
    //             'title' => '底部信息',
    //             'list' => [
    //                 [
    //                     'key' => 'qrcode',
    //                     'label' => '二维码',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'ticket_print_time',
    //                     'label' => '打印时间',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'bottom_remark',
    //                     'label' => '底部备注',
    //                     'value' => '感谢您的回收，共建绿色环保社会！',
    //                     'type' => 'input',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                 ],
    //             ]
    //         ],
    //     ],
    // ],
    // [
    //     'key' => 'recycleDevice',
    //     'title' => '设备标签', // 模板类型名称
    //     'sort' => 10001,
    //     //  触发打印时机，定义何时触发调用
    //     'trigger' => [
    //         'check_after' => '质检后',
    //         'manual' => '手动'
    //     ],
    //     // 无需筛选条件
    //     'condition' => [],
    //     'path' => 'preview-device-label', // 实时预览组件名称
    //     // 模板内容
    //     'template' => [
    //         [
    //             'key' => 'head_info',
    //             'title' => '设备标签头',
    //             'list' => [
    //                 [
    //                     'key' => 'label_title',
    //                     'label' => '标签标题',
    //                     'value' => '回收设备标签', // 存储的初始值
    //                     'placeholder' => '', 
    //                     'type' => 'input',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => 'big',
    //                     'fontWeight' => 'bold',
    //                     'remark' => '',
    //                 ],
    //             ]
    //         ],
    //         [
    //             'key' => 'device_info',
    //             'title' => '设备信息',
    //             'list' => [
    //                 [
    //                     'key' => 'device_items',
    //                     'label' => '',
    //                     'value' => ['order_no', 'category_name', 'brand', 'model', 'color', 'memory', 'capacity', 'imei', 'sn', 'check_result', 'final_price', 'check_time'],
    //                     'type' => 'checkbox',
    //                     'status' => 1,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'disabled' => false,
    //                     'remark' => '',
    //                     'list' => [
    //                         'order_no' => '订单编号',
    //                         'category_name' => '设备分类',
    //                         'brand' => '品牌',
    //                         'model' => '型号',
    //                         'color' => '颜色',
    //                         'memory' => '内存',
    //                         'capacity' => '容量',
    //                         'imei' => 'IMEI号',
    //                         'sn' => 'SN号',
    //                         'check_result' => '质检结果',
    //                         'final_price' => '回收价格',
    //                         'check_time' => '质检时间',
    //                     ]
    //                 ],
    //             ]
    //         ],
    //         [
    //             'key' => 'bottom_info',
    //             'title' => '底部信息',
    //             'list' => [
    //                 [
    //                     'key' => 'qrcode',
    //                     'label' => '二维码',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'print_time',
    //                     'label' => '打印时间',
    //                     'value' => '',
    //                     'type' => '',
    //                     'status' => 1, 
    //                     'disabled' => false,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                 ],
    //                 [
    //                     'key' => 'bottom_remark',
    //                     'label' => '底部备注',
    //                     'value' => '回收设备已质检，请妥善保管！',
    //                     'type' => 'input',
    //                     'status' => 1,
    //                     'disabled' => false,
    //                     'fontSize' => '',
    //                     'fontWeight' => '',
    //                     'remark' => '',
    //                 ],
    //             ]
    //         ],
    //     ],
    // ],
]; 