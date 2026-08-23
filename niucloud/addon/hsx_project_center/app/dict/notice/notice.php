<?php
return [
    'project_center_application_approved' => [
        'addon' => 'hsx_project_center', 'key' => 'project_center_application_approved',
        'receiver_type' => 1, 'name' => '项目资料审核通过通知',
        'title' => '客户提交的项目资料审核通过后发送', 'async' => true,
        'variable' => [
            'project_name' => '项目名称', 'group_no' => '群编号', 'review_result' => '审核结果',
            'review_reason' => '审核说明', 'review_time' => '审核时间', 'url' => '查看链接',
        ],
    ],
    'project_center_application_rejected' => [
        'addon' => 'hsx_project_center', 'key' => 'project_center_application_rejected',
        'receiver_type' => 1, 'name' => '项目资料退回修改通知',
        'title' => '客户提交的项目资料存在问题时发送', 'async' => true,
        'variable' => [
            'project_name' => '项目名称', 'group_no' => '群编号', 'review_result' => '审核结果',
            'review_reason' => '问题说明', 'review_time' => '审核时间', 'url' => '查看链接',
        ],
    ],
    'project_center_refund_completed' => [
        'addon' => 'hsx_project_center', 'key' => 'project_center_refund_completed',
        'receiver_type' => 1, 'name' => '项目退款完成通知',
        'title' => '线下款项退回客户并上传退款凭证后发送', 'async' => true,
        'variable' => [
            'project_name' => '项目名称', 'group_no' => '群编号', 'refund_amount' => '退款金额',
            'refund_result' => '退款结果', 'refund_reason' => '退款说明', 'refund_time' => '退款时间', 'url' => '查看链接',
        ],
    ],
];
