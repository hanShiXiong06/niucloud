<?php

return [
    'HOME_SERVICE_COMPONENT' => [
        'title' => get_lang('dict_diy.home_service_component_type_basic'),
        'list' => [
            'HomeServiceMemberInfo' => [
                'title' => '会员信息',
                'icon' => 'iconfont iconhuiyuanqiandaopc',
                'path' => 'edit-homeServicemember-info',
                'support_page' => [ 'DIY_HOME_SERVICE_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10013,
                'value' => [
                    "style" => "style-1",
                    "styleName" => "风格1",
                    'bgUrl' => '',
                    'bgColorStart' => '',
                    'bgColorEnd' => ''
                ],
            ],
			'HomeServiceCard' => [
			    'title' => '次卡管理',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-card',
			    'support_page' => [ 'DIY_HOME_SERVICE_MEMBER_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
			        "style" => "style-1",
			        "styleName" => "风格1",
			        'bgUrl' => '',
			        'bgColorStart' => '',
			        'bgColorEnd' => '',
					'linkUrl' => [
						'name' => ''
					],
			    ],
			],
			'HomeServiceBanner' => [
			    'title' => '广告图片',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-banner',
			    'support_page' => [ 'DIY_HOME_SERVICE_MEMBER_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
			        "style" => "style-1",
			        "styleName" => "风格1",
			        'bgUrl' => '',
			        'bgColorStart' => '',
			        'bgColorEnd' => ''
			    ],
			],
			'HomeServiceSettle' => [
			    'title' => '入驻模块',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-settle',
			    'support_page' => [ 'DIY_HOME_SERVICE_MEMBER_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
			        "style" => "style-1",
			        "styleName" => "风格1",
			        'bgUrl' => '',
					'leftText'=>'',
					'rightText'=>'',
			        'bgColorStart' => '',
					'leftDesc' =>'',
					"rightDesc"=>'',
			        'bgColorEnd' => ''
			    ],
			],
            'HomeServiceOrderInfo' => [
                'title' => '我的订单',
                'icon' => 'iconfont icondingdanzhongxinPC-1',
                'path' => 'edit-homeService-order-info',
                'support_page' => [ 'DIY_HOME_SERVICE_MEMBER_INDEX' ],
                'uses' => 1,
                'sort' => 10014,
                'value' => [
                    "textColor" => "#303133",
                    "fontSize" => 16,
                    "fontWeight" => "normal",
                    "text" => "我的订单",
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

			'HomeServiceCarouselSearch' => [
			    'title' => '顶部导航',
			    'icon' => 'iconfont icondingdanzhongxinPC-1',
			    'path' => 'edit-homeService-carousel-search',
			    'support_page' => [ 'DIY_HOME_SERVICE_INDEX' ],
			    'uses' => 1,
			    'sort' => 10014,
				'value' => [
						'positionWay' => 'static',
						'fixedBgColor' => '',
						'bgGradient' => false,
						// 搜索设置
						'search' => [
							'logo' => '',
							'text' => '请输入搜索关键词',
							'link' => [
								'name' => ''
							],
							'style' => 'style-1',
							'styleName' => '风格一',
							'positionColor' => '#000000',
							'hotWord' => [
								"interval" => 3,
								'list' => []
							],
							'color' => '#999999',
							'btnColor' => '#ffffff',
							'bgColor' => '#ffffff',
							'btnBgColor' => '#26DD00'
						],
						// 轮播图设置
						'swiper' => [
							'control' => true, // 控制显示隐藏
							"interval" => 5,
							'indicatorColor' => 'rgba(0, 0, 0, 0.3)', // 未选中颜色
							"indicatorActiveColor" => '#FF0E0E',
							'indicatorStyle' => 'style-1',
							'indicatorAlign' => 'center',
							'swiperStyle' => 'style-1',
							'imageHeight' => 168,
							'topRounded' => 0,
							'bottomRounded' => 0,
							'list' => [
								[
									"imageUrl" => "",
									"imgWidth" => 690,
									"imgHeight" => 330,
									"link" => [
										"name" => ""
									]
								]
							]
						]
					]
			],
			'HomeServiceGuarantee' => [
			    'title' => '服务保障',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-guarantee',
			    'support_page' => [ 'DIY_HOME_SERVICE_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
			        "style" => "style-1",
			        "styleName" => "风格1",
			        'bgUrl' => '',
			        'bgColorStart' => '',
			        'bgColorEnd' => ''
			    ],
			],
			'HomeServiceSubguarantee' => [
			    'title' => '售后保障',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-subguarantee',
			    'support_page' => [ 'DIY_HOME_SERVICE_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
			        "style" => "style-1",
			        "styleName" => "风格1",
			        'bgUrl' => '',
			        'bgColorStart' => '',
			        'bgColorEnd' => '',
					'desc' =>'',
					'title' => '',
					'textColor' =>'',
					'titleTextColor' => '',
					'titleBgColor' => ''

			    ],
			],
			'HomeServiceCardList' => [
			    'title' => '次卡列表',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-cardList',
			    'support_page' => [ 'DIY_HOME_SERVICE_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
			        'style' => 'style-1',
			        'source' => 'all',
			        'num' => 10,
			        'leftBg' => '',
			        'goods_category' => '',
			        "goods_category_name" => "请选择",
			        'goods_ids' => [],
					'bgUrl' =>'',
			        "sortWay" => "default", // 排序方式，default：综合，sale_num：销量，price：价格
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
			        "imgElementRounded" => 10,// 图片圆角
			    ],
			],
			'HomeServicePanicBuying' => [
			    'title' => '新人抢购',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-panicBuying',
			    'support_page' => [ 'DIY_HOME_SERVICE_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
                    'style' => 'style-1',
                    'source' => 'custom',
                    'num' => 10,
                    'goods_category' => '',
                    "goods_category_name" => "请选择",
                    'goods_ids' => [],
					'bgUrl' =>'',
                    "sortWay" => "default", // 排序方式，default：综合，sale_num：销量，price：价格
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
					'bgUrl' =>'',
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
			'HomeServiceRubikCube' => [
				'title' => '魔方',
				'icon' => 'iconfont iconmofangpc',
				'path' => 'edit-homeService-rubik-cube',
				'support_page' => ['DIY_HOME_SERVICE_INDEX'],
				'uses' => 0,
				'sort' => 10004,
				'value' => [
					'bgUrl' => '',
					'topBgUrl' => '',
					"topLink" => [
					    "name" => ''
					],
					"leftLink" => [
					    "name" => ''
					],
					"rightLink" => [
					    "name" => ''
					],
					'bottomLeftBgUrl' => '',
					'bottomRightBgUrl' => '',
					'textColor' => ''
				],
			],
			'HomeServiceLowCost' => [
			    'title' => '本地低价购',
			    'icon' => 'iconfont iconhuiyuanqiandaopc',
			    'path' => 'edit-homeServicemember-lowCost',
			    'support_page' => [ 'DIY_HOME_SERVICE_INDEX' ],
			    'uses' => 1,
			    'sort' => 10011,
			    'value' => [
			        'style' => 'style-1',
			        'source' => 'all',
			        'num' => 3,
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
			'HomeServiceManyGoodsList' => [
				    'title' => '多商品组',
				    'icon' => 'iconfont iconduoshangpinzupc',
				    'path' => 'edit-homeService-many-goods-list',
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

			]
        ],
    ],

];
