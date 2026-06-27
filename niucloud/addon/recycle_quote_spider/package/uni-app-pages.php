<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/recycle_quote_spider",
            "pages": [
                {
                    "path": "pages/price/show_price",
                    "style": {
                        "navigationBarTitleText": "二手机回收报价单",
                         // #ifndef H5
                        "navigationStyle": "custom",
                        // #endif
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/report/config",
                    "style": {
                        "navigationBarTitleText": "生成报价单"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/report/preview",
                    "style": {
                        "navigationBarTitleText": "报价单预览"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/report/personalize",
                    "style": {
                        "navigationBarTitleText": "个性化调整"
                    },
                    "needLogin": true
                }
            ]
        },
// PAGE_END
EOT
];