<?php
declare(strict_types=1);
return [
    [
        'key' => 'hsx_member_card_maintenance_tick',
        'name' => '会员卡过期与事件补偿',
        'desc' => '刷新过期卡，并重试失败的绩效事件',
        'time' => ['type' => 'min', 'min' => 1],
        'class' => 'addon\hsx_member_card\app\job\schedule\MaintenanceTick',
        'function' => 'doJob',
    ],
];
