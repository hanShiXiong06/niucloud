<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\listener;

final class MemberCardBusinessSources
{
    public function handle(): array
    {
        return [
            [
                'key' => 'hsx_member_card.card_order',
                'name' => '会员卡开卡',
                'direction' => 'income',
                'scene' => 'member_card',
                'source_plugin' => 'hsx_member_card',
                'source_key' => 'card_order',
                'enabled' => 1,
                'sort' => 120,
            ],
            [
                'key' => 'hsx_member_card.card_refund',
                'name' => '会员卡退款',
                'direction' => 'expense',
                'scene' => 'member_card',
                'source_plugin' => 'hsx_member_card',
                'source_key' => 'card_refund',
                'enabled' => 1,
                'sort' => 119,
            ],
        ];
    }
}
