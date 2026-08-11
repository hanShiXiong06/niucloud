<?php
declare(strict_types=1);

return [
    'HSX_MARKETING_BASE_LINK' => [
        'title' => '营销中心', 'type' => 'folder',
        'child_list' => [[
            'name' => 'HSX_MARKETING_LINK', 'title' => '营销任务与奖励',
            'child_list' => [[
                'name' => 'HSX_MARKETING_INDEX', 'title' => '我的任务与奖励',
                'url' => '/addon/hsx_marketing/pages/index', 'is_share' => 1, 'action' => '',
            ]],
        ]],
    ],
];
