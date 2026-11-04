<?php
declare(strict_types=1);

return [[
    'key' => 'hsx_lead_inventory_sync',
    'name' => '商户ERP库存只读同步',
    'desc' => '每分钟分批推进到期连接，每个连接单次最多读取一页；完整成功后发布库存批次',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\SyncInventory',
    'function' => 'doJob',
],[
    'key' => 'hsx_lead_promotion_settle',
    'name' => '商户一级推广奖励到期结算',
    'desc' => '核对原付款及退款状态后，将到期奖励记入框架会员佣金；不执行提现打款',
    'time' => ['type' => 'min', 'min' => 5],
    'class' => 'addon\\hsx_lead\\app\\job\\SettlePromotion',
    'function' => 'doJob',
]];

