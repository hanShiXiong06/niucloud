<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/hsx_lead",
            "pages": [
                {
                    "path": "pages/workbench/index",
                    "style": {
                        "navigationStyle": "custom",
                        "navigationBarTitleText": "商户交付",
                        "enablePullDownRefresh": true
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/stores/index",
                    "style": { "navigationStyle": "custom", "navigationBarTitleText": "找交付门店", "enablePullDownRefresh": true },
                    "needLogin": true
                },
                {
                    "path": "pages/wallet/index",
                    "style": { "navigationStyle": "custom", "navigationBarTitleText": "我的收益", "enablePullDownRefresh": true },
                    "needLogin": true
                },
                {
                    "path": "pages/profile/index",
                    "style": { "navigationStyle": "custom", "navigationBarTitleText": "我的门店", "enablePullDownRefresh": true },
                    "needLogin": true
                }
            ]
        },
        // PAGE_END
EOT
];
