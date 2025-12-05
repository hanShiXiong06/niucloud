<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
            // *********************************** ai_image ***********************************
           	{
			"root": "addon/ai_image",
			"pages": [
				{
					"path": "pages/index",
					"style": {
						"navigationBarTitleText": "首页",
						// #ifndef H5 
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					}
				},
				{
					"path": "pages/member",
					"style": {
						"navigationBarTitleText": "会员中心",
						// #ifndef H5 
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					}
				},
				{
					"path": "pages/create",
					"style": {
						"navigationBarTitleText": "作品创作",
						// #ifndef H5 
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					},
					"needLogin": true
				},
				{
					"path": "pages/list",
					"style": {
						"navigationBarTitleText": "创作列表",
						// #ifndef H5 
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					},
					"needLogin": true
				},
				{
					"path": "pages/verify",
					"style": {
						"navigationBarTitleText": "卡密兑换"
					},
					"needLogin": true
				},
				{
					"path": "pages/card",
					"style": {
						"navigationBarTitleText": "卡密列表"
					},
					"needLogin": true
				},
				{
					"path": "pages/package",
					"style": {
						"navigationBarTitleText": "套餐列表"
					},
					"needLogin": true
				},
				{
					"path": "pages/packageorder",
					"style": {
						"navigationBarTitleText": "套餐订单"
					},
					"needLogin": true
				},
				{
					"path": "pages/model",
					"style": {
						"navigationBarTitleText": "智能体"
					}
				},
				{
					"path": "pages/help/list",
					"style": {
						"navigationBarTitleText": "帮助中心"
					}
				},
				{
					"path": "pages/help/detail",
					"style": {
						"navigationBarTitleText": "帮助详情"
					}
				}
			]
		},
            // PAGE_END
EOT
];