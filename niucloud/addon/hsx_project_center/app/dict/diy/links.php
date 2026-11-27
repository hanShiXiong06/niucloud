<?php
declare(strict_types=1);

return [
    'HSX_PROJECT_CENTER_BASE_LINK' => [
        'title' => '项目合作中心',
        'type' => 'folder',
        'child_list' => [
            [
                'name' => 'HSX_PROJECT_CENTER_LINK',
                'title' => '项目参与',
                'child_list' => [
                    [
                        'name' => 'HSX_PROJECT_CENTER_PROJECT_DETAIL',
                        'title' => '项目参与页面',
                        'url' => '/addon/hsx_project_center/pages/project/detail',
                        'is_share' => 1,
                        'action' => '',
                    ],
                ],
            ],
        ],
    ],
];
