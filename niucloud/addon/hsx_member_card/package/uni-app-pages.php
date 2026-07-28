<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/hsx_member_card",
            "pages": [
                {
                    "path": "pages/member/index",
                    "style": {
                        "navigationBarTitleText": "我的会员卡",
                        "enablePullDownRefresh": true
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/member/detail",
                    "style": {
                        "navigationBarTitleText": "会员卡详情",
                        "enablePullDownRefresh": true
                    },
                    "needLogin": true
                }
            ]
        },
        // PAGE_END
EOT
];