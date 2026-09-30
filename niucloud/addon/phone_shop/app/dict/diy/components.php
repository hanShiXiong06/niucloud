<?php

return [
    'PHONE_SHOP_COMPONENT' => [
        'title' => get_lang('dict_diy.shop_component_type_basic'),
        'list' => [
            'PhoneGoodsList' => [
                'title' => '商品列表',
                'icon' => 'iconfont iconshangpinliebiaopc',
                'path' => 'edit-phone-goods-list',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10011,
                'value' => [
                    'style' => 'style-1',
                    'source' => 'all',
                    'num' => 10,
                    'goods_category' => '',
                    "goods_category_name" => "请选择",
                    'goods_ids' => [],
                    "sortWay" => "default", // 排序方式，default：综合，sale_num：销量，price：价格
                    "goodsNameStyle" => [
                        "color" => "#303133",
                        "control" => true,
                        "fontWeight" => 'normal',
                        "isShow" => true
                    ],
                    "mode" => "aspectFill",
                    "priceStyle" => [
                        "color" => "#FF4142",
                        "control" => true,
                        "isShow" => true
                    ],
                    "saleStyle" => [
                        "color" => "#999999",
                        "control" => true,
                        "isShow" => true
                    ],
                    "labelStyle" => [
                        "control" => true,
                        "isShow" => true
                    ],
                    "btnStyle" => [
                        "fontWeight" => false,
                        "padding" => 0,
                        "aroundRadius" => 25,
                        "cartEvent" => "detail",
                        "text" => "购买",
                        "textColor" => "#FFFFFF",
                        "startBgColor" => "#FF4142",
                        "endBgColor" => "#FF4142",
                        "style" => "button",
                        "control" => true
                    ],
                    "imgElementRounded" => 10,// 图片圆角
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
                    "topElementRounded" => 10,// 元素上圆角
                    "bottomElementRounded" => 10, // 元素下圆角
                    "margin" => [
                        "top" => 0, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneShopSearch' => [
                'title' => '搜索',
                'icon' => 'iconfont iconsousuopc-1',
                'path' => 'edit-phone-shop-search',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10012,
                'value' => [
                    "searchStyle" => "style-1",
                    "searchLink" => [
                        "name" => ""
                    ],
                    "text" => "请输入搜索关键词",
                    "iconType" => "img",
                    "icon" => "",
                    "imageUrl" => ""
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
            'PhoneManyGoodsList' => [
                'title' => '多商品组',
                'icon' => 'iconfont iconduoshangpinzupc',
                'path' => 'edit-phone-many-goods-list',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10013,
                'value' => [
                    'style' => 'style-2',
                    'num' => 6,
                    "mode" => "aspectFill",
                    "sortWay" => "default", // 排序方式，default：综合，sale_num：销量，price：价格
                    "headStyle" => "style-1",
                    "aroundRadius" => 25,
                    "source" => "custom",
                    "goods_category" => '',
                    "goods_category_name" => '请选择',
                    "goodsNameStyle" => [
                        "color" => "#303133",
                        "control" => true,
                        "fontWeight" => 'normal',
                        "isShow" => true
                    ],
                    "priceStyle" => [
                        "color" => "#FF4142",
                        "control" => true,
                        "isShow" => true
                    ],
                    "saleStyle" => [
                        "color" => "#999999",
                        "control" => true,
                        "isShow" => true
                    ],
                    "labelStyle" => [
                        "control" => true,
                        "isShow" => true
                    ],
                    "btnStyle" => [
                        "fontWeight" => false,
                        "padding" => 0,
                        "aroundRadius" => 25,
                        "cartEvent" => "detail",
                        "text" => "购买",
                        "textColor" => "#FFFFFF",
                        "startBgColor" => "#FF4142",
                        "endBgColor" => "#FF4142",
                        "style" => "button",
                        "control" => true
                    ],
                    "list" => [
                        [
                            "title" => "推荐",
                            "desc" => "猜你喜欢",
                            "source" => "all",
                            "goods_category" => '',
                            "goods_category_name" => '请选择',
                            "goods_ids" => [],
                            "imageUrl" => ''
                        ]
                    ],
                    "imgElementRounded" => 0 // 图片圆角
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
                        "top" => 0, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneGoodsCoupon' => [
                'title' => '优惠券',
                'icon' => 'iconfont iconyouhuiquanpc',
                'path' => 'edit-phone-goods-coupon',
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
                    'couponTitle' => '先领券 再购物',
                    'couponSubTitle' => '领券下单 享购物优惠',
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
                    "topRounded" => 12, // 组件上圆角
                    "bottomRounded" => 12, // 组件下圆角
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
            'PhoneShopMemberInfo' => [
                'title' => '会员信息',
                'icon' => 'iconfont iconhuiyuanqiandaopc',
                'path' => 'edit-phone-shop-member-info',
                'support_page' => [ 'DIY_PHONE_SHOP_MEMBER_INDEX', 'DIY_SHOP_GIFTCARD_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10015,
                'value' => [
                    "style" => "style-1",
                    "styleName" => "风格1",
                    'bgUrl' => '',
                    'isShowAccount' => true,
                    'uidTextColor' => '#666666',
                    'accountTextColor' => '#666666'
                ],
            ],
            'PhoneShopMemberBarcode' => [
                'title' => '会员身份码',
                'icon' => 'iconfont iconerweima',
                'path' => 'edit-phone-shop-member-barcode',
                // 商城首页和个人中心都可投放；之前仅限个人中心，装修首页时组件面板不会展示。
                'support_page' => [ 'DIY_PHONE_SHOP_INDEX', 'DIY_PHONE_SHOP_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10016,
                'value' => [
                    'title' => '我的身份码',
                    'desc' => '出入库时出示，业务员扫码快速识别',
                ],
                'template' => [
                    'componentStartBgColor' => '#ffffff',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 12,
                    'bottomRounded' => 12,
                    'margin' => [ 'top' => 10, 'bottom' => 10, 'both' => 10 ],
                ],
            ],
            'PhoneShopOrderInfo' => [
                'title' => '订单中心',
                'icon' => 'iconfont icondingdanzhongxinPC-1',
                'path' => 'edit-phone-shop-order-info',
                'support_page' => [ 'DIY_PHONE_SHOP_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10017,
                'value' => [
                    "textColor" => "#303133",
                    "fontSize" => 16,
                    "fontWeight" => "normal",
                    "text" => "订单中心",
                    "more" => [
                        "text" => "全部订单",
                        "color" => "#999999",
                    ],
                    "item" => [
                        "fontSize" => 12,
                        "fontWeight" => "normal",
                        "color" => "#303133"
                    ],
                ]
            ],
            'PhoneShopExchangeInfo' => [
                'title' => '积分兑换',
                'icon' => 'iconfont iconjifenpc',
                'path' => 'edit-phone-shop-exchange-info',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10017,
                'value' => [
                    'bgUrl' => 'addon/phone_shop/diy/point/point_index_bg.jpg',
                ],
            ],
            'PhoneShopExchangeGoods' => [
                'title' => '积分商品',
                'icon' => 'iconfont iconjifenshangpinpc',
                'path' => 'edit-phone-shop-exchange-goods',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10018,
                'value' => [
                    'style' => 'style-2',
                    "mode" => "aspectFill",
                    'source' => 'all',
                    'num' => 10,
                    'goods_category' => '',
                    "goods_category_name" => "请选择",
                    'goods_ids' => [],
                    "sortWay" => "total_order_num", // 排序方式，total_order_num：综合，total_exchange_num：销量，price：价格
                    "goodsNameStyle" => [
                        "color" => "#333",
                        "control" => true,
                        "fontWeight" => 'normal'
                    ],
                    "priceStyle" => [
                        "mainColor" => "#FF4142",
                        "mainControl" => true,
                        "lineColor" => "#999CA7",
                        "lineControl" => true
                    ],
                    "saleStyle" => [
                        "color" => "#999999",
                        "control" => true
                    ],
                    "imgElementRounded" => 10,// 图片圆角
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
                        "top" => 0, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 10 // 左右边距
                    ],
                ]
            ],
            'PhoneShopGoodsRecommend' => [
                'title' => '商品推荐',
                'icon' => 'iconfont icona-shangpintuijianpc30',
                'path' => 'edit-phone-shop-goods-recommend',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10019,
                'value' => [
                    "priceStyle" => [
                        "mainColor" => "#333333",
                    ],
                    "mode" => "aspectFill",
                    'source' => 'all',
                    "goods_ids" => [],
                    'list' => [
                        [
                            "title" => [
                                "text" => "今日推荐",
                                "textColor" => "#303133"
                            ],
                            "moreTitle" => [
                                "text" => "精选",
                                "textColor" => "#FFFFFF",
                                "startColor" => "#FF7234",
                                "endColor" => "#FF213F",
                            ],
                            "listFrame" => [
                                "startColor" => "#FFE5E5",
                                "endColor" => "#FFF5F0",
                            ],
                            "button" => [
                                "text" => "首单",
                                "textColor" => "#FFFFFF",
                                "color" => "#FF1128",
                            ]
                        ],
                        [
                            "title" => [
                                "text" => "品质好物",
                                "textColor" => "#303133"
                            ],
                            "moreTitle" => [
                                "text" => "精选",
                                "textColor" => "#FFFFFF",
                                "startColor" => "#F2C719",
                                "endColor" => "#FBBA08",
                            ],
                            "listFrame" => [
                                "startColor" => "#FFEFBA",
                                "endColor" => "#FFF5D7",
                            ],
                            "button" => [
                                "text" => "首单",
                                "textColor" => "#FFFFFF",
                                "color" => "#FF1128",
                            ]
                        ],
                        [
                            "title" => [
                                "text" => "热销爆款",
                                "textColor" => "#303133"
                            ],
                            "moreTitle" => [
                                "text" => "精选",
                                "textColor" => "#FFFFFF",
                                "startColor" => "#FFA629",
                                "endColor" => "#FF8E1E",
                            ],
                            "listFrame" => [
                                "startColor" => "#FFE4D9",
                                "endColor" => "#FFFBF9",
                            ],
                            "button" => [
                                "text" => "首单",
                                "textColor" => "#FFFFFF",
                                "color" => "#FF1128",
                            ]
                        ]
                    ],
                    "imgElementRounded" => 10,// 图片圆角
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
                    "topElementRounded" => 10,// 元素上圆角
                    "bottomElementRounded" => 10, // 元素下圆角
                    "margin" => [
                        "top" => 10, // 上边距
                        "bottom" => 10, // 下边距
                        "both" => 10 // 左右边距
                    ],
                ]
            ],
            'PhoneSingleRecommend' => [
                'title' => '精选推荐',
                'icon' => 'iconfont icona-jingxuantuijianpc30-12',
                'path' => 'edit-phone-single-recommend',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10019,
                'value' => [
                    "mode" => "aspectFill",
                    "titleStyle" => [
                        'title' => '风格1',
                        'value' => 'style-1'
                    ],
                    'textImg' => 'addon/phone_shop/diy/index/style3/single_recommend_text1.png',
                    "textLink" => [
                        "name" => ""
                    ],
                    "titleColor" => "#999999",
                    "subTitle" => [
                        "text" => "更多",
                        "textColor" => "#999999",
                        "link" => [
                            "name" => ""
                        ]
                    ],
                    'source' => 'all',
                    'goods_ids' => [],
                    "imageHeight" => 250,
                    "list" => [
                        [
                            "link" => [
                                "name" => ""
                            ],
                            "imageUrl" => "addon/phone_shop/diy/index/style3/single_recommend_banner1.jpg",
                            "imgWidth" => 345,
                            "imgHeight" => 495
                        ],
                        [
                            "link" => [
                                "name" => ""
                            ],
                            "imageUrl" => "addon/phone_shop/diy/index/style3/single_recommend_banner2.jpg",
                            "imgWidth" => 345,
                            "imgHeight" => 495
                        ]
                    ],
                    "goodsNameStyle" => [
                        "color" => "#303133",
                        "control" => true,
                        "fontWeight" => 'normal'
                    ],
                    "priceStyle" => [
                        "mainColor" => "#FF4142",
                        "mainControl" => true,
                        "lineColor" => "#999CA7",
                        "lineControl" => true
                    ],
                    "saleStyle" => [
                        "color" => "#FF0000",
                        "control" => true
                    ],
                    'topCarouselRounded' => 12,
                    'bottomCarouselRounded' => 12,
                    'indicatorColor' => 'rgba(255, 255, 255, 0.6)',
                    'indicatorActiveColor' => '#ffffff',
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
            'PhoneShopNewcomer' => [
                'title' => '新人专享',
                'icon' => 'iconfont icona-xinrenzhuanxiangpc30',
                'path' => 'edit-phone-shop-newcomer',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10020,
                'value' => [
                    "mode" => "aspectFill",
                    "style" => [
                        'title' => '风格1',
                        'value' => 'style-1'
                    ],
                    'textImg' => 'addon/phone_shop/diy/newcomer/style_title_01.png',
                    "subTitle" => [
                        "text" => "查看更多",
                        "textColor" => "#FFFFFF",
                        "startColor" => "#FB792F",
                        "endColor" => "#F91700",
                        "link" => [
                            "name" => ""
                        ],
                    ],
                    "countDown" => [
                        "numberColor" => "rgba(255, 0, 0, 1)",
                        "numberBg" => [
                            "startColor" => "rgba(255, 255, 255, 1)",
                            "endColor" => ""
                        ],
                        "otherColor" => "rgba(255, 255, 255, 1)"
                    ],
                    'source' => 'all',
                    'num' => 10,
                    'goods_category' => '',
                    "goods_category_name" => "请选择",
                    'goods_ids' => [],
                    "imgElementRounded" => 10 // 图片圆角
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    "componentStartBgColor" => '#ff6D1A', // 组件背景颜色（开始）
                    "componentEndBgColor" => 'rgba(255, 70, 56, 1)', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to right', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 12, // 组件上圆角
                    "bottomRounded" => 12, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 10,// 元素上圆角
                    "bottomElementRounded" => 10, // 元素下圆角
                    "margin" => [
                        "top" => 10, // 上边距
                        "bottom" => 10, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsRanking' => [
                'title' => '排行榜',
                'icon' => 'iconfont icona-paihangbangpc30',
                'path' => 'edit-phone-shop-goods-ranking',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10021,
                'value' => [
                    "mode" => "aspectFill",
                    "list" => [
                        [
                            'bgUrl' => 'addon/phone_shop/rank/rank_bg_01.jpg', // 榜单背景图
                            'text' => '热销排行榜',
                            "textColor" => "#FFFFFF",
                            "imgUrl" => "addon/phone_shop/rank/rank_trophy.png", // 图标
                            'subTitle' => [
                                'text' => '查看更多',
                                'textColor' => '#FFFFFF',
                                'link' => [
                                    'name' => 'PHONE_SHOP_GOODS_RANK',
                                    "parent" => "PHONE_SHOP_LINK",
                                    'title' => '商品排行榜',
                                    'url' => '/addon/phone_shop/pages/goods/rank',
                                    'action' => '',
                                ]
                            ],
                            'listFrame' => [
                                'startColor' => '#FEA715',
                                'endColor' => '#FE1E00',
                            ],
                            'source' => 'default',
                            'rankIds' => []
                        ],
                        [
                            'bgUrl' => 'addon/phone_shop/rank/rank_bg_02.jpg', // 榜单背景图
                            'text' => '人气排行榜',
                            "textColor" => "#FFFFFF",
                            "imgUrl" => "addon/phone_shop/rank/rank_top.png", // 图标
                            'subTitle' => [
                                'text' => '查看更多',
                                'textColor' => '#FFFFFF',
                                'link' => [
                                    'name' => 'PHONE_SHOP_GOODS_RANK',
                                    "parent" => "PHONE_SHOP_LINK",
                                    'title' => '商品排行榜',
                                    'url' => '/addon/phone_shop/pages/goods/rank',
                                    'action' => '',
                                ]
                            ],
                            'listFrame' => [
                                'startColor' => '#FEA715',
                                'endColor' => '#FE1E00',
                            ],
                            'source' => 'default',
                            'rankIds' => []
                        ]
                    ],
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
                    "topElementRounded" => 10,// 元素上圆角
                    "bottomElementRounded" => 10, // 元素下圆角
                    "margin" => [
                        "top" => 10, // 上边距
                        "bottom" => 10, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsHot' => [
                'title' => '爆款商品',
                'icon' => 'iconfont iconjifenshangpinpc',
                'path' => 'edit-phone-shop-goods-hot',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10022,
                'value' => [
                    "mode" => "aspectFill",
                    'titleStyle' => [
                        'text' => '大牌直降',
                        'textColor' => '#FFFFFF',
                        "fontWeight" => 'bold',
                        "fontSize" => 18,
                        'imgUrl' => '',
                        'way' => 'text', // 展示方式，text：文本，img：图片
                        "link" => [
                            "name" => ""
                        ]
                    ],
                    'subTitleStyle' => [
                        'text' => '7月10日-29日',
                        'textColor' => '#ffffff',
                        'bgColor' => '#ff737a',
                        "rounded" => 6,
                        "fontSize" => 12
                    ],
                    'btnStyle' => [
                        "text" => '立即抢购',
                        'textColor' => '#ee695c',
                        "startBgColor" => "",
                        "endBgColor" => "",
                        "gradientType" => "radial",
                        "fontSize" => 12,
                        "link" => [
                            "name" => ""
                        ]
                    ],
                    'goodsStyle' => [
                        'bgColor' => '#ffffff',
                        'labelText' => '大牌直降',
                        'labelSize' => 10,
                        'labelColor' => '#ffffff',
                        'labelBgColor' => '#FF0000',
                        "rounded" => 8,
                        "imgRounded" => 7,
                        'source' => 'all', // 商品来源方式
                        'num' => 10,
                        "goodsIds" => []
                    ]
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '#FF0000', // 底部背景颜色（开始）
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
                        "top" => 12, // 上边距
                        "bottom" => 12, // 下边距
                        "both" => 10 // 左右边距
                    ],
                ]
            ],
        ]
    ],
    'PHONE_SHOP_GOODS_DETAIL_COMPONENT' => [
        'title' => get_lang('dict_diy.shop_goods_detail_component_type_basic'),
        'list' => [
            'PhoneShopGoodsDetailBasicInfo' => [
                'title' => '基础信息',
                'icon' => 'iconfont iconjichuxinxi',
                'path' => 'edit-phone-shop-goods-detail-basic-info',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'uses' => 1,
                'sort' => 10011,
                'position' => 'top_fixed', // 组件置顶标识，不能拖拽
                'value' => [
                    'menuContent' => 'index,search,cart,member',
                    'layoutStyle' => 'standard',
                    'titleLines' => 2,
                    'showBrand' => true,
                    'showSubtitle' => true,
                    'showLabels' => true,
                    'showDeviceMeta' => true,
                    'imeiShow' => true, // 二手机:详情是否展示 IMEI
                    'medium' => [
                        'type' => 'square_img',
                        'indicator' => true
                    ],
                    'priceRegion' => [
                        'showWay' => 'normal',
                        'bgImg' => 'addon/phone_shop/diy/goods_detail/style_01.jpg',
                        'marketingBgImg' => 'addon/phone_shop/diy/goods_detail/marketing_style_01.jpg',
                        'goodsStyle' => [
                            'title' => '风格1',
                            'value' => 'style-1'
                        ],
                        'marketingStyle' => [
                            'title' => '风格1',
                            'value' => 'style-1'
                        ]
                    ],
                    'goodsInfo' => [
                        'titleColor' => '#333333',
                        'subTitleColor' => '#999999',
                        'saleInfoColor' => '#999999',
                        'priceTopRounded' => 20,
                        'priceBottomRounded' => 0,
                        'topRounded' => 20,
                        'bottomRounded' => 0,
                        "priceBgColor" => '',
                        "startBgColor" => '#ffffff',
                        "endBgColor" => '#f6f6f6',
                        "priceAboutMargin" => 0,
                        "priceTopMargin" => -22,
                        "topMargin" => -17,
                        "aboutMargin" => 0
                    ],
                    'saleInfo' => ['underlined_price','sales','stock']
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
                        "top" => 0, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 0 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsDetailPurchaseService' => [
                'title' => '商品服务',
                'icon' => 'iconfont iconshangpinfuwu1',
                'path' => 'edit-phone-shop-goods-detail-purchase-service',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'uses' => 1,
                'sort' => 10012,
                'value' => [
                    'serviceConfig' => ['goods_service','spec_select','delivery_info','coupons','activity']
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 2, // 组件背景图片的透明度，0~10
                    "componentStartBgColor" => '#FFFFFF', // 组件背景颜色（开始）
                    "componentEndBgColor" => '', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to bottom', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 12, // 组件上圆角
                    "bottomRounded" => 12, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 0,// 元素上圆角
                    "bottomElementRounded" => 0, // 元素下圆角
                    "margin" => [
                        "top" => 12, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsDetailEvaluate' => [
                'title' => '商品评价',
                'icon' => 'iconfont iconshangpinpingjia1',
                'path' => 'edit-phone-shop-goods-detail-evaluate',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'uses' => 1,
                'sort' => 10014,
                'value' => [
                    'isShow' => true,
                    'title' => '',
                    'layoutStyle' => 'standard',
                    'showSource' => true,
                    'emptyText' => '暂无真实成交评价'
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 2, // 组件背景图片的透明度，0~10
                    "componentStartBgColor" => '#FFFFFF', // 组件背景颜色（开始）
                    "componentEndBgColor" => '', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to bottom', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 12, // 组件上圆角
                    "bottomRounded" => 12, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 0,// 元素上圆角
                    "bottomElementRounded" => 0, // 元素下圆角
                    "margin" => [
                        "top" => 12, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsDetailAttr' => [
                'title' => '商品属性',
                'icon' => 'iconfont iconshangpinshuxing',
                'path' => 'edit-phone-shop-goods-detail-attr',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'uses' => 1,
                'sort' => 10015,
                'value' => [
                    'isShow' => true,
                    'title' => '本机参数',
                    'layoutStyle' => 'list',
                    'previewCount' => 4,
                    'defaultExpand' => false
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 2, // 组件背景图片的透明度，0~10
                    "componentStartBgColor" => '#FFFFFF', // 组件背景颜色（开始）
                    "componentEndBgColor" => '', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to bottom', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 12, // 组件上圆角
                    "bottomRounded" => 12, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 0,// 元素上圆角
                    "bottomElementRounded" => 0, // 元素下圆角
                    "margin" => [
                        "top" => 12, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsDetailQc' => [
                'title' => '质检报告',
                'icon' => 'iconfont iconshangpinshuxing',
                'path' => 'edit-phone-shop-goods-detail-qc',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'uses' => 1,
                'sort' => 10016,
                'value' => [
                    'isShow' => true,
                    'title' => '官方质检报告',
                    'subTitle' => '逐项检测 · 真实成色',
                    'layoutStyle' => 'professional',
                    'defaultExpand' => 'collapsed',
                    'themeColor' => '#1A6DFF',
                    'titleColor' => '#1D2129',
                    'showSummaryBar' => true,
                    'showBadge' => true,
                    'showNormalItems' => true
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133",
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    "componentStartBgColor" => '#FFFFFF',
                    "componentEndBgColor" => '',
                    "componentGradientAngle" => 'to bottom',
                    "topRounded" => 12,
                    "bottomRounded" => 12,
                    "elementBgColor" => '',
                    "topElementRounded" => 0,
                    "bottomElementRounded" => 0,
                    "margin" => [
                        "top" => 12,
                        "bottom" => 0,
                        "both" => 10
                    ]
                ]
            ],
            'PhoneShopGoodsDetailDesc' => [
                'title' => '商品详情',
                'icon' => 'iconfont iconshangpinxiangqing',
                'path' => 'edit-phone-shop-goods-detail-desc',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'uses' => 1,
                'sort' => 10016,
                'value' => [
                    'isShow' => true
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 2, // 组件背景图片的透明度，0~10
                    "componentStartBgColor" => '#FFFFFF', // 组件背景颜色（开始）
                    "componentEndBgColor" => '', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to bottom', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 12, // 组件上圆角
                    "bottomRounded" => 12, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 0,// 元素上圆角
                    "bottomElementRounded" => 0, // 元素下圆角
                    "margin" => [
                        "top" => 12, // 上边距
                        "bottom" => 20, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsDetailBottom' => [
                'title' => '底部菜单',
                'icon' => 'iconfont icondibucaidan',
                'path' => 'edit-phone-shop-goods-detail-bottom',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'position' => 'bottom_fixed', // 组件置底标识，不能拖拽
                'uses' => 1,
                'sort' => 10017,
                'value' => [
                    'menuContent' => ['index','service','cart'],
                    'layoutStyle' => 'standard',
                    'cartName' => '加入购物车',
                    'buyName' => '立即购买',
                    'cartIsShow' => true,
                    'buyIsShow' => true,        // 立即购买按钮显隐
                    'forwardIsShow' => false,   // 一键转发朋友圈按钮(与立即购买同级)
                    'forwardName' => '一键转发', // 转发按钮文字(可自定义,避免过长)
                    'cartStyle' => [
                        "textColor" => "#FFFFFF", // 文字颜色
                        "fontSize" => 16,
                        "gradientAngle" => "to right",
                        "startColor" => "#FFB000",
                        "endColor" => "#FFA029"
                    ],
                    'buyStyle' => [
                        "textColor" => "#FFFFFF", // 文字颜色
                        "fontSize" => 16,
                        "gradientAngle" => "to right",
                        "startColor" => "#FB7939",
                        "endColor" => "#FF4142"
                    ],
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 2, // 组件背景图片的透明度，0~10
                    "componentStartBgColor" => '#FFFFFF', // 组件背景颜色（开始）
                    "componentEndBgColor" => '', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to bottom', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 0, // 组件上圆角
                    "bottomRounded" => 0, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 0,// 元素上圆角
                    "bottomElementRounded" => 0, // 元素下圆角
                    "margin" => [
                        "top" => 0, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 0 // 左右边距
                    ]
                ]
            ],
            'PhoneShopGoodsDetailSow' => [
                'title' => '种草秀',
                'icon' => 'iconfont iconzhongcaoxiu',
                'path' => 'edit-phone-shop-goods-detail-sow',
                'support_page' => [ 'DIY_PHONE_SHOP_GOODS_DETAIL' ],
                'uses' => 0,
                'sort' => 10018,
                'value' => [
                    'isShow' => true
                ],
                // 组件属性
                'template' => [
                    "textColor" => "#303133", // 文字颜色
                    'pageStartBgColor' => '', // 底部背景颜色（开始）
                    'pageEndBgColor' => '', // 底部背景颜色（结束）
                    'pageGradientAngle' => 'to bottom', // 渐变角度，从上到下（to bottom）、从左到右（to right）
                    'componentBgUrl' => '', // 组件背景图片
                    'componentBgAlpha' => 2, // 组件背景图片的透明度，0~10
                    "componentStartBgColor" => '#FFFFFF', // 组件背景颜色（开始）
                    "componentEndBgColor" => '', // 组件背景颜色（结束）
                    "componentGradientAngle" => 'to bottom', // 渐变角度，上下（to bottom）、左右（to right）
                    "topRounded" => 12, // 组件上圆角
                    "bottomRounded" => 12, // 组件下圆角
                    "elementBgColor" => '', // 元素背景颜色
                    "topElementRounded" => 0,// 元素上圆角
                    "bottomElementRounded" => 0, // 元素下圆角
                    "margin" => [
                        "top" => 12, // 上边距
                        "bottom" => 0, // 下边距
                        "both" => 10 // 左右边距
                    ]
                ]
            ]
        ]
    ],
];
