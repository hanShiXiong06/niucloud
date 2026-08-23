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
];
