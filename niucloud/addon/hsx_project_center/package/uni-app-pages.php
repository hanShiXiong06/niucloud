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
                }
            ]
        },
        // PAGE_END
EOT
];