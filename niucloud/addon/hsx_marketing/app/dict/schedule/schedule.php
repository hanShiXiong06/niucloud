<?php
declare(strict_types=1);

return [[
    'key' => 'hsx_marketing_tick', 'name' => '营销任务奖励补偿',
    'desc' => '补偿失败奖励、关闭过期领取单并发送到期提醒',
    'time' => ['type' => 'min', 'min' => 5],
    'class' => 'addon\hsx_marketing\app\job\schedule\MarketingTick', 'function' => 'doJob',
]];
