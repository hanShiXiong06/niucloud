<?php
declare(strict_types=1);

return [[
    'key' => 'hsx_project_center_distribution_tick',
    'name' => '项目分销结算与补偿',
    'desc' => '补建审批成功但未生成的佣金单，并结算已过保护期的一级、二级佣金',
    'time' => ['type' => 'min', 'min' => 5],
    'class' => 'addon\hsx_project_center\app\job\schedule\ProjectCenterDistributionTick',
    'function' => 'doJob',
]];
