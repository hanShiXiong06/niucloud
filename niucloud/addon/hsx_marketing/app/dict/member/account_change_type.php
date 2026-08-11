<?php
declare(strict_types=1);

use app\dict\member\MemberAccountTypeDict;

return [
    MemberAccountTypeDict::POINT => [
        'hsx_marketing_reward' => ['name' => '营销活动积分奖励', 'inc' => 1, 'dec' => 0],
        'hsx_marketing_reward_reversal' => ['name' => '营销活动积分冲红', 'inc' => 0, 'dec' => 1],
    ],
    MemberAccountTypeDict::GROWTH => [
        'hsx_marketing_reward' => ['name' => '营销活动成长值奖励', 'inc' => 1, 'dec' => 0],
        'hsx_marketing_reward_reversal' => ['name' => '营销活动成长值冲红', 'inc' => 0, 'dec' => 1],
    ],
];
