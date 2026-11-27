<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\marketing;

final class MarketingRewardProviders
{
    public function handle(array $payload): array
    {
        return [[
            'provider_key' => 'phone_shop', 'reward_type' => 'coupon', 'name' => '商城优惠券',
            'value_label' => '券数量', 'options' => [],
        ]];
    }
}
