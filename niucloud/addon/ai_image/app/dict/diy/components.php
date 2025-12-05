<?php

return [
    'AI_IMAGE' => [
        'title' => 'AI设计',
        'list' => [
            'AiImageMemberInfo' => [
                'title' => '会员信息',
                'icon' => 'iconfont iconhuiyuanqiandaopc',
                'path' => 'edit-ai-image-member-info',
                'support_page' => [  ],
                'uses' => 1,
                'sort' => 10008,
                'value' => [
                    "style" => "style-1",
                    "styleName" => "风格1",
                    'bgUrl' => '',
                    'bgColorStart' => '#64b5f6',
                    'bgColorEnd' => '#64b5f6',
                    'show_member' => true,
                ],
            ],
            'AiImageMember' => [
                'title' => '会员余额',
                'icon' => 'nc-iconfont nc-icon-huiyuandengjiV6xx1',
                'path' => 'edit-ai-image-member', // 编辑组件属性名称
                'support_page' => [], // 支持页面
                'uses' => 1, // 最大添加数量
                'sort' => 10001,
                'value' => [
                    "background" => "#64b5f6",
                    "vipbackground"=>"rgba(32, 151, 243, 1)",
                    "buttoncolor" => "#0d7ff2",
                    "textcolor"=>"#ffffff",
                    "radiussize"=>"10",
                    "padding"=>"10",
                    "descsize"=>"享受10+数字权益，大额赠送"
                ]
            ],

            'AiImageHelp' => [
                'title' => '帮助中心',
                'icon' => 'nc-iconfont nc-icon-fenleiV6xx',
                'path' => 'edit-ai-image-help',
                'support_page' => [  ],
                'uses' => 1,
                'sort' => 10008,
                'value' => [
                    "titlesize" => "24",
                    "descsize" => "24",
                    "titlecolor" => "#131314",
                    "viewshow" => "1",
                    "desccolor" => "#131314",
                    "timecolor" => "#131314",
                    "imagewidth" => "180",
                    "imageheight" => "140",
                    "bgcolor" => "#131314",
                ],
            ],
            'AiImageModel' => [
                'title' => '智能体',
                'icon' => 'nc-iconfont nc-icon-dingweiV6xx1',
                'path' => 'edit-ai-image-model',
                'support_page' => [  ],
                'uses' => 1,
                'sort' => 10008,
                'value' => [
                    "titlesize" => "24",
                    "descsize" => "24",
                    "titlecolor" => "#131314",
                    "viewshow" => "1",
                    "desccolor" => "#131314",
                    "timecolor" => "#131314",
                    "imagewidth" => "180",
                    "imageheight" => "140",
                    "bgcolor" => "#131314",
                    "arrowcolor"=>"#131314",
                    "style"=>"style1"
                ],
            ],
        ],
    ],

];

