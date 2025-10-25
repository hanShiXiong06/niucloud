<?php

return [
    'RECYCLE_COMPONENT' => [
        'title' => '回收系统组件',
        'list' => [
           
           
            'RecycleCategory' => [
                'title' => '回收分类导航',
                'icon' => 'iconfont iconfenlei',
                'path' => 'edit-recycle-category',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10010,
                
                // 组件属性
                'template' => [
                    'layout' => 'horizontal', // 布局方式，水平horizontal 
                    'rowCount' => 5, // 一行显示的数量
                    'imageSize' => 35, // 图片大小
                    'aroundRadius' => 5, // 图片圆角
                    'font' => [
                        'size' => 14, // 字体大小
                        'weight' => 400, // 字体粗细
                        'color' => '#333333' // 字体颜色
                    ],
                    'componentStartBgColor' => '#FFFFFF', // 组件背景颜色
                    'componentEndBgColor' => '#FFFFFF', // 组件背景颜色
                    "componentGradientAngle" => 'to bottom', // 渐变角度
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 0, // 组件背景图片的透明度
                    "topRounded" => 8, // 组件上圆角
                    "bottomRounded" => 8, // 组件下圆角
                    "margin" => [
                        "top" => 10, // 上边距
                        "bottom" => 10, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            
            'RecycleOrderOverview' => [
                'title' => '订单数据概况',
                'icon' => 'iconfont iconfenlei',
                'path' => 'edit-recycle-order-overview',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10009,
                
                // 组件属性
                'template' => [
                    'title' => '设备状态概况', // 标题
                    'viewAllText' => '全部', // 查看全部文本
                    'pendingReceiptText' => '待质检', // 待质检文本
                    'processingText' => '处理中', // 处理中文本
                    'shippedText' => '已质检', // 已质检文本
                    'pendingConfirmText' => '待确认', // 待确认文本
                    'titleColor' => '#333333', // 标题颜色
                    'viewAllColor' => '#999999', // 查看全部颜色
                    'numberColor' => '#FF6B00', // 数字颜色
                    'labelColor' => '#666666', // 标签颜色
                    'componentStartBgColor' => '#FFFFFF', // 组件背景颜色
                    'componentEndBgColor' => '#FFFFFF', // 组件背景颜色
                    "componentGradientAngle" => 'to bottom', // 渐变角度
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 0, // 组件背景图片的透明度
                    "topRounded" => 8, // 组件上圆角
                    "bottomRounded" => 8, // 组件下圆角
                    "margin" => [
                        "top" => 10, // 上边距
                        "bottom" => 10, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'RecycleSendButton' => [
                'title' => '去发货',
                'icon' => 'iconfont iconfenlei',
                'path' => 'edit-recycle-send-button',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10008,
                'template' => [
                    'title' => '去发货',
                    'buttonText' => '去发货',
                    'buttonColor' => '#FFFFFF',
                    'buttonBgColor' => '#FF6B00',
                    'buttonBgUrl' => '',
                    'buttonBgAlpha' => 0,
                    'buttonBgSize' => '100% 100%',
                    'buttonBgRadius' => 8,
                    'buttonBgMargin' => [
                        "top" => 10, // 上边距
                        "bottom" => 10, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ]
        ]
    ],

];
