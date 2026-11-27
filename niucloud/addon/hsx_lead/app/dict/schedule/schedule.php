<?php
declare(strict_types=1);

return [[
    'key' => 'hsx_lead_virtual_payment_reconcile',
    'name' => '会员微信虚拟支付原单核对',
    'desc' => '每分钟分批查询原虚拟支付及重放已核实退款事实；由框架处理收款和权益回退，不新建付款、不重发退款、不将未知结果当作失败',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\ReconcileVirtualPayments',
    'function' => 'doJob',
], [
    'key' => 'hsx_lead_financial_notice_recover',
    'name' => '商户业务通知补偿',
    'desc' => '处理启用后的资金流水及明确的新接单计划；分批补漏、退避重试，过时节点不追发，受理或结果不明不重发',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\RecoverFinancialNotices',
    'function' => 'doJob',
], [
    'key' => 'hsx_lead_inventory_sync',
    'name' => '商户ERP库存只读同步',
    'desc' => '每分钟分批推进到期连接，每个连接单次最多读取一页；完整成功后发布库存批次',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\SyncInventory',
    'function' => 'doJob',
], [
    'key' => 'hsx_lead_promotion_settle',
    'name' => '商户一级推广奖励到期结算',
    'desc' => '核对原付款及退款状态后，将到期奖励记入框架会员佣金；不执行提现打款',
    'time' => ['type' => 'min', 'min' => 5],
    'class' => 'addon\\hsx_lead\\app\\job\\SettlePromotion',
    'function' => 'doJob',
], [
    'key' => 'hsx_lead_unpaid_order_close',
    'name' => '商户协作未支付订单超时关闭',
    'desc' => '每分钟分批恢复已由管理员核实的收款入账；会员及成交付佣支付单满2分钟核对关闭，已收款或结果不明不强关，不发起真实转账',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\CloseUnpaidOrders',
    'function' => 'doJob',
], [
    'key' => 'hsx_lead_notice_recover',
    'name' => '商户协作通知待处理事件补偿',
    'desc' => '按站点轮转处理未入队或中断的持久事件；已提交或结果不明的渠道不盲目重发，不保证微信终端送达',
    'time' => ['type' => 'min', 'min' => 1],
    'class' => 'addon\\hsx_lead\\app\\job\\RecoverCooperationNotices',
    'function' => 'doJob',
], [
    'key' => 'hsx_lead_order_refund_reconcile',
    'name' => '商户协作订单原退款进度核对',
    'desc' => '分批核对协作订单及退出押金的已提交原退款，补齐成功回调；不发起新退款，不重发结果未知的退款',
    'time' => ['type' => 'min', 'min' => 2],
    'class' => 'addon\\hsx_lead\\app\\job\\ReconcileOrderRefunds',
    'function' => 'doJob',
]];
