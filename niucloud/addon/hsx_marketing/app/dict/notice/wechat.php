<?php
declare(strict_types=1);

$make = static fn(string $tips): array => [
    'temp_key' => '',
    'content' => [
        ['奖励名称', '{reward_name}', 'thing1'], ['奖励内容', '{reward_content}', 'thing2'],
        ['奖励状态', '{status_text}', 'phrase3'], ['失效时间', '{expire_time}', 'time4'],
    ],
    'keyword_name_list' => ['奖励名称', '奖励内容', '奖励状态', '失效时间'], 'tips' => $tips,
];

return [
    'hsx_marketing_reward_available' => $make('请配置任务达标或奖励待领取类公众号模板。'),
    'hsx_marketing_reward_grant_success' => $make('请配置奖励到账类公众号模板。'),
    'hsx_marketing_reward_grant_failed' => $make('请配置奖励发放失败类公众号模板。'),
    'hsx_marketing_reward_expiring' => $make('请配置奖励到期提醒类公众号模板。'),
];
