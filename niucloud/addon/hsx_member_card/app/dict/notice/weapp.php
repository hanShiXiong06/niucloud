<?php

return [
    'hsx_member_card_redeem_success' => [
        'tid' => '',
        'content' => [
            ['会员卡名称', '{card_name}', 'thing1'],
            ['核销项目', '{item_name}', 'thing2'],
            ['剩余次数', '{remaining_text}', 'thing3'],
            ['核销结果', '{operation_result}', 'thing4'],
            ['核销时间', '{operation_time}', 'date5'],
        ],
        'kid_list' => [1, 2, 3, 4, 5],
        'scene_desc' => '会员卡核销结果通知',
        'tips' => '请在微信公众平台选择消费/核销结果通知模板，并在牛云通知设置中配置模板 ID 及对应关键词。',
    ],
    'hsx_member_card_redeem_reversed' => [
        'tid' => '',
        'content' => [
            ['会员卡名称', '{card_name}', 'thing1'],
            ['撤销项目', '{item_name}', 'thing2'],
            ['剩余次数', '{remaining_text}', 'thing3'],
            ['操作结果', '{operation_result}', 'thing4'],
            ['操作时间', '{operation_time}', 'date5'],
        ],
        'kid_list' => [1, 2, 3, 4, 5],
        'scene_desc' => '会员卡核销撤销通知',
        'tips' => '请在微信公众平台选择消费/核销结果通知模板，并在牛云通知设置中配置模板 ID 及对应关键词。',
    ],
];
