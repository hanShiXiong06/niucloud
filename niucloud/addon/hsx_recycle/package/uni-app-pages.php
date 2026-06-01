<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
		 {
            "root": "addon/recycle",
            "pages": [
                {
                    "path": "pages/order/order",
                    "style": {
                        "navigationBarTitleText": "回收下单"
                    }
                }
            ]
        },
        {
            "root": "addon/hsx_recycle/pages/payment/index", 
            "pages": [
				{
					"path": "pages/index",
					"style": {
						"navigationBarTitleText": "回收主页",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
					}
				},{
					"path": "pages/price",
					"style": {
						"navigationBarTitleText": "回收报价单",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
					},
					"needLogin": true
				},
				{
					"path": "pages/order/order",
					"style": {
						"navigationBarTitleText": "立即下单",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
					},
					"needLogin": true
				},
				{
					"path": "pages/order/list",
					"style": {
						"navigationBarTitleText": "订单列表",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
					},
					"needLogin": true
				},
				{
					"path": "pages/order/detail",
					"style": {
						"navigationBarTitleText": "订单详情",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
					},
					"needLogin": true
				},
				{
					"path": "pages/payment/index",
					"style": {
						"navigationBarTitleText": "收款管理",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
					},
					"needLogin": true
				},
				{
                    "path": "pages/return_order/list",
                    "style": {
                        "navigationBarTitleText": "退货订单",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
                    },
                    "needLogin": true
                },{
                    "path": "pages/return_order/detail",
                    "style": {
                        "navigationBarTitleText": "退货订单详情",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/consignment/list",
                    "style": {
                        "navigationBarTitleText": "代卖订单",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/consignment/detail",
                    "style": {
                        "navigationBarTitleText": "代卖详情",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
                    },
                    "needLogin": true
                },
                {
					"path": "pages/price/show_price",
					"style": {
						"navigationBarTitleText": "二手机回收报价单",
						 // #ifndef H5
						"navigationStyle": "custom",
						// #endif
					},
					"needLogin": true
				},
                {
					"path": "pages/member/index",
					"style": {
                        // #ifndef H5
						"navigationStyle": "custom",
						// #endif
                        "navigationBarTitleText": "%recycle.pages.member.index%"
					}
				}
			]
        },
// PAGE_END
EOT
];