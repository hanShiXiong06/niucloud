<?php

return [
    'JHKD' => [
        'title' => '聚合快递',
        'list' => [
            'Jhkd' => [
                'title' => '下单组件',
                'icon' => 'tk_jhkd jhkd-kuaidi1',
                'path' => 'edit-jhkd', // 编辑组件属性名称
                'support_page' => [], // 支持页面
                'uses' => 1, // 最大添加数量
                'sort' => 10001,
                'value' => [
                    "songbackground"=>"#4541c7",
                    "btbackground"=>"#4541c7",
                    "btfontcolor"=>"#ffffff",
                    "btname"=>"去下单",
                    "qsfontcolor"=>"#030307",
                    "slfontcolor"=>"#a9a9a9",
                    "padding"=>"12",
                ]
            ],
            'Brand' => [
                'title' => '快递列表',
                'icon' => 'tk_jhkd jhkd-kuaidi2',
                'path' => 'edit-jhkdbrand', // 编辑组件属性名称
                'support_page' => [], // 支持页面
                'uses' => 0, // 最大添加数量
                'sort' => 10001,
                'value' => [
                    "iconsize"=>"24",
                    "radiussize"=>"100",
                    "padding"=>"12",
                    "mrsize"=>"4"
                ]
            ],
            'KdGz' => [
                'title' => '关注公众号',
                'icon' => 'nc-iconfont nc-icon-erweimaV6xx-1',
                'path' => 'edit-gz', // 编辑组件属性名称
                'support_page' => [], // 支持页面
                'uses' => 1, // 最大添加数量
                'sort' => 10001,
                'value' => [
                    "btbackground"=>"#0057FE",
                    "link" => [
                        "name" => "",
                        "title" => "",
                    ],
                    "text" => "关注公众号,第一时间掌握物流动态",
                    "min"=>3,
                ]
            ],
            'KdCoupon' => [
                'title' => '优惠券',
                'icon' => 'iconfont iconyouhuiquanpc',
                'path' => 'edit-kd-coupon',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10014,
                'value' => [
                    'style' => 'style-1',
                    "styleName" => "风格一",
                    'source' => 'all',
                    'num' => 6,
                    'couponIds' => [],
                    "btnText" => "立即领取",
                    'couponTitle' => '先领券 再寄件',
                    'couponSubTitle' => '领券下单 享超值优惠',
                    "titleColor" => "#ffffff",
                    "subTitleColor" => "#ffffff",
                    "couponItem" => [
                        "bgColor" => "#ffffff",
                        "textColor" => "#333333",
                        "subTextColor" => "#666666",
                        "moneyColor" => "#333333",
                        "aroundRadius" => 12
                    ]
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 2, // 组件背景图片的透明度，0~10
                    "componentStartBgColor" => '', // 组件背景颜色（开始）
                    "componentEndBgColor" => '', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to bottom', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 0, // 组件上圆角
                    "bottomRounded" => 0, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 0,// 元素上圆角
                    "bottomElementRounded" => 0, // 元素下圆角
                    "margin" => [
                        "top" => 10, // 上边距
                        "bottom" => 10, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
        ],
    ],

];

