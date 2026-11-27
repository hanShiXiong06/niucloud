<?php
return [[
    'key' => 'hsx_kingdee_process', 'name' => '金蝶接入预检与异常提醒',
    'desc' => '本地预览版：仅预检和提醒，不写金蝶账套',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_kingdee\\app\\job\\Process', 'function' => 'doJob',
]];
