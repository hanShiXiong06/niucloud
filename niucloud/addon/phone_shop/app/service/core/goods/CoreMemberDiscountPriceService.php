<?php

namespace addon\phone_shop\app\service\core\goods;

/**
 * 会员等级折扣价格计算器。
 *
 * 会员折扣的展示价、收银价与订单成交价必须共用同一口径，避免出现
 * “商品页显示一个价格、结算时又是另一个价格”的情况。
 */
class CoreMemberDiscountPriceService
{
    /**
     * @param float|int|string $original_price 商品单件原价
     * @param array $benefit 会员等级 level_benefits.discount 配置
     * @return string 计算后的单件会员价
     */
    public static function calculate($original_price, array $benefit): string
    {
        $original_price = max(0, round((float) $original_price, 2));
        $discount = max(0, min(10, (float) ($benefit['discount'] ?? 10)));
        $member_price = round($original_price * $discount / 10, 2);

        // 0 或未配置表示不封顶，兼容已有会员等级数据。
        $max_discount_money = max(0, round((float) ($benefit['max_discount_money'] ?? 0), 2));
        if ($max_discount_money > 0) {
            $discount_money = max(0, round($original_price - $member_price, 2));
            if ($discount_money > $max_discount_money) {
                $member_price = max(0, round($original_price - $max_discount_money, 2));
            }
        }

        return number_format($member_price, 2, '.', '');
    }
}
