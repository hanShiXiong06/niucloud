<?php

return [
    'hsx_member_card_redeem_success' => [
        'addon' => 'hsx_member_card',
        'key' => 'hsx_member_card_redeem_success',
        'receiver_type' => 1,
        'name' => '会员卡核销成功通知',
        'title' => '会员权益核销成功后通知用户剩余次数',
        'async' => true,
        'variable' => [
            'card_name' => '会员卡名称',
            'item_name' => '核销权益',
            'remaining_times' => '剩余次数',
            'remaining_text' => '剩余权益',
            'operation_result' => '操作结果',
            'operation_time' => '核销时间',
            'operator_name' => '操作人',
            'url' => '会员卡详情链接',
        ],
    ],
    'hsx_member_card_redeem_reversed' => [
        'addon' => 'hsx_member_card',
        'key' => 'hsx_member_card_redeem_reversed',
        'receiver_type' => 1,
        'name' => '会员卡核销撤销通知',
        'title' => '核销撤销并恢复次数后通知用户',
        'async' => true,
        'variable' => [
            'card_name' => '会员卡名称',
            'item_name' => '撤销权益',
            'remaining_times' => '剩余次数',
            'remaining_text' => '剩余权益',
            'operation_result' => '操作结果',
            'operation_time' => '撤销时间',
            'operator_name' => '操作人',
            'url' => '会员卡详情链接',
        ],
    ],
];
