<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/hsx_phone_query", 
            "pages": [
				{
					"path": "pages/index",
					"style": {
						"navigationBarTitleText": "查询界面"
					},
					"needLogin": true
				},
				{
					"path": "pages/history",
					"style": {
						"navigationBarTitleText": "查询历史"
					},
					"needLogin": true
				},
				{
					"path": "pages/detail",
					"style": {
						"navigationBarTitleText": "查询详情"
					},
					"needLogin": true,
				}
			]
        },
// PAGE_END
EOT
];