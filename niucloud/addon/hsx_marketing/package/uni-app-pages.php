<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/hsx_marketing",
            "pages": [
                {
                    "path": "pages/index",
                    "style": {
                        "navigationBarTitleText": "任务与奖励",
                        "enablePullDownRefresh": true
                    },
                    "needLogin": true
                }
            ]
        },
        // PAGE_END
EOT
];