<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\dashboard;

/**
 * 回收看板下钻过滤字典
 *
 * 前端和看板只传 filter_key，真实业务条件统一在服务层解析。
 * 这样可以保证“看板数字”和“点击后的列表”使用同一套口径。
 */
class RecycleDashboardFilterDict
{
    public const TODAY_CREATED_ORDERS = 'today_created_orders';
    public const TODAY_CREATED_DEVICES = 'today_created_devices';
    public const PENDING_SIGN = 'pending_sign';
    public const PENDING_CHECK = 'pending_check';
    public const CHECK_TIMEOUT = 'check_timeout';
    public const PENDING_QUOTE = 'pending_quote';
    public const QUOTE_TIMEOUT = 'quote_timeout';
    public const PENDING_CONFIRM = 'pending_confirm';
    public const PENDING_PAY = 'pending_pay';
    public const PAY_TIMEOUT = 'pay_timeout';
    public const PAID_TODAY = 'paid_today';
    public const COMPLETED_TODAY = 'completed_today';
    public const RETURNED_ORDERS = 'returned_orders';
    public const PENDING_RETURN = 'pending_return';
    public const INVENTORY_DEVICES = 'inventory_devices';
    public const HIGH_COST_DEVICES = 'high_cost_devices';
    public const QUOTE_DECIDED = 'quote_decided';
    public const QUOTE_CONFIRMED = 'quote_confirmed';
    public const RETURNED_DEVICES = 'returned_devices';

    public static function getList(): array
    {
        return [
            self::TODAY_CREATED_ORDERS => [
                'name' => '今日新增订单',
                'target' => 'order_list',
                'description' => '今日创建且未删除的回收订单',
            ],
            self::TODAY_CREATED_DEVICES => [
                'name' => '今日新增设备',
                'target' => 'order_list',
                'view_mode' => 'device_expand',
                'description' => '今日创建订单下的设备',
            ],
            self::PENDING_SIGN => [
                'name' => '待签收',
                'target' => 'order_list',
                'description' => '订单状态为待签收的订单',
            ],
            self::PENDING_CHECK => [
                'name' => '待质检',
                'target' => 'order_list',
                'description' => '已签收或质检中，尚未完成质检的订单',
            ],
            self::CHECK_TIMEOUT => [
                'name' => '质检超时',
                'target' => 'order_list',
                'description' => '签收超过阈值仍未完成质检的订单',
            ],
            self::PENDING_QUOTE => [
                'name' => '待报价',
                'target' => 'order_list',
                'description' => '已质检，等待定价/报价的订单',
            ],
            self::QUOTE_TIMEOUT => [
                'name' => '报价超时',
                'target' => 'order_list',
                'description' => '已质检超过阈值仍未进入待确认的订单',
            ],
            self::PENDING_CONFIRM => [
                'name' => '待用户确认',
                'target' => 'order_list',
                'description' => '已经报价，等待用户确认的订单',
            ],
            self::PENDING_PAY => [
                'name' => '待打款',
                'target' => 'order_list',
                'description' => '用户已确认价格，等待财务打款的订单',
            ],
            self::PAY_TIMEOUT => [
                'name' => '打款超时',
                'target' => 'order_list',
                'description' => '进入待打款超过阈值仍未完成打款的订单',
            ],
            self::PAID_TODAY => [
                'name' => '今日已打款',
                'target' => 'order_list',
                'description' => '今日已经打款的订单',
            ],
            self::COMPLETED_TODAY => [
                'name' => '今日已完成',
                'target' => 'order_list',
                'description' => '今日完成的订单',
            ],
            self::RETURNED_ORDERS => [
                'name' => '有退回设备的订单',
                'target' => 'order_list',
                'description' => '包含已退回设备的订单',
            ],
            self::PENDING_RETURN => [
                'name' => '待退回设备',
                'target' => 'order_list',
                'view_mode' => 'device_expand',
                'description' => '处置方式为退回且尚未完成退回的设备',
            ],
            self::INVENTORY_DEVICES => [
                'name' => '库存设备',
                'target' => 'order_list',
                'view_mode' => 'device_expand',
                'description' => '已回收、尚未接入销售出库闭环的设备',
            ],
            self::HIGH_COST_DEVICES => [
                'name' => '高成本设备',
                'target' => 'order_list',
                'view_mode' => 'device_expand',
                'description' => '最终回收价达到阈值的设备',
            ],
            self::QUOTE_DECIDED => [
                'name' => '已报价订单',
                'target' => 'order_list',
                'description' => '已经进入用户确认、待打款、已完成或已关闭的订单',
            ],
            self::QUOTE_CONFIRMED => [
                'name' => '报价已确认订单',
                'target' => 'order_list',
                'description' => '用户已确认并进入待打款或已完成的订单',
            ],
            self::RETURNED_DEVICES => [
                'name' => '已退回设备',
                'target' => 'order_list',
                'view_mode' => 'device_expand',
                'description' => '状态为已退回的设备',
            ],
        ];
    }

    public static function get(string $key): array
    {
        $list = self::getList();
        return $list[$key] ?? [];
    }
}
