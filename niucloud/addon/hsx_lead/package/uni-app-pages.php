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
                        "navigationBarTitleText": "商户工作台",
                        "enablePullDownRefresh": true
                    },
                    "needLogin": true
                }
            ]
        },
        // PAGE_END
EOT
];
