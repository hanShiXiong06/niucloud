<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\order;

/**
 * 小程序线上成交计价器。
 *
 * 这里只负责由“基础成交价 + 购买身份 + 支付配置”生成不可变金额快照，
 * 不读取订单、会员、ERP或支付表，后续接入其他自营渠道时可以复用同一口径。
 */
class CoreOnlineTradePricingService
{
    public function calculate(float $baseAmount, string $identity, array $config): array
    {
        $base = max(0, round($baseAmount, 2));
        $identity = $identity === 'peer' ? 'peer' : 'retail';
        $rate = $identity === 'peer'
            ? max(0, min((float)($config['peer_fee_rate'] ?? 0), 0.2))
            : 0.0;
        $bearer = $identity === 'peer' && (string)($config['peer_fee_bearer'] ?? '') === 'customer'
            ? 'customer'
            : 'merchant';
        $fee = 0.0;
        $payable = $base;
        $merchantNet = $base;

        if ($identity === 'peer' && $rate > 0) {
            if ($bearer === 'customer') {
                // 净额反推并向上取分，确保支付渠道扣费后不低于ERP同行销售价。
                $fee = ceil((($base / (1 - $rate)) - $base) * 100) / 100;
                $payable = round($base + $fee, 2);
                $merchantNet = round($payable * (1 - $rate), 2);
            } else {
                $fee = round($base * $rate, 2);
                $merchantNet = max(0, round($base - $fee, 2));
            }
        }

        return [
            'base_order_money' => number_format($base, 2, '.', ''),
            'pricing_identity' => $identity,
            'payment_fee_rate' => number_format($rate, 6, '.', ''),
            'payment_fee_bearer' => $bearer,
            'payment_fee_amount' => number_format($fee, 2, '.', ''),
            'merchant_net_amount' => number_format($merchantNet, 2, '.', ''),
            'order_money' => number_format($payable, 2, '.', ''),
        ];
    }
}
