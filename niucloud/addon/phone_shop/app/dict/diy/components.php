<?php

return [
    'PHONE_SHOP_COMPONENT' => [
        'title' => '二手商城组件',
        'list' => [
            'PhoneGoodsList' => [
                'title' => '二手商品列表',
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
                ],
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
                    "sortWay" => "default", // 排序方式，default：综合，sale_num：销量，price：价格
                    "headStyle" => "style-1",
                    "aroundRadius" => 25,
                    "source" => "custom",
                    "goods_category" => '',
                    "goods_category_name" => '请选择',
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
                        "color" => "#999999",
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
                    ]
                ],
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
                    'couponSubTitle' => '领券下单 享购物优惠'
                ],
            ],
            'PhoneShopMemberInfo' => [
                'title' => '会员信息',
                'icon' => 'iconfont iconhuiyuanqiandaopc',
                'path' => 'edit-phone-shop-member-info',
                'support_page' => [ 'DIY_PHONE_SHOP_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10015,
                'value' => [
                    "style" => "style-1",
                    "styleName" => "风格1",
                    'bgUrl' => ''
                ],
            ],
            'PhoneShopOrderInfo' => [
                'title' => '订单中心',
                'icon' => 'iconfont icondingdanzhongxinPC-1',
                'path' => 'edit-phone-shop-order-info',
                'support_page' => [ 'DIY_PHONE_SHOP_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10016,
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
                ],
            ]
        ]
    ],

];