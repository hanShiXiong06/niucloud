<?php

return [
    'RECYCLE_COMPONENT' => [
        'title' => '回收系统组件',
        'list' => [
            'MemberInfo' => [
                'title' => '会员信息',
                'icon' => 'iconfont iconhuiyuanqiandaopc',
                'path' => 'edit-member-info',
                'support_page' => [ 'DIY_RECYCLE_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10001,
                'value' => [
                    "style" => "style-1",
                    "styleName" => "风格1",
                    'bgUrl' => '',
                    'bgColorStart' => '',
                    'bgColorEnd' => ''
                ],
                'template' => [
                    "textColor" => "#303133",
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    "componentStartBgColor" => "",
                    "componentEndBgColor" => "",
                    "componentGradientAngle" => "to bottom",
                    "topRounded" => 0,
                    "bottomRounded" => 0,
                    "elementBgColor" => "",
                    "topElementRounded" => 0,
                    "bottomElementRounded" => 0,
                    "margin" => [
                        "top" => 0,
                        "bottom" => 0,
                        "both" => 0
                    ],
                ],
            ],
            'RecycleSpiderQuotationList' => [
                'title' => '爬虫报价单',
                'icon' => 'iconfont iconshangpinliebiaopc',
                'path' => 'edit-recycle-spider-quotation-list',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10006,
                'value' => [
                    'title' => '实时报价',
                    'subtitle' => '按数据源同步展示回收报价',
                    'showHeader' => true,
                    'actionText' => '查看',
                    'sourceId' => 0,
                    'limit' => 6,
                    'onlyHot' => false,
                    'showCategoryTabs' => true,
                    'categoryDefaultMode' => 'first',
                    'categoryTabDepth' => 0,
                    'flatGroupMode' => 'level2',
                    'showGroupCount' => true,
                    'showRefresh' => true,
                    'displayStyle' => 'list',
                    'navRowCount' => 4,
                    'navImageSize' => 40,
                    'navAroundRadius' => 20,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 0,
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'titleColor' => '#111827',
                    'subtitleColor' => '#6B7280',
                    'buttonColor' => '#2563EB',
                    'groupTitleColor' => '#111827',
                    'groupCountColor' => '#94A3B8',
                    'groupTitleSize' => 22,
                    'groupTitleWeight' => 500,
                    'groupTitleAlign' => 'left',
                    'itemTitleColor' => '#111827',
                    'itemTitleSize' => 28,
                    'itemMetaColor' => '#6B7280',
                    'itemImageRadius' => 20,
                    'margin' => [
                        'top' => 10,
                        'bottom' => 10,
                        'both' => 12
                    ]
                ]
            ],

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
                    'title' => '订单概况', // 标题
                    'viewAllText' => '全部', // 查看全部文本
                    'pendingSignText' => '待签收', // 待签收文本
                    'checkingText' => '质检中', // 质检中文本
                    'pendingConfirmText' => '待确认', // 待确认文本
                    'pendingPaymentText' => '待打款', // 待打款文本
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
