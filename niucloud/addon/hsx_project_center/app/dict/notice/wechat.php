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
    'project_center_distribution_settled' => [
        'temp_key' => '',
        'content' => [
            ['项目名称', '{project_name}', 'thing1'], ['佣金级别', '{relation_level}', 'phrase2'],
            ['到账金额', '{commission_amount}', 'amount3'], ['到账时间', '{change_time}', 'time4'],
        ],
        'keyword_name_list' => ['项目名称', '佣金级别', '到账金额', '到账时间'],
        'tips' => '请在微信公众号申请佣金到账类模板，并在牛云通知设置中填写模板 ID。',
    ],
    'project_center_distribution_pending' => [
        'temp_key' => '',
        'content' => [
            ['项目名称', '{project_name}', 'thing1'], ['佣金级别', '{relation_level}', 'phrase2'],
            ['预计佣金', '{commission_amount}', 'amount3'], ['生成时间', '{change_time}', 'time4'],
        ],
        'keyword_name_list' => ['项目名称', '佣金级别', '预计佣金', '生成时间'],
        'tips' => '请在微信公众号申请佣金待结算类模板，并在牛云通知设置中填写模板 ID。',
    ],
    'project_center_distribution_reversed' => [
        'temp_key' => '',
        'content' => [
            ['项目名称', '{project_name}', 'thing1'], ['变动金额', '{commission_amount}', 'amount2'],
            ['变动说明', '{status_text}', 'thing3'], ['变动时间', '{change_time}', 'time4'],
        ],
        'keyword_name_list' => ['项目名称', '变动金额', '变动说明', '变动时间'],
        'tips' => '请在微信公众号申请佣金变动类模板，并在牛云通知设置中填写模板 ID。',
    ],
];
