<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/hsx_lead",
            "pages": [
                { "path": "pages/index", "style": { "navigationStyle": "custom", "navigationBarTitleText": "商户协作" }, "needLogin": false },
                { "path": "pages/invite/index", "style": { "navigationBarTitleText": "登录注册" }, "needLogin": false },
                { "path": "pages/task/index", "style": { "navigationStyle": "custom", "navigationBarTitleText": "商户协作" }, "needLogin": true },
                { "path": "pages/catalog/index", "style": { "navigationStyle": "custom", "navigationBarTitleText": "选择业务能力" }, "needLogin": true },
                { "path": "pages/entity/index", "style": { "navigationStyle": "custom", "navigationBarTitleText": "成员管理" }, "needLogin": true },
                { "path": "pages/membership/index", "style": { "navigationStyle": "custom", "navigationBarTitleText": "门店会员" }, "needLogin": true },
                { "path": "pages/orders/index", "style": { "navigationStyle": "custom", "navigationBarTitleText": "我的付款单" }, "needLogin": true },
                { "path": "pages/notifications/index", "style": { "navigationStyle": "custom", "navigationBarTitleText": "通知偏好" }, "needLogin": true },
                {
                    "path": "pages/workbench/index",
                    "style": {
                        "navigationStyle": "custom",
                        "navigationBarTitleText": "找客户",
                        "enablePullDownRefresh": false
                    },
                    "needLogin": false
                },
                {
                    "path": "pages/stores/index",
                    "style": { "navigationStyle": "custom", "navigationBarTitleText": "找门店", "enablePullDownRefresh": false },
                    "needLogin": false
                },
                {
                    "path": "pages/wallet/index",
                    "style": { "navigationStyle": "custom", "navigationBarTitleText": "我的收益", "enablePullDownRefresh": true },
                    "needLogin": true
                },
                {
                    "path": "pages/profile/index",
                    "style": { "navigationStyle": "custom", "navigationBarTitleText": "我的门店", "enablePullDownRefresh": true },
                    "needLogin": false
                }
            ]
        },
        // PAGE_END
EOT
];
