<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/hsx_ai",
            "pages": [
                {
                    "path": "pages/chat/index",
                    "style": {
                        "navigationBarTitleText": "AI 选机助手",
                        // #ifndef H5
                        "navigationStyle": "custom"
                        // #endif
                    }
                }
            ]
        },
        // PAGE_END
EOT
];
