<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/fast_pay", 
            "pages": [
				{
				"path": "pages/pay/index",
				"style": {
					"navigationBarTitleText": "收银台"
				},
				"needLogin": true
			},
				{
				"path": "pages/pay/result",
				"style": {
					"navigationBarTitleText": "支付结果"
				},
				"needLogin": true
			},
				{
				"path": "pages/pay/list",
				"style": {
					"navigationBarTitleText": "支付记录"
				},
				"needLogin": true
			},
				{
				"path": "pages/pay/pay",
				"style": {
					"navigationBarTitleText": "商户收款"
				}
			},
				{
				"path": "pages/partner/index",
				"style": {
					"navigationBarTitleText": "收银台"
				},
				"needLogin": true
			},
				{
				"path": "pages/partner/result",
				"style": {
					"navigationBarTitleText": "支付结果"
				},
				"needLogin": true
			},
				{
				"path": "pages/partner/list",
				"style": {
					"navigationBarTitleText": "支付记录"
				},
				"needLogin": true
			},
				{
				"path": "pages/partner/pay",
				"style": {
					"navigationBarTitleText": "商户收款"
				}
			}
			]
        },
// PAGE_END
EOT
];