<?php

return [
    'DIY_AI_IMAGE_INDEX' => [
        'ai_image_index_one' => [ // 页面标识
            "title" => "AI设计首页模板", // 页面名称
            'cover' => 'addon/ai_image/cover.png', // 页面封面图
            'preview' => 'addon/ai_image/cover.png', // 页面预览图
            'desc' => '', // 页面描述
            'mode' => 'diy', // 页面模式：diy：自定义，fixed：固定
            // 页面数据源
            "data" => [
                "global" => [
                    "title" =>"页面",
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
                        "control" =>true,
                        "isShow" =>true,
                        "designNav" => [
                            "title" =>"",
                            "key" =>""
                        ]
                    ],
                    "copyright" => [
                        "control" =>true,
                        "isShow" =>true,
                        "textColor" => '#ccc'
                    ],
                    "popWindow" => [
                        "imgUrl" =>"",
                        "imgWidth" =>"",
                        "imgHeight" =>"",
                        "count" =>"once",
                        "show" =>0,
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
                        "id" =>"56aw5bve1hc0",
                        "componentName" =>"ImageAds",
                        "componentTitle" =>"图片广告",
                        "ignore" =>[
                            "componentBgUrl"
                        ],
                        "imageHeight" =>214,
                        "isSameScreen" =>false,
                        "list" =>[
                            [
                                "link" => [
                                    "name" =>""
                                ],
                                "imageUrl" =>"https://oss.sotui.top/upload/attachment/image/100000/202511/08/1762599045ff838afefba197e582d2035cabf2ae2d_tencent.jpg",
                                "imgWidth" =>1344,
                                "imgHeight" =>768,
                                "id" =>"4sfkybh3fw80",
                                "width" =>375,
                                "height" =>214.28571428571428
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
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>0
                        ],
                        "isHidden" =>false
                    ],
                    [
                        "path" =>"edit-notice",
                        "uses" =>0,
                        "id" =>"p4eqd7p994w",
                        "componentName" =>"Notice",
                        "componentTitle" =>"公告",
                        "ignore" =>[],
                        "noticeType" =>"img",
                        "imgType" =>"system",
                        "systemUrl" =>"style_2",
                        "imageUrl" =>"",
                        "showType" =>"popup",
                        "scrollWay" =>"upDown",
                        "fontSize" =>14,
                        "fontWeight" =>"normal",
                        "noticeTitle" =>"公告",
                        "list" =>[
                            [
                                "text" =>"AI设计上线了",
                                "link" => [
                                    "name" =>""
                                ],
                                "id" =>"5dgkmzwgrps0"
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
                            "top" =>0,
                            "bottom" =>0,
                            "both" =>0
                        ],
                        "isHidden" =>false
                    ],
                    [
                        "path" =>"edit-ai-image-model",
                        "uses" =>1,
                        "id" =>"4t8c08ykjpm0",
                        "componentName" =>"AiImageModel",
                        "componentTitle" =>"智能体",
                        "ignore" =>[],
                        "titlesize" =>"24",
                        "descsize" =>"24",
                        "titlecolor" =>"#131314",
                        "viewshow" =>"1",
                        "desccolor" =>"#131314",
                        "timecolor" =>"#131314",
                        "imagewidth" =>"180",
                        "imageheight" =>"140",
                        "bgcolor" =>"linear-gradient(0deg, rgba(178, 223, 220, 1) 0%, rgba(144, 202, 248, 0.08) 100%)",
                        "arrowcolor" =>"#131314",
                        "style" =>"style2",
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
                            "both" =>10
                        ],
                        "isHidden" =>false
                    ],
                    [
                        "path" =>"edit-ai-image-help",
                        "uses" =>1,
                        "id" =>"2dzto1qpid8g",
                        "componentName" =>"AiImageHelp",
                        "componentTitle" =>"帮助中心",
                        "ignore" =>[],
                        "titlesize" =>16,
                        "descsize" =>12,
                        "titlecolor" =>"#131314",
                        "viewshow" =>"1",
                        "desccolor" =>"#131314",
                        "timecolor" =>"#131314",
                        "imagewidth" =>108,
                        "imageheight" =>100,
                        "bgcolor" =>"linear-gradient(0deg, rgba(255, 255, 255, 1) 0%, rgba(0, 0, 0, 0.04) 100%)",
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
                            "both" =>13
                        ],
                        "isHidden" =>false
                    ]
                ]
            ]
        ],
    ],
    'DIY_AI_IMAGE_MEMBER_INDEX' => [
        'ai_image_member_first' => [ // 页面标识
            "title" => "AI设计个人中心", // 页面名称
            'cover' => '', // 页面封面图
            'preview' => '', // 页面预览图
            'desc' => '', // 页面描述
            'mode' => 'diy', // 页面模式：diy：自定义，fixed：固定
            // 页面数据源
            "data" => [
                "global" => [
                    "title" =>"个人中心",
                    "pageStartBgColor" =>"#F8F8F8",
                    "pageEndBgColor" =>"",
                    "pageGradientAngle" =>"to bottom",
                    "bgUrl" =>"",
                    "bgHeightScale" =>0,
                    "imgWidth" =>"",
                    "imgHeight" =>"",
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
                            "both" =>12
                        ]
                    ],
                    "topStatusBar" => [
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
                        ],
                        "control" =>true
                    ],
                    "popWindow" => [
                        "imgUrl" =>"",
                        "imgWidth" =>"",
                        "imgHeight" =>"",
                        "count" =>-1,
                        "show" =>0,
                        "link" => [
                            "name" =>""
                        ]
                    ],
                    "bottomTabBar" => [
                        "control" =>true,
                        "isShow" =>true,
                        "designNav" => [
                            "title" =>"",
                            "key" =>""
                        ]
                    ],
                    "copyright" => [
                        "control" =>true,
                        "isShow" =>false,
                        "textColor" => '#ccc'
                    ],
                    "bottomTabBarSwitch" =>true
                ],
                "value" =>[
                    [
                        "path" =>"edit-ai-image-member-info",
                        "uses" =>1,
                        "id" =>"3eobp17son40",
                        "componentName" =>"AiImageMemberInfo",
                        "componentTitle" =>"会员信息",
                        "ignore" =>[],
                        "style" =>"style-1",
                        "styleName" =>"风格1",
                        "bgUrl" =>"",
                        "bgColorStart" =>"#64b5f6",
                        "bgColorEnd" =>"#64b5f6",
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
                        ]
                    ],
                    [
                        "path" =>"edit-ai-image-member",
                        "uses" =>1,
                        "id" =>"39tqxnnr2x60",
                        "componentName" =>"AiImageMember",
                        "componentTitle" =>"会员余额",
                        "ignore" =>[],
                        "background" =>"#64b5f6",
                        "vipbackground" =>"rgba(32, 151, 243, 1)",
                        "buttoncolor" =>"#0d7ff2",
                        "textcolor" =>"#ffffff",
                        "radiussize" =>"10",
                        "padding" =>"10",
                        "descsize" =>"享受10+数字权益，大额赠送",
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
                        ]
                    ]
                ]
            ]
        ],
    ],
];