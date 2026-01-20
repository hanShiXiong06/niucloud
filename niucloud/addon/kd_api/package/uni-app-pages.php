<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
                // *********************************** 快递API ***********************************
             {
			"root": "addon/kd_api",
			"pages": [
				{
					"path": "pages/index",
					"style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
						"navigationBarTitleText": "接口信息"
					},
					"needLogin": true
				},
				{
					"path": "pages/order",
					"style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
						"navigationBarTitleText": "订单列表"
					},
					"needLogin": true
				}
			]
		},
                // PAGE_END
EOT
];