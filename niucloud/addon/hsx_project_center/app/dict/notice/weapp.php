<?php
$make = static fn(string $scene): array => [
    'tid' => '',
    'content' => [
        ['审核结果', '{review_result}', 'thing1'], ['审核时间', '{review_time}', 'date2'],
        ['项目名称', '{project_name}', 'thing3'], ['备注', '{review_reason}', 'thing4'],
    ],
    'kid_list' => [1, 2, 3, 4], 'scene_desc' => $scene,
    'tips' => '请在微信小程序后台申请资料审核结果类订阅消息，并在牛云通知设置中配置模板。',
];
return [
    'project_center_application_approved' => $make('项目资料审核通过'),
    'project_center_application_rejected' => $make('项目资料退回修改'),
    'project_center_refund_completed' => [
        'tid' => '',
        'content' => [
            ['退款结果', '{refund_result}', 'phrase1'], ['退款金额', '{refund_amount}', 'amount2'],
            ['项目名称', '{project_name}', 'thing3'], ['退款时间', '{refund_time}', 'date4'],
        ],
        'kid_list' => [1, 2, 3, 4], 'scene_desc' => '项目退款完成',
        'tips' => '请在微信小程序后台申请退款结果类订阅消息，并在牛云通知设置中配置模板。',
    ],
    'project_center_distribution_settled' => [
        'tid' => '',
        'content' => [
            ['项目名称', '{project_name}', 'thing1'], ['佣金级别', '{relation_level}', 'phrase2'],
            ['到账金额', '{commission_amount}', 'amount3'], ['到账时间', '{change_time}', 'date4'],
        ],
        'kid_list' => [1, 2, 3, 4], 'scene_desc' => '项目推广佣金到账',
        'tips' => '请在微信小程序后台申请佣金到账类订阅消息，并在牛云通知设置中配置模板。',
    ],
    'project_center_distribution_pending' => [
        'tid' => '',
        'content' => [
            ['项目名称', '{project_name}', 'thing1'], ['佣金级别', '{relation_level}', 'phrase2'],
            ['预计佣金', '{commission_amount}', 'amount3'], ['生成时间', '{change_time}', 'date4'],
        ],
        'kid_list' => [1, 2, 3, 4], 'scene_desc' => '项目推广佣金待结算',
        'tips' => '请在微信小程序后台申请佣金待结算类订阅消息，并在牛云通知设置中配置模板。',
    ],
    'project_center_distribution_reversed' => [
        'tid' => '',
        'content' => [
            ['项目名称', '{project_name}', 'thing1'], ['变动金额', '{commission_amount}', 'amount2'],
            ['变动说明', '{status_text}', 'thing3'], ['变动时间', '{change_time}', 'date4'],
        ],
        'kid_list' => [1, 2, 3, 4], 'scene_desc' => '项目退款佣金冲红',
        'tips' => '请在微信小程序后台申请佣金变动类订阅消息，并在牛云通知设置中配置模板。',
    ],
];
