<?php
$make = static fn(string $tips): array => [
    'temp_key' => '',
    'content' => [
        ['项目名称', '{project_name}', 'thing1'], ['审核结果', '{review_result}', 'phrase2'],
        ['问题说明', '{review_reason}', 'thing3'], ['审核时间', '{review_time}', 'time4'],
    ],
    'keyword_name_list' => ['项目名称', '审核结果', '问题说明', '审核时间'],
    'tips' => $tips,
];
return [
    'project_center_application_approved' => $make('请在微信公众号申请资料审核结果类模板，并在牛云通知设置中填写模板 ID。'),
    'project_center_application_rejected' => $make('请在微信公众号申请资料审核结果类模板，并在牛云通知设置中填写模板 ID。'),
    'project_center_refund_completed' => [
        'temp_key' => '',
        'content' => [
            ['项目名称', '{project_name}', 'thing1'], ['退款结果', '{refund_result}', 'phrase2'],
            ['退款金额', '{refund_amount}', 'amount3'], ['退款时间', '{refund_time}', 'time4'],
        ],
        'keyword_name_list' => ['项目名称', '退款结果', '退款金额', '退款时间'],
        'tips' => '请在微信公众号申请退款结果类模板，并在牛云通知设置中填写模板 ID。',
    ],
];
