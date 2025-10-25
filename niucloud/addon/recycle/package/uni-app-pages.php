<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/recycle", 
            "pages": [
				{
					"path": "pages/index",
					"style": {
						"navigationBarTitleText": "回收主页"
					}
				},{
					"path": "pages/price",
					"style": {
						"navigationBarTitleText": "二手机回收报价单"
					},
					"needLogin": true
				},
				{
					"path": "pages/order/order",
					"style": {
						"navigationBarTitleText": "立即下单"
					},
					"needLogin": true
				},
				{
					"path": "pages/order/list",
					"style": {
						"navigationBarTitleText": "订单列表"
					},
					"needLogin": true
				},
				{
					"path": "pages/order/detail",
					"style": {
						"navigationBarTitleText": "订单详情"
					},
					"needLogin": true
				},
				{
					"path": "pages/payment/index",
					"style": {
						"navigationBarTitleText": "收款方式"
					},
					"needLogin": true
				},
				{
                    "path": "pages/return_order/list",
                    "style": {
                        "navigationBarTitleText": "退货订单"
                    },
                    "needLogin": true
                },{
                    "path": "pages/return_order/detail",
                    "style": {
                        "navigationBarTitleText": "退货订单详情"
                    },
                    "needLogin": true
                }
			]
        },
// PAGE_END
EOT
];