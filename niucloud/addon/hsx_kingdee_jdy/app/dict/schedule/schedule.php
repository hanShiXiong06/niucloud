<?php
return [[
    'key' => 'hsx_kingdee_jdy_process', 'name' => '精斗云本地预检与异常提醒',
    'desc' => '接收ERP事实、检查映射、分配人工待办；不写精斗云账套',
    'time' => ['type' => 'min', 'min' => 1], 'class' => 'addon\\hsx_kingdee_jdy\\app\\job\\Process', 'function' => 'doJob',
]];
