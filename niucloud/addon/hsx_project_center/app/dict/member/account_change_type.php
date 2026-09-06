<?php
declare(strict_types=1);

use app\dict\member\MemberAccountTypeDict;

return [
    MemberAccountTypeDict::COMMISSION => [
        'project_center_distribution_settle' => [
            'name' => '项目推广佣金结算', 'inc' => 1, 'dec' => 0,
        ],
        'project_center_distribution_reverse' => [
            'name' => '项目退款佣金冲红', 'inc' => 0, 'dec' => 1,
        ],
    ],
];
