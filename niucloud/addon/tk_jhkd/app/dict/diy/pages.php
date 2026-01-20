<?php

return [
    'DIY_TK_JHKD_INDEX' => [
        'tk_jhkd_index_one' => [ // 页面标识
            "title" => "快递首页(模板一)", // 页面名称
            'cover' => 'addon/tk_jhkd/diy/cover/cover.png', // 页面封面图
            'preview' => 'addon/tk_jhkd/diy/cover/cover.png', // 页面预览图
            'desc' => '', // 页面描述
            'mode' => 'diy', // 页面模式：diy：自定义，fixed：固定
            // 页面数据源
            "data" => [
                "global" => [
                    "title" =>"快递5元寄",
                    "completeLayout" =>"style-1",
                    "completeAlign" =>"left",
                    "borderControl" =>true,
                    "pageStartBgColor" =>"",
                    "pageEndBgColor" =>"",
                    "pageGradientAngle" =>"to bottom",
                    "bgUrl" =>"",
                    "bgHeightScale" =>100,
                    "imgWidth" =>"",
                    "imgHeight" =>"",
                    "topStatusBar" => [
                        "control" =>true,
                        "isShow" =>true,
                        "bgColor" =>"#ffffff",
                        "rollBgColor" =>"#ffffff",
                        "style" =>"style-1",
                        "styleName" =>"风格1",
                        "textColor" =>"#333333",
                        "rollTextColor" =>"#333333",
                        "textAlign" =>"center",
                        "inputPlaceholder" =>"请输入搜索关键词",
                        "imgUrl" =>"",
                        "link" => [
                            "name" =>""
                        ]
                    ],
                    "bottomTabBar" => [
                        'control' => true,
                        'isShow' => true,
                        'designNav' => [
                            'title' => '',
                            'key' => ''
                        ]
                    ],
                    "copyright" => [
                        'control' => true,
                        'isShow' => false,
                        'textColor'=>'#ccc',
                    ],
                    "popWindow" => [
                        "imgUrl" =>"",
                        "imgWidth" =>"",
                        "imgHeight" =>"",
                        "count" =>"once",
                        "show" =>false,
                        "link" => [
                            "name" =>""
                        ]
                    ],
                    "template" => [
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>0
                        ],
                        "isHidden" =>false
                    ]
                ],
                "value" =>[
                    [
                        "path" =>"edit-image-ads",
                        "uses" =>0,
                        "id" =>"7km7wgz9ui80",
                        "componentName" =>"ImageAds",
                        "componentTitle" =>"图片广告",
                        "ignore" =>[],
                        "imageHeight" =>187,
                        "list" =>[
                            [
                                "link" => [
                                    "name" =>""
                                ],
                                "imageUrl" =>"addon/tk_jhkd/diy/index/banner.png",
                                "imgWidth" =>720,
                                "imgHeight" =>360,
                                "id" =>"7jezrytkabg0",
                                "width" =>375,
                                "height" =>187.5
                            ]
                        ],
                        "textColor" =>"#303133",
                        "pageBgColor" =>"",
                        "componentBgColor" =>"",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>0
                        ],
                        "pageStyle" =>"padding-top:2rpx;padding-bottom:0rpx;padding-right:0rpx;padding-left:0rpx;",
                        "componentBgUrl" =>""
                    ],
                    [
                        "path" =>"edit-graphic-nav",
                        "uses" =>0,
                        "id" =>"2xbdfz1tl3g0",
                        "componentName" =>"GraphicNav",
                        "componentTitle" =>"图文导航",
                        "ignore" =>[],
                        "layout" =>"horizontal",
                        "navTitle" =>"",
                        "subNavTitle" =>"",
                        "subNavTitleLink" => [
                            "name" =>""
                        ],
                        "subNavColor" =>"#999999",
                        "mode" =>"graphic",
                        "showStyle" =>"fixed",
                        "rowCount" =>4,
                        "pageCount" =>2,
                        "carousel" => [
                            "type" =>"circle",
                            "color" =>"#FFFFFF"
                        ],
                        "imageSize" =>38,
                        "aroundRadius" =>25,
                        "font" => [
                            "size" =>14,
                            "weight" =>"bold",
                            "color" =>"rgba(108, 108, 108, 1)"
                        ],
                        "list" =>[
                            [
                                "title" =>"寄快递",
                                "link" => [
                                    "parent" =>"TK_JHKD_LINK",
                                    "name" =>"TK_JHKD_ORDERKD",
                                    "title" =>"寄快递",
                                    "url" =>"/addon/tk_jhkd/pages/ordersubmit?type=kd",
                                    "action" =>""
                                ],
                                "imageUrl" =>"addon/tk_jhkd/diy/index/kuaidi.png",
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ],
                                "id" =>"6k02y3zk9vo0",
                                "imgWidth" =>200,
                                "imgHeight" =>200
                            ],
                            [
                                "id" =>"2720qf5lnf40",
                                "title" =>"寄重货",
                                "imageUrl" =>"addon/tk_jhkd/diy/index/kuaiyun.png",
                                "imgWidth" =>200,
                                "imgHeight" =>200,
                                "link" => [
                                    "parent" =>"TK_JHKD_LINK",
                                    "name" =>"TK_JHKD_ORDERKY",
                                    "title" =>"寄重货",
                                    "url" =>"/addon/tk_jhkd/pages/ordersubmit?type=ky",
                                    "action" =>""
                                ],
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ]
                            ],
                            [
                                "id" =>"5o9su1q1qs80",
                                "title" =>"违禁品",
                                "imageUrl" =>"addon/tk_jhkd/diy/index/weijinpin.png",
                                "imgWidth" =>200,
                                "imgHeight" =>200,
                                "link" => [
                                    "parent" =>"TK_JHKD_LINK",
                                    "name" =>"TK_JHKD_YESGOODS",
                                    "title" =>"违禁查询",
                                    "url" =>"/addon/tk_jhkd/pages/agreement?type=jhkdyesgoods",
                                    "action" =>""
                                ],
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ]
                            ],
                            [
                                "id" =>"4i1iloxfmfi0",
                                "title" =>"补差价",
                                "imageUrl" =>"addon/tk_jhkd/diy/index/buchajia.png",
                                "imgWidth" =>200,
                                "imgHeight" =>200,
                                "link" => [
                                    "parent" =>"TK_JHKD_LINK",
                                    "name" =>"TK_JHKD_ORDERADDLIST",
                                    "title" =>"补差价",
                                    "url" =>"/addon/tk_jhkd/pages/orderaddlist",
                                    "action" =>""
                                ],
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ]
                            ]
                        ],
                        "textColor" =>"#303133",
                        "pageBgColor" =>"",
                        "componentBgColor" =>"",
                        "topRounded" =>3,
                        "bottomRounded" =>10,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>-12,
                            "bottom" =>0,
                            "both" =>12
                        ],
                        "pageStyle" =>"padding-top:2rpx;padding-bottom:0rpx;padding-right:24rpx;padding-left:24rpx;",
                        "componentBgUrl" =>"",
                        "componentStartBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentEndBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentGradientAngle" =>"to bottom"
                    ],
                    [
                        "path" =>"edit-notice",
                        "uses" =>0,
                        "id" =>"1wbnbit16y4g",
                        "componentName" =>"Notice",
                        "componentTitle" =>"公告",
                        "ignore" =>[],
                        "noticeType" =>"text",
                        "imgType" =>"system",
                        "systemUrl" =>"style_2",
                        "imageUrl" =>"",
                        "showType" =>"popup",
                        "scrollWay" =>"horizontal",
                        "fontSize" =>14,
                        "fontWeight" =>"normal",
                        "noticeTitle" =>"公告",
                        "list" =>[
                            [
                                "text" =>"5元起全国寄快递",
                                "link" => [
                                    "name" =>""
                                ],
                                "id" =>"15chwibgbtq8"
                            ]
                        ],
                        "textColor" =>"#525252",
                        "pageBgColor" =>"",
                        "componentBgColor" =>"",
                        "topRounded" =>0,
                        "bottomRounded" =>12,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>-12,
                            "bottom" =>0,
                            "both" =>12
                        ],
                        "componentBgUrl" =>"",
                        "pageStyle" =>"padding-top:2rpx;padding-bottom:0rpx;padding-right:24rpx;padding-left:24rpx;",
                        "componentStartBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentEndBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentGradientAngle" =>"to bottom"
                    ],
                    [
                        "path" =>"edit-jhkd",
                        "uses" =>1,
                        "id" =>"l0m47pvyspo",
                        "componentName" =>"Jhkd",
                        "componentTitle" =>"下单组件",
                        "ignore" =>[],
                        "songbackground" =>"#4541c7",
                        "btbackground" =>"#4541c7",
                        "btfontcolor" =>"#ffffff",
                        "btname" =>"去下单",
                        "qsfontcolor" =>"#030307",
                        "slfontcolor" =>"#a9a9a9",
                        "padding" =>"12",
                        "textColor" =>"#303133",
                        "pageBgColor" =>"",
                        "componentBgColor" =>"",
                        "topRounded" =>10,
                        "bottomRounded" =>10,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>8,
                            "bottom" =>0,
                            "both" =>12
                        ],
                        "componentBgUrl" =>"",
                        "pageStyle" =>"padding-top:22rpx;padding-bottom:0rpx;padding-right:24rpx;padding-left:24rpx;",
                        "componentStartBgColor" =>"rgba(246, 252, 255, 0.79)",
                        "componentEndBgColor" =>"rgba(255, 255, 255, 0.91)",
                        "componentGradientAngle" =>"to bottom"
                    ],
                    [
                        "path" =>"edit-jhkdbrand",
                        "uses" =>0,
                        "id" =>"4rjz2zx1prc0",
                        "componentName" =>"Brand",
                        "componentTitle" =>"快递列表",
                        "ignore" =>[],
                        "iconsize" =>20,
                        "radiussize" =>100,
                        "padding" =>"12",
                        "mrsize" =>5,
                        "textColor" =>"#303133",
                        "pageBgColor" =>"",
                        "componentBgColor" =>"",
                        "topRounded" =>12,
                        "bottomRounded" =>12,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>8,
                            "bottom" =>0,
                            "both" =>12
                        ],
                        "pageStyle" =>"padding-top:24rpx;padding-bottom:0rpx;padding-right:24rpx;padding-left:24rpx;",
                        "componentBgUrl" =>"",
                        "componentStartBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentEndBgColor" =>"rgba(244, 244, 244, 1)",
                        "componentGradientAngle" =>"to bottom"
                    ],
                    [
                        "path" =>"edit-text",
                        "uses" =>0,
                        "id" =>"1damta0p9tz4",
                        "componentName" =>"Text",
                        "componentTitle" =>"标题",
                        "ignore" =>[],
                        "style" =>"style-2",
                        "styleName" =>"风格2",
                        "text" =>"新手指导",
                        "link" => [
                            "parent" =>"TK_JHKD_LINK",
                            "name" =>"TK_JHKD_HELP",
                            "title" =>"帮助中心",
                            "url" =>"/addon/tk_jhkd/pages/help",
                            "action" =>""
                        ],
                        "textColor" =>"#303133",
                        "fontSize" =>16,
                        "fontWeight" =>"normal",
                        "textAlign" =>"center",
                        "subTitle" => [
                            "text" =>"常见问题导航",
                            "color" =>"#999999",
                            "fontSize" =>14,
                            "control" =>true,
                            "fontWeight" =>"normal"
                        ],
                        "more" => [
                            "text" =>"查看更多",
                            "control" =>true,
                            "isShow" =>true,
                            "link" => [
                                "name" =>""
                            ],
                            "color" =>"#060606"
                        ],
                        "pageBgColor" =>"",
                        "componentBgColor" =>null,
                        "topRounded" =>12,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>8,
                            "bottom" =>0,
                            "both" =>12
                        ],
                        "pageStyle" =>"padding-top:26rpx;padding-bottom:0rpx;padding-right:24rpx;padding-left:24rpx;",
                        "componentBgUrl" =>"",
                        "componentStartBgColor" =>"rgba(238, 246, 255, 1)",
                        "componentEndBgColor" =>"rgba(195, 214, 255, 1)",
                        "componentGradientAngle" =>"to bottom"
                    ],
                    [
                        "path" =>"edit-rich-text",
                        "uses" =>0,
                        "id" =>"6dbj8npp5mw0",
                        "componentName" =>"RichText",
                        "componentTitle" =>"富文本",
                        "ignore" =>[],
                        "html" =>"<p style=\"text-align: center;\">温馨提示：</p><p><span style=\"color: #7F7F7F;\">请提前打包好物品，核对包裹重量并拍照，及时联系快递员上门取件，所有费用均通过线上支付，无需额外支付快递小哥费用~~~</span></p>",
                        "textColor" =>"#303133",
                        "pageBgColor" =>"",
                        "componentBgColor" =>"",
                        "topRounded" =>0,
                        "bottomRounded" =>12,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>12
                        ],
                        "componentBgUrl" =>"",
                        "componentStartBgColor" =>"rgba(171, 220, 255, 0.23)",
                        "componentGradientAngle" =>"to bottom",
                        "componentEndBgColor" =>"rgba(217, 199, 255, 0.26)",
                        "pageStyle" =>"padding-top:2rpx;padding-bottom:0rpx;padding-right:24rpx;padding-left:24rpx;"
                    ]
                ]
            ]
        ],
        'tk_jhkd_index_two' => [ // 页面标识
            "title" => "快递首页(模板二)", // 页面名称
            'cover' => 'addon/tk_jhkd/diy/cover/cover.png', // 页面封面图
            'preview' => 'addon/tk_jhkd/diy/cover/cover.png', // 页面预览图
            'desc' => '', // 页面描述
            'mode' => 'diy', // 页面模式：diy：自定义，fixed：固定
            // 页面数据源
            "data" => [
                "global" => [
                    "title" =>"快递5元寄",
                    "completeLayout" =>"style-1",
                    "completeAlign" =>"left",
                    "borderControl" =>true,
                    "pageStartBgColor" =>"",
                    "pageEndBgColor" =>"",
                    "pageGradientAngle" =>"to bottom",
                    "bgUrl" =>"",
                    "bgHeightScale" =>100,
                    "imgWidth" =>"",
                    "imgHeight" =>"",
                    "topStatusBar" => [
                        "control" =>true,
                        "isShow" =>true,
                        "bgColor" =>"#ffffff",
                        "rollBgColor" =>"#ffffff",
                        "style" =>"style-1",
                        "styleName" =>"风格1",
                        "textColor" =>"#333333",
                        "rollTextColor" =>"#333333",
                        "textAlign" =>"center",
                        "inputPlaceholder" =>"请输入搜索关键词",
                        "imgUrl" =>"",
                        "link" => [
                            "name" =>""
                        ]
                    ],
                    "bottomTabBar" => [
                        'control' => true,
                        'isShow' => true,
                        'designNav' => [
                            'title' => '',
                            'key' => ''
                        ]
                    ],
                    "copyright" => [
                        'control' => true,
                        'isShow' => false,
                        'textColor'=>'#ccc',
                    ],
                    "popWindow" => [
                        "imgUrl" =>"",
                        "imgWidth" =>"",
                        "imgHeight" =>"",
                        "count" =>"once",
                        "show" =>false,
                        "link" => [
                            "name" =>""
                        ]
                    ],
                    "template" => [
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>0
                        ],
                        "isHidden" =>false
                    ]
                ],
                "value" =>[
                    [
                        "path" =>"edit-image-ads",
                        "uses" =>0,
                        "id" =>"3pklscsbrve0",
                        "componentName" =>"ImageAds",
                        "componentTitle" =>"图片广告",
                        "ignore" =>[
                            "componentBgUrl"
                        ],
                        "imageHeight" =>134,
                        "isSameScreen" =>false,
                        "list" =>[
                            [
                                "link" => [
                                    "name" =>""
                                ],
                                "imageUrl" =>"addon/tk_jhkd/indext/banner.png",
                                "imgWidth" =>347,
                                "imgHeight" =>124,
                                "id" =>"5jxjee390c80",
                                "width" =>375,
                                "height" =>134.0057636887608
                            ]
                        ],
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>10,
                            "bottom" =>0,
                            "both" =>4
                        ]
                    ],
                    [
                        "path" =>"edit-rubik-cube",
                        "uses" =>0,
                        "id" =>"4pcwp2zz7cm0",
                        "componentName" =>"RubikCube",
                        "componentTitle" =>"魔方",
                        "ignore" =>[],
                        "mode" =>"row1-lt-of1-tp-of2-bm",
                        "imageGap" =>0,
                        "list" =>[
                            [
                                "imageUrl" =>"addon/tk_jhkd/indext/jkd.png",
                                "imgWidth" =>373,
                                "imgHeight" =>464,
                                "link" => [
                                    "parent" =>"TK_JHKD_DIY_LINK",
                                    "name" =>"TK_JHKD_ORDERSUBMIT",
                                    "title" =>"快递下单",
                                    "url" =>"/addon/tk_jhkd/pages/ordersubmit",
                                    "action" =>""
                                ]
                            ],
                            [
                                "imageUrl" =>"addon/tk_jhkd/indext/jdj.png",
                                "imgWidth" =>300,
                                "imgHeight" =>182,
                                "link" => [
                                    "parent" =>"TK_JHKD_DIY_LINK",
                                    "name" =>"TK_JHKD_ORDERKY",
                                    "title" =>"寄重货",
                                    "url" =>"/addon/tk_jhkd/pages/ordersubmit?type=ky",
                                    "action" =>""
                                ]
                            ],
                            [
                                "imageUrl" =>"addon/tk_jhkd/indext/bcj.png",
                                "imgWidth" =>115,
                                "imgHeight" =>149,
                                "link" => [
                                    "parent" =>"TK_JHKD_DIY_LINK",
                                    "name" =>"TK_JHKD_ORDERADDLIST",
                                    "title" =>"补差价",
                                    "url" =>"/addon/tk_jhkd/pages/orderaddlist",
                                    "action" =>""
                                ]
                            ],
                            [
                                "imageUrl" =>"addon/tk_jhkd/indext/lfl.png",
                                "imgWidth" =>115,
                                "imgHeight" =>149,
                                "link" => [
                                    "parent" =>"TK_JHKD_DIY_LINK",
                                    "name" =>"TK_JHKD_INDEX",
                                    "title" =>"快递首页",
                                    "url" =>"/addon/tk_jhkd/pages/index",
                                    "action" =>"decorate"
                                ]
                            ]
                        ],
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>null,
                        "pageEndBgColor" =>null,
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentEndBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>10,
                        "bottomRounded" =>10,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>10,
                            "bottom" =>0,
                            "both" =>10
                        ]
                    ],
                    [
                        "path" =>"edit-image-ads",
                        "uses" =>0,
                        "id" =>"1znkrwhni7a8",
                        "componentName" =>"ImageAds",
                        "componentTitle" =>"图片广告",
                        "ignore" =>[
                            "componentBgUrl"
                        ],
                        "imageHeight" =>29,
                        "isSameScreen" =>false,
                        "list" =>[
                            [
                                "link" => [
                                    "name" =>""
                                ],
                                "imageUrl" =>"https://saasr2.sotui.top/upload/attachment/image/100000/202501/16/173700287309a99529d5e405cad2b8121e61c6e932_tk_s3.png",
                                "imgWidth" =>591,
                                "imgHeight" =>59,
                                "id" =>"bebebp3520w",
                                "width" =>297,
                                "height" =>29.649746192893403
                            ]
                        ],
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>7,
                            "bottom" =>0,
                            "both" =>39
                        ]
                    ],
                    [
                        "path" =>"edit-gz",
                        "uses" =>1,
                        "id" =>"5q7m1gj19i40",
                        "componentName" =>"KdGz",
                        "componentTitle" =>"关注公众号",
                        "ignore" =>[],
                        "btbackground" =>"#0057FE",
                        "link" => [
                            "parent" =>"DIY_PAGE",
                            "name" =>"DIY_PAGE",
                            "title" =>"首页",
                            "url" =>"/app/pages/index/diy?id=945",
                            "action" =>"decorate"
                        ],
                        "text" =>"关注公众号,第一时间掌握物流动态",
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>0
                        ],
                        "min" =>"3"
                    ]
                ]
            ]
        ],
    ],
    'DIY_TK_JHKD_MEMBER_INDEX' => [
        'tk_jhkd_member_first' => [ // 页面标识
            "title" => "聚合快递个人中心", // 页面名称
            'cover' => '', // 页面封面图
            'preview' => '', // 页面预览图
            'desc' => '', // 页面描述
            'mode' => 'diy', // 页面模式：diy：自定义，fixed：固定
            // 页面数据源
            "data" => [
                "global" => [
                    "title" =>"个人中心",
                    "completeLayout" =>"style-1",
                    "completeAlign" =>"left",
                    "borderControl" =>true,
                    "pageStartBgColor" =>"",
                    "pageEndBgColor" =>"",
                    "pageGradientAngle" =>"to bottom",
                    "bgUrl" =>"",
                    "bgHeightScale" =>100,
                    "imgWidth" =>"",
                    "imgHeight" =>"",
                    "topStatusBar" => [
                        "control" =>true,
                        "isShow" =>true,
                        "bgColor" =>"#ffffff",
                        "rollBgColor" =>"#ffffff",
                        "style" =>"style-1",
                        "styleName" =>"风格1",
                        "textColor" =>"#333333",
                        "rollTextColor" =>"#333333",
                        "textAlign" =>"center",
                        "inputPlaceholder" =>"请输入搜索关键词",
                        "imgUrl" =>"",
                        "link" => [
                            "name" =>""
                        ]
                    ],
                    "bottomTabBar" => [
                        'control' => true,
                        'isShow' => true,
                        'designNav' => [
                            'title' => '',
                            'key' => ''
                        ]
                    ],
                    "copyright" => [
                        'control' => true,
                        'isShow' => false,
                        'textColor'=>'#ccc',
                    ],
                    "popWindow" => [
                        "imgUrl" =>"",
                        "imgWidth" =>"",
                        "imgHeight" =>"",
                        "count" =>"once",
                        "show" =>false,
                        "link" => [
                            "name" =>""
                        ]
                    ],
                    "template" => [
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>0
                        ],
                        "isHidden" =>false
                    ]
                ],
                "value" =>[
                    [
                        "path" =>"edit-member-info",
                        "id" =>"67qv49qgxp00",
                        "componentName" =>"MemberInfo",
                        "componentTitle" =>"会员信息",
                        "uses" =>0,
                        "ignore" =>[
                            "componentBgUrl"
                        ],
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>9,
                        "bottomRounded" =>9,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>12,
                            "bottom" =>6,
                            "both" =>16
                        ],
                        "style" =>"style-1",
                        "styleName" =>"风格1",
                        "textColor" =>"#000000",
                        "bgUrl" =>"",
                        "bgColorStart" =>"",
                        "bgColorEnd" =>"",
                        "pageStyle" =>"padding-top:24rpx;padding-bottom:12rpx;padding-right:32rpx;padding-left:32rpx;"
                    ],
                    [
                        "path" =>"edit-member-level",
                        "uses" =>1,
                        "id" =>"1w83pipkfpi8",
                        "componentName" =>"MemberLevel",
                        "componentTitle" =>"会员等级",
                        "ignore" =>[
                            "componentBgColor",
                            "componentBgUrl"
                        ],
                        "style" =>"style-3",
                        "styleName" =>"风格3",
                        "textColor" =>"#303133",
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>0,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>12
                        ],
                        "pageStyle" =>"padding-top:2rpx;padding-bottom:0rpx;padding-right:24rpx;padding-left:24rpx;"
                    ],
                    [
                        "path" =>"edit-graphic-nav",
                        "id" =>"62b7d7hl4ok",
                        "componentName" =>"GraphicNav",
                        "componentTitle" =>"图文导航",
                        "uses" =>0,
                        "layout" =>"horizontal",
                        "mode" =>"graphic",
                        "showStyle" =>"fixed",
                        "rowCount" =>4,
                        "pageCount" =>2,
                        "carousel" => [
                            "type" =>"circle",
                            "color" =>"#FFFFFF"
                        ],
                        "imageSize" =>25,
                        "aroundRadius" =>25,
                        "font" => [
                            "size" =>12,
                            "weight" =>"bold",
                            "color" =>"#303133"
                        ],
                        "pageStartBgColor" =>"",
                        "pageEndBgColor" =>"",
                        "pageGradientAngle" =>"to bottom",
                        "componentBgUrl" =>"",
                        "componentBgAlpha" =>2,
                        "componentStartBgColor" =>"rgba(255, 255, 255, 1)",
                        "componentEndBgColor" =>"",
                        "componentGradientAngle" =>"to bottom",
                        "topRounded" =>0,
                        "bottomRounded" =>9,
                        "elementBgColor" =>"",
                        "topElementRounded" =>0,
                        "bottomElementRounded" =>0,
                        "margin" => [
                            "top" =>0,
                            "bottom" =>6,
                            "both" =>16
                        ],
                        "ignore" =>[],
                        "list" =>[
                            [
                                "title" =>"个人资料",
                                "link" => [
                                    "parent" =>"MEMBER_LINK",
                                    "name" =>"MEMBER_PERSONAL",
                                    "title" =>"个人资料",
                                    "url" =>"/app/pages/member/personal"
                                ],
                                "imageUrl" =>"static/resource/images/diy/horz_m_personal.png",
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ],
                                "id" =>"xvlauaflc6o",
                                "imgWidth" =>100,
                                "imgHeight" =>100
                            ],
                            [
                                "title" =>"我的佣金",
                                "link" => [
                                    "parent" =>"MEMBER_LINK",
                                    "name" =>"MEMBER_COMMISSION",
                                    "title" =>"我的佣金",
                                    "url" =>"/app/pages/member/commission",
                                    "action" =>""
                                ],
                                "imageUrl" =>"static/resource/images/diy/horz_m_balance.png",
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ],
                                "id" =>"63bjscck5n40",
                                "imgWidth" =>100,
                                "imgHeight" =>100
                            ],
                            [
                                "title" =>"分销收入",
                                "link" => [
                                    "parent" =>"DIY_LINK",
                                    "name" =>"DIY_LINK",
                                    "title" =>"分销收入",
                                    "url" =>"/app/pages/member/detailed_account?type=commission",
                                    "action" =>""
                                ],
                                "imageUrl" =>"static/resource/images/diy/horz_m_point.png",
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ],
                                "id" =>"4qiczw54t8g0",
                                "imgWidth" =>100,
                                "imgHeight" =>100
                            ],
                            [
                                "title" =>"联系客服",
                                "link" => [
                                    "parent" =>"TK_CPS_COMMONLINK",
                                    "name" =>"TK_CPS_DIY",
                                    "title" =>"CPS首页",
                                    "url" =>"/addon/tk_cps/pages/diy",
                                    "action" =>"decorate"
                                ],
                                "imageUrl" =>"static/resource/images/diy/horz_m_service.png",
                                "label" => [
                                    "control" =>false,
                                    "text" =>"热门",
                                    "textColor" =>"#FFFFFF",
                                    "bgColorStart" =>"#F83287",
                                    "bgColorEnd" =>"#FE3423"
                                ],
                                "id" =>"2eqwfkdphpgk",
                                "imgWidth" =>100,
                                "imgHeight" =>100
                            ]
                        ],
                        "pageStyle" =>"padding-top:2rpx;padding-bottom:12rpx;padding-right:32rpx;padding-left:32rpx;"
                    ]
                ]
            ]
        ],
    ],
];