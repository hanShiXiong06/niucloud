<?php

return [
    'DIY_RECYCLE_INDEX' => [
        'recycle_index' => [ // 页面标识
            "title" => "回收系统首页", // 页面名称
            'cover' => '', // 页面封面图
            'preview' => '', // 页面预览图
            'desc' => '', // 页面描述
            'mode' => 'diy', // 页面模式：diy：自定义，fixed：固定
            // 页面数据源
            "data" => [
                "global" => [
                    "title" => "首页",
                    "pageStartBgColor" => "rgba(246, 246, 246, 1)",
                    "pageEndBgColor" => "",
                    "pageGradientAngle" => "to bottom",
                    "bgUrl" => "",
                    "bgHeightScale" => 100,
                    "imgWidth" => "",
                    "imgHeight" => "",
                    "topStatusBar" => [
                        "isShow" => false,
                        "bgColor" => "#ffffff",
                        "rollBgColor" => "#ffffff",
                        "style" => "style-1",
                        "styleName" => "风格1",
                        "textColor" => "#333333",
                        "rollTextColor" => "#333333",
                        "textAlign" => "center",
                        "inputPlaceholder" => "请输入搜索关键词",
                        "imgUrl" => "",
                        "link" => [
                            "name" => ""
                        ]
                    ],
                    "bottomTabBarSwitch" => true,
                    "popWindow" => [
                        "imgUrl" => "",
                        "imgWidth" => "",
                        "imgHeight" => "",
                        "count" => -1,
                        "show" => 0,
                        "link" => [
                            "name" => ""
                        ]
                    ],
                    "template" => [
                        "textColor" => "#303133",
                        "pageStartBgColor" => "",
                        "pageEndBgColor" => "",
                        "pageGradientAngle" => "to bottom",
                        "componentBgUrl" => "",
                        "componentBgAlpha" => 2,
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
                        ]
                    ]
                ],
                "value" => [
                    [
                        "path" => "edit-image-ads",
                        "uses" => 0,
                        "id" => "5pxo11dusw40",
                        "componentName" => "ImageAds",
                        "componentTitle" => "图片广告",
                        "ignore" => [
                            "componentBgUrl"
                        ],
                        "imageHeight" => 158,
                        "isSameScreen" => false,
                        "list" => [
                            [
                                "link" => [
                                    "name" => ""
                                ],
                                "imageUrl" => "static/resource/images/attachment/banner4.png",
                                "imgWidth" => 710,
                                "imgHeight" => 300,
                                "id" => "45iqylhi6qw0",
                                "width" => 375,
                                "height" => 158.4507042253521
                            ]
                        ],
                        "textColor" => "#303133",
                        "pageStartBgColor" => "",
                        "pageEndBgColor" => "",
                        "pageGradientAngle" => "to bottom",
                        "componentBgUrl" => "",
                        "componentBgAlpha" => 2,
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
                            "both" => 10
                        ],
                        "isHidden" => false,
                        "pageStyle" => "padding-top:2rpx;padding-bottom:0rpx;padding-right:20rpx;padding-left:20rpx;"
                    ]
                ]
            ]
        ]
    ],
    'DIY_RECYCLE_MEMBER_INDEX' => [
        'recycle_member_index' => [ // 页面标识
            "title" => "回收个人中心", // 页面名称
            'cover' => '', // 页面封面图
            'preview' => '', // 页面预览图
            'desc' => '', // 页面描述
            'mode' => 'diy', // 页面模式：diy：自定义，fixed：固定
            // 页面数据源
            "data" => [
                "global" => [
                    "title" => "个人中心",
                    'pageStartBgColor' => 'rgba(247, 247, 247, 1)',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    "bgUrl" => "",
                    'bgHeightScale' => 0,
                    "imgWidth" => "",
                    "imgHeight" => "",
                    "topStatusBar" => [
                        'isShow' => true,
                        'bgColor' => "#ffffff",
                        'rollBgColor' => "#ffffff",
                        'style' => 'style-1',
                        'styleName' => '风格1',
                        'textColor' => "#333333",
                        'rollTextColor' => "#333333",
                        'textAlign' => 'center',
                        'inputPlaceholder' => '请输入搜索关键词',
                        'imgUrl' => '',
                        'link' => [
                            'name' => ""
                        ]
                    ],
                    "bottomTabBarSwitch" => true,
                    "popWindow" => [
                        "imgUrl" => "",
                        "imgWidth" => "",
                        "imgHeight" => "",
                        "count" => -1,
                        "show" => 0,
                        "link" => [
                            "name" => ""
                        ]
                    ],
                    "template" => [
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
                        ]
                    ]
                ],
                "value" => []
            ]
        ]
    ]
];
