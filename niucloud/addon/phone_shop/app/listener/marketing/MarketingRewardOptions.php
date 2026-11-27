<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\marketing;

use addon\phone_shop\app\dict\coupon\CouponDict;
use addon\phone_shop\app\model\coupon\Coupon;

final class MarketingRewardOptions
{
    public function handle(array $payload): array
    {
        if (($payload['provider_key'] ?? '') !== 'phone_shop' || ($payload['reward_type'] ?? '') !== 'coupon') return ['handled' => false];
        $rows = Coupon::where([['site_id', '=', (int)($payload['site_id'] ?? 0)], ['status', '=', CouponDict::NORMAL]])
            ->field('id,title,type,price,min_condition_money,valid_type,length,valid_end_time')
            ->order('id desc')->select()->toArray();
        return ['handled' => true, 'options' => array_map(static fn(array $row): array => [
            'id' => (int)$row['id'], 'name' => (string)$row['title'],
            'description' => '面额 ¥' . (string)$row['price'] . '，满 ¥' . (string)$row['min_condition_money'] . ' 可用',
            'raw' => $row,
        ], $rows)];
    }
}
