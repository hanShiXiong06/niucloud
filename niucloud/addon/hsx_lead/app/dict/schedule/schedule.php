<?php
declare(strict_types=1);

return [[
    'key' => 'hsx_lead_inventory_sync',
    'name' => '商户ERP库存只读同步',
    'desc' => '每分钟分批推进到期连接，每个连接单次最多读取一页；完整成功后发布库存批次',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\SyncInventory',
    'function' => 'doJob',
]];

