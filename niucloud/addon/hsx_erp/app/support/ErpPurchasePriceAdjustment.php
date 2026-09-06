<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use addon\hsx_erp\app\dict\ErpDict;
use core\exception\CommonException;

/** 当前应付余额可随真实议价变化，历史结算和资金流水不可覆盖。 */
final class ErpPurchasePriceAdjustment
{
    public static function calculate(float $amount, float $settled, float $delta): array
    {
        foreach ([$amount, $settled, $delta] as $value) {
            if (!is_finite($value) || abs($value) > 999999999.99) throw new CommonException('调价金额无效');
        }
        $total = (int)round($amount * 100);
        $paid = (int)round($settled * 100);
        $change = (int)round($delta * 100);
        if ($total <= 0 || $paid < 0 || $paid > $total) throw new CommonException('原采购应付与已结算金额不一致，请先核账');
        if ($change === 0) throw new CommonException('调整金额不能为0');
        $after = $total + $change;
        if ($after > 99999999999) throw new CommonException('调整后采购价超出支持范围');
        if ($after <= 0) throw new CommonException('调整后采购价必须大于0');
        if ($after < $paid) {
            throw new CommonException(sprintf('调价后需向供货方收回 %.2f 元，请先处理采购退差款；不能把已付款直接抹掉或记为已退款', ($paid - $after) / 100));
        }
        return ['amount' => $after / 100, 'settled_amount' => $paid / 100,
            'remaining_amount' => ($after - $paid) / 100,
            'status' => ErpDict::financeStatus($after / 100, $paid / 100)];
    }
}
