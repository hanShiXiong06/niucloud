<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\dashboard;

/**
 * 回收经营看板指标字典
 *
 * 字典只描述指标，不写 SQL。真实统计在 MetricService，列表下钻在 FilterService。
 */
class RecycleDashboardMetricDict
{
    public const TODAY_ORDER_COUNT = 'today_order_count';
    public const TODAY_DEVICE_COUNT = 'today_device_count';
    public const TODAY_PAID_AMOUNT = 'today_paid_amount';
    public const PENDING_CHECK = 'pending_check';
    public const CHECK_TIMEOUT = 'check_timeout';
    public const PENDING_PAY = 'pending_pay';
    public const PENDING_PAY_AMOUNT = 'pending_pay_amount';
    public const INVENTORY_RECOVERY_COST = 'inventory_recovery_cost';
    public const QUOTE_CONFIRM_RATE = 'quote_confirm_rate';
    public const RETURN_RATE = 'return_rate';

    // 代卖相关
    public const TODAY_CONSIGNMENT_COUNT = 'today_consignment_count';
    public const CONSIGNMENT_LISTING_COUNT = 'consignment_listing_count';
    public const CONSIGNMENT_SOLD_AMOUNT = 'consignment_sold_amount';
    public const CONSIGNMENT_SERVICE_FEE = 'consignment_service_fee';

    // 退货相关
    public const TODAY_RETURN_DEVICE_COUNT = 'today_return_device_count';
    public const PENDING_RETURN = 'pending_return';

    // 待确认
    public const PENDING_CONFIRM_COUNT = 'pending_confirm_count';

    public static function getList(): array
    {
        return [
            self::TODAY_ORDER_COUNT => [
                'title' => '新增订单',
                'unit' => '单',
                'value_type' => 'integer',
                'category' => '业务量',
                'description' => '所选时间内新提交的回收订单数量',
                'caliber' => '统计所选时间内创建且未删除的订单',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
                'filter_key' => RecycleDashboardFilterDict::TODAY_CREATED_ORDERS,
            ],
            self::TODAY_DEVICE_COUNT => [
                'title' => '新增设备',
                'unit' => '台',
                'value_type' => 'integer',
                'category' => '业务量',
                'description' => '所选时间内新增订单中包含的设备数量',
                'caliber' => '统计所选时间内创建订单下的设备数量',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
                'filter_key' => RecycleDashboardFilterDict::TODAY_CREATED_DEVICES,
                'view_mode' => 'device_expand',
            ],
            self::TODAY_PAID_AMOUNT => [
                'title' => '已结算金额',
                'unit' => '元',
                'value_type' => 'money',
                'category' => '资金',
                'description' => '所选时间实际登记的付款及折账流水合计',
                'caliber' => '按设备结算流水的发生时间统计，包含折账；不以当前报价推算历史付款，已删除订单的真实结算仍保留',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
                'filter_key' => RecycleDashboardFilterDict::PAID_TODAY,
            ],
            self::PENDING_CHECK => [
                'title' => '待质检',
                'unit' => '单',
                'value_type' => 'integer',
                'category' => '待办',
                'description' => '当前仍有有效待质检或质检中设备的订单',
                'caliber' => '按有效质检在途设备关联的订单去重；排除未签收、已终结及已付款残留',
                'time_scope' => 'snapshot',
                'scope_label' => '当前状态',
                'filter_key' => RecycleDashboardFilterDict::PENDING_CHECK,
            ],
            self::CHECK_TIMEOUT => [
                'title' => '质检超时',
                'unit' => '单',
                'value_type' => 'integer',
                'category' => '异常',
                'description' => '签收超过阈值仍未完成质检的订单',
                'caliber' => '有效质检在途设备所在订单的签收时间超过配置阈值，按订单去重',
                'time_scope' => 'snapshot',
                'scope_label' => '当前状态',
                'filter_key' => RecycleDashboardFilterDict::CHECK_TIMEOUT,
            ],
            self::PENDING_PAY => [
                'title' => '待打款',
                'unit' => '单',
                'value_type' => 'integer',
                'category' => '待办',
                'description' => '当前仍有未结清回收款设备的订单',
                'caliber' => '按有效待打款设备关联订单去重；包括未付款、部分付款及已完成订单的真实补差',
                'time_scope' => 'snapshot',
                'scope_label' => '当前状态',
                'filter_key' => RecycleDashboardFilterDict::PENDING_PAY,
            ],
            self::PENDING_PAY_AMOUNT => [
                'title' => '待打款金额',
                'unit' => '元',
                'value_type' => 'money',
                'category' => '资金',
                'description' => '未结清的回收款，不重复累计已经支付的部分',
                'caliber' => '有效待打款设备的最终报价减已结算金额，包含部分付款后的剩余差额',
                'time_scope' => 'snapshot',
                'scope_label' => '当前状态',
                'filter_key' => RecycleDashboardFilterDict::PENDING_PAY,
            ],
            self::INVENTORY_RECOVERY_COST => [
                'title' => '库存回收成本',
                'unit' => '元',
                'value_type' => 'money',
                'category' => '库存',
                'description' => '已回收设备沉淀在库存中的回收成本',
                'caliber' => '设备状态为已回收，按 final_price 合计；销售出库未接入前不等同于利润',
                'time_scope' => 'snapshot',
                'scope_label' => '当前库存',
                'filter_key' => RecycleDashboardFilterDict::INVENTORY_DEVICES,
                'view_mode' => 'device_expand',
            ],
            self::QUOTE_CONFIRM_RATE => [
                'title' => '报价确认率',
                'unit' => '%',
                'value_type' => 'percent',
                'category' => '转化',
                'description' => '所选时间内已报价订单中，用户确认进入打款环节的比例',
                'caliber' => '所选时间内报价已确认订单数 / 所选时间内已报价订单数',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
                'filter_key' => RecycleDashboardFilterDict::QUOTE_CONFIRMED,
            ],
            self::RETURN_RATE => [
                'title' => '退货率',
                'unit' => '%',
                'value_type' => 'percent',
                'category' => '风险',
                'description' => '所选时间内形成最终结果的设备中，退回设备占比',
                'caliber' => '所选时间内已退回设备数 / (所选时间内已回收设备数 + 所选时间内已退回设备数)',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
                'filter_key' => RecycleDashboardFilterDict::RETURNED_DEVICES,
                'view_mode' => 'device_expand',
            ],
            self::TODAY_CONSIGNMENT_COUNT => [
                'title' => '新增代卖',
                'unit' => '单',
                'value_type' => 'integer',
                'category' => '业务量',
                'description' => '所选时间内转入代卖的设备数量',
                'caliber' => '统计所选时间内创建的代卖订单数',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
            ],
            self::CONSIGNMENT_LISTING_COUNT => [
                'title' => '挂牌中',
                'unit' => '台',
                'value_type' => 'integer',
                'category' => '待办',
                'description' => '当前挂牌中等待成交的代卖设备',
                'caliber' => '代卖订单状态为挂牌中的数量',
                'time_scope' => 'snapshot',
                'scope_label' => '当前状态',
            ],
            self::CONSIGNMENT_SOLD_AMOUNT => [
                'title' => '代卖成交额',
                'unit' => '元',
                'value_type' => 'money',
                'category' => '资金',
                'description' => '所选时间内代卖成交的总金额',
                'caliber' => '所选时间内代卖订单成交金额合计',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
            ],
            self::CONSIGNMENT_SERVICE_FEE => [
                'title' => '代卖服务收益',
                'unit' => '元',
                'value_type' => 'money',
                'category' => '资金',
                'description' => '所选时间内代卖服务费收益',
                'caliber' => '所选时间内代卖订单服务费合计（成交价-客户结算金额）',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
            ],
            self::TODAY_RETURN_DEVICE_COUNT => [
                'title' => '退货设备数',
                'unit' => '台',
                'value_type' => 'integer',
                'category' => '业务量',
                'description' => '所选时间内退货的设备数量',
                'caliber' => '统计所选时间内状态变为退回的设备数',
                'time_scope' => 'period',
                'scope_label' => '所选时间',
            ],
            self::PENDING_RETURN => [
                'title' => '待退回',
                'unit' => '台',
                'value_type' => 'integer',
                'category' => '待办',
                'description' => '当前待退回客户的设备',
                'caliber' => '设备当前关联的有效退回单处于待处理或退回中；不包含已完成、已取消、已删除及失效关联',
                'time_scope' => 'snapshot',
                'scope_label' => '当前状态',
            ],
            self::PENDING_CONFIRM_COUNT => [
                'title' => '待确认',
                'unit' => '台',
                'value_type' => 'integer',
                'category' => '待办',
                'description' => '当前等待客户确认报价的设备',
                'caliber' => '有效在途订单下待确认且尚未确认、尚未付款的设备数量',
                'time_scope' => 'snapshot',
                'scope_label' => '当前状态',
            ],
        ];
    }

    public static function get(string $key): array
    {
        $list = self::getList();
        return $list[$key] ?? [];
    }
}
