<?php
declare(strict_types=1);

$make = static fn(string $scene): array => [
    'tid' => '',
    'content' => [
        ['奖励名称', '{reward_name}', 'thing1'], ['奖励内容', '{reward_content}', 'thing2'],
        ['奖励状态', '{status_text}', 'phrase3'], ['失效时间', '{expire_time}', 'time4'],
    ],
    'kid_list' => [1, 2, 3, 4], 'scene_desc' => $scene,
    'tips' => '请在微信公众平台选择任务/奖励结果类模板，并在牛云通知设置中配置模板 ID 与关键词。',
];

return [
    'hsx_marketing_reward_available' => $make('营销奖励待领取'),
    'hsx_marketing_reward_grant_success' => $make('营销奖励到账'),
    'hsx_marketing_reward_grant_failed' => $make('营销奖励发放失败'),
    'hsx_marketing_reward_expiring' => $make('营销奖励到期提醒'),
];
