<?php
return [
    [
        "name" => "商家收款码通用海报", // 海报模板名称
        'type' => 'fast_pay_poster', // 海报类型
        "data" => [
            "global" => [
                "width" =>720,
                "height" =>1280,
                "bgType" =>"url",
                "bgColor" =>"#ffffff",
                "bgUrl" =>"/addon/fast_pay/poster/wx.png"
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
                    "width" =>370,
                    "height" =>370,
                    "minWidth" =>30,
                    "minHeight" =>30,
                    "x" =>176,
                    "y" =>416,
                    "angle" =>0,
                    "zIndex" =>1
                ],
                [
                    "type" =>"text",
                    "path" =>"text",
                    "uses" =>0,
                    "relate" =>"",
                    "value" =>"向商家付款",
                    "id" =>"5rpkj7abrh00",
                    "componentName" =>"Text",
                    "componentTitle" =>"文本",
                    "width" =>232,
                    "height" =>53,
                    "minWidth" =>120,
                    "minHeight" =>44,
                    "x" =>256,
                    "y" =>810,
                    "angle" =>0,
                    "zIndex" =>2,
                    "fontFamily" =>"static/font/SourceHanSansCN-Regular.ttf",
                    "fontSize" =>40,
                    "weight" =>false,
                    "lineHeight" =>10,
                    "fontColor" =>"#848484"
                ]
            ]
        ]
    ],

];