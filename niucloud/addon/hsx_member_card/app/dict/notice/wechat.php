<?php

return [
    'hsx_member_card_redeem_success' => [
        'temp_key' => '',
        'content' => [
            ['会员卡', '{card_name}', 'thing1'],
            ['核销项目', '{item_name}', 'thing2'],
            ['剩余次数', '{remaining_text}', 'thing3'],
            ['核销结果', '{operation_result}', 'thing4'],
            ['核销时间', '{operation_time}', 'time5'],
        ],
        'keyword_name_list' => ['会员卡', '核销项目', '剩余次数', '核销结果', '核销时间'],
        'tips' => '公众号模板消息需在微信公众平台申请消费/核销结果类模板，并在通知配置中填写模板 ID。',
    ],
    'hsx_member_card_redeem_reversed' => [
        'temp_key' => '',
        'content' => [
            ['会员卡', '{card_name}', 'thing1'],
            ['撤销项目', '{item_name}', 'thing2'],
            ['剩余次数', '{remaining_text}', 'thing3'],
            ['操作结果', '{operation_result}', 'thing4'],
            ['操作时间', '{operation_time}', 'time5'],
        ],
        'keyword_name_list' => ['会员卡', '撤销项目', '剩余次数', '操作结果', '操作时间'],
        'tips' => '公众号模板消息需在微信公众平台申请消费/核销结果类模板，并在通知配置中填写模板 ID。',
    ],
];
