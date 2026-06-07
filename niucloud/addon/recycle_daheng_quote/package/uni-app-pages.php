<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/recycle_daheng_quote",
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
                }
            ]
        },
// PAGE_END
EOT
];