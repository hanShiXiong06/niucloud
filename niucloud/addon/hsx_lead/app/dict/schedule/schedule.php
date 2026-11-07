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
],[
    'key' => 'hsx_lead_unpaid_order_close',
    'name' => '商户协作未支付订单超时关闭',
    'desc' => '会员报价10分钟、成交付佣20分钟超时后分批核对关闭；支付中或已收款不强行关闭，不取消成交责任，不退款',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\CloseUnpaidOrders',
    'function' => 'doJob',
],[
    'key' => 'hsx_lead_notice_recover',
    'name' => '商户协作通知待处理事件补偿',
    'desc' => '按站点轮转处理未入队或中断的持久事件；已提交或结果不明的渠道不盲目重发，不保证微信终端送达',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\RecoverCooperationNotices',
    'function' => 'doJob',
],[
    'key' => 'hsx_lead_order_refund_reconcile',
    'name' => '商户协作订单原退款进度核对',
    'desc' => '分批查询已提交原退款及补齐成功回调；不发起新退款，不重发结果未知的退款',
    'time' => ['type' => 'min', 'min' => 2],
    'class' => 'addon\\hsx_lead\\app\\job\\ReconcileOrderRefunds',
    'function' => 'doJob',
]];

