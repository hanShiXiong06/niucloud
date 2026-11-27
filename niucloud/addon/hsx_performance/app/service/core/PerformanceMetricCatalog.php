<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

final class PerformanceMetricCatalog
{
    private const DEFINITIONS = [
        'member_card_issued' => ['name' => '会员卡开卡', 'scope' => 'outcome', 'unit' => 'card'],
        'member_card_redeemed' => ['name' => '会员卡核销服务', 'scope' => 'action', 'unit' => 'service'],
        'member_card_received' => ['name' => '会员卡确认收款', 'scope' => 'action', 'unit' => 'settlement'],
        'member_card_refund_applied' => ['name' => '会员卡退款申请', 'scope' => 'action', 'unit' => 'refund'],
        'member_card_refunded' => ['name' => '会员卡退款完成', 'scope' => 'outcome', 'unit' => 'refund'],
        'member_card_refund_paid' => ['name' => '会员卡退款付款', 'scope' => 'action', 'unit' => 'settlement'],
        'recycle.device.signed' => ['name' => '设备签收', 'scope' => 'action', 'unit' => 'device'],
        'recycle.check.completed' => ['name' => '完成质检', 'scope' => 'action', 'unit' => 'device'],
        'recycle.price.completed' => ['name' => '完成定价', 'scope' => 'action', 'unit' => 'device'],
        'recycle.inbound.completed' => ['name' => '回收入库成功', 'scope' => 'outcome', 'unit' => 'device'],
        'recycle.device.returned' => ['name' => '回收设备退回', 'scope' => 'quality', 'unit' => 'device'],
        'erp.purchase.inbound' => ['name' => '采购入库', 'scope' => 'outcome', 'unit' => 'device'],
        'erp.asset.photo.completed' => ['name' => '设备拍照完成', 'scope' => 'action', 'unit' => 'device'],
        'erp.asset.price.completed' => ['name' => '商城销售定价', 'scope' => 'action', 'unit' => 'device'],
        'erp.asset.material.completed' => ['name' => '商城资料完善', 'scope' => 'action', 'unit' => 'device'],
        'erp.asset.listed' => ['name' => '设备成功上架', 'scope' => 'outcome', 'unit' => 'device'],
        'erp.asset.transferred' => ['name' => '库存调拨', 'scope' => 'action', 'unit' => 'device'],
        'erp.sale.created' => ['name' => '销售开单', 'scope' => 'outcome', 'unit' => 'order'],
        'erp.sale.outbound' => ['name' => '销售出库', 'scope' => 'outcome', 'unit' => 'device'],
        'erp.finance.receipt.confirmed' => ['name' => '确认收款', 'scope' => 'action', 'unit' => 'settlement'],
        'erp.finance.payment.confirmed' => ['name' => '确认付款', 'scope' => 'action', 'unit' => 'settlement'],
        'erp.finance.offset.confirmed' => ['name' => '确认折账', 'scope' => 'action', 'unit' => 'settlement'],
    ];

    private const ALIASES = [
        'member_card_issue_cancelled' => 'member_card_issued',
        'member_card_redeem_reversed' => 'member_card_redeemed',
    ];

    public static function normalizeKey(string $key): string
    {
        return self::ALIASES[$key] ?? $key;
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::DEFINITIONS[self::normalizeKey($key)]);
    }

    public static function definition(string $key): array
    {
        $key = self::normalizeKey($key);
        return self::DEFINITIONS[$key] ?? [
            'name' => $key,
            'scope' => 'action',
            'unit' => 'item',
        ];
    }
}
