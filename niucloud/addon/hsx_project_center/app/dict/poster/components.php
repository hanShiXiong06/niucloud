<?php

return [
    'hsx_project_center_distribution' => [
        'title' => '项目推广海报组件',
        'support' => ['hsx_project_center_distribution'],
        'list' => [
            'ProjectCenterCover' => [
                'title' => '项目封面', 'type' => 'image', 'icon' => 'iconfont icontupian1',
                'path' => 'image', 'uses' => 1, 'sort' => 12100, 'relate' => 'project_cover', 'value' => '',
                'template' => ['width' => 620, 'height' => 360, 'minWidth' => 120, 'minHeight' => 100],
            ],
            'ProjectCenterTitle' => [
                'title' => '项目名称', 'type' => 'text', 'icon' => 'iconfont iconbiaoti',
                'path' => 'text', 'uses' => 1, 'sort' => 12101, 'relate' => 'project_title', 'value' => '项目合作邀请',
                'template' => ['width' => 620, 'height' => 90],
            ],
            'ProjectCenterSubtitle' => [
                'title' => '项目简介', 'type' => 'text', 'icon' => 'iconfont iconbiaoti',
                'path' => 'text', 'uses' => 1, 'sort' => 12102, 'relate' => 'project_subtitle', 'value' => '查看项目详情与参与方式',
                'template' => ['width' => 620, 'height' => 72],
            ],
            'ProjectCenterInviter' => [
                'title' => '推广人', 'type' => 'text', 'icon' => 'iconfont iconnicheng1',
                'path' => 'text', 'uses' => 1, 'sort' => 12103, 'relate' => 'inviter_text', 'value' => '项目推广人：会员昵称',
                'template' => ['width' => 420, 'height' => 44],
            ],
            'ProjectCenterLevel' => [
                'title' => '推广身份', 'type' => 'text', 'icon' => 'iconfont iconbiaoti',
                'path' => 'text', 'uses' => 1, 'sort' => 12104, 'relate' => 'level_text', 'value' => '合作伙伴 · 一级佣金系数 100%',
                'template' => ['width' => 520, 'height' => 44],
            ],
            'ProjectCenterScanText' => [
                'title' => '扫码提示', 'type' => 'text', 'icon' => 'iconfont iconbiaoti',
                'path' => 'text', 'uses' => 1, 'sort' => 12105, 'relate' => 'scan_text', 'value' => '长按识别二维码，查看项目详情',
                'template' => ['width' => 360, 'height' => 60],
            ],
            'ProjectCenterFooter' => [
                'title' => '海报说明', 'type' => 'text', 'icon' => 'iconfont iconbiaoti',
                'path' => 'text', 'uses' => 1, 'sort' => 12106, 'relate' => 'footer_text', 'value' => '项目说明与实际办理结果以项目页面为准',
                'template' => ['width' => 620, 'height' => 42],
            ],
        ],
    ],
];
