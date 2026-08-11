<?php
declare(strict_types=1);

$variables = [
    'reward_name' => '奖励名称', 'reward_content' => '奖励内容', 'status_text' => '奖励状态',
    'expire_time' => '失效时间', 'failure_reason' => '失败原因', 'url' => '奖励中心链接',
];

return [
    'hsx_marketing_reward_available' => ['addon' => 'hsx_marketing', 'key' => 'hsx_marketing_reward_available', 'receiver_type' => 1, 'name' => '营销奖励待领取通知', 'title' => '任务达标后通知用户领取奖励', 'async' => true, 'variable' => $variables],
    'hsx_marketing_reward_grant_success' => ['addon' => 'hsx_marketing', 'key' => 'hsx_marketing_reward_grant_success', 'receiver_type' => 1, 'name' => '营销奖励到账通知', 'title' => '奖励到账后通知用户', 'async' => true, 'variable' => $variables],
    'hsx_marketing_reward_grant_failed' => ['addon' => 'hsx_marketing', 'key' => 'hsx_marketing_reward_grant_failed', 'receiver_type' => 1, 'name' => '营销奖励发放失败通知', 'title' => '多次发放失败后通知用户', 'async' => true, 'variable' => $variables],
    'hsx_marketing_reward_expiring' => ['addon' => 'hsx_marketing', 'key' => 'hsx_marketing_reward_expiring', 'receiver_type' => 1, 'name' => '营销奖励即将失效通知', 'title' => '奖励即将失效时提醒用户', 'async' => true, 'variable' => $variables],
];
