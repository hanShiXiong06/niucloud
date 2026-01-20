<?php
return [
    [
        "name" => "聚合快递海报", // 海报模板名称
        'type' => 'tk_jhkd_poster', // 海报类型
        "data" => [
            "global" => [
                "width" =>720,
                "height" =>1280,
                "bgType" =>"url",
                "bgColor" =>"#ffffff",
                "bgUrl" =>"addon/tk_jhkd/poster/jhkd.png"
            ],
            "value" =>[
                [
                    "type" =>"qrcode",
                    "path" =>"qrcode",
                    "uses" =>1,
                    "relate" =>"url",
                    "value" =>"",
                    "id" =>"cf1kcq281hk",
                    "componentName" =>"Qrcode",
                    "componentTitle" =>"二维码",
                    "width" =>104,
                    "height" =>104,
                    "minWidth" =>30,
                    "minHeight" =>30,
                    "x" =>127,
                    "y" =>491,
                    "angle" =>0,
                    "zIndex" =>1
                ]
            ]
        ]
    ],

];