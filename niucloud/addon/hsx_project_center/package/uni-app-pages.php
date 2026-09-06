<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/hsx_project_center",
            "pages": [
                {
                    "path": "pages/project/detail",
                    "style": {
                        "navigationBarTitleText": "项目详情",
                        "enablePullDownRefresh": false
                    },
                    "needLogin": false
                },
                {
                    "path": "pages/distribution/index",
                    "style": {
                        "navigationBarTitleText": "我的项目推广",
                        "enablePullDownRefresh": false
                    },
                    "needLogin": true
                }
            ]
        },
        // PAGE_END
EOT
];