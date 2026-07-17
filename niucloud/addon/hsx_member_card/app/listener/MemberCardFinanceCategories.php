<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\listener;

final class MemberCardFinanceCategories
{
    public function handle(): array
    {
        return [
            [
                'key' => 'hsx_member_card.card_sale',
                'name' => '会员卡预收款',
                'direction' => 'income',
                'scope' => 'member_card_sale',
                'statement_group' => 'advance_receipt',
                'affects_asset_cost' => 0,
                'creates_finance' => 1,
                'party_required' => 1,
                'source_plugin' => 'hsx_member_card',
                'source_key' => 'card_sale',
                'enabled' => 1,
                'sort' => 120,
            ],
            [
                'key' => 'hsx_member_card.card_refund',
                'name' => '会员卡退款',
                'direction' => 'expense',
                'scope' => 'member_card_refund',
                'statement_group' => 'advance_receipt_reversal',
                'affects_asset_cost' => 0,
                'creates_finance' => 1,
                'party_required' => 1,
                'source_plugin' => 'hsx_member_card',
                'source_key' => 'card_refund',
                'enabled' => 1,
                'sort' => 119,
            ],
        ];
    }
}
