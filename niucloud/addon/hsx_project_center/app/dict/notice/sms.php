<?php
return [
    'project_center_application_approved' => [
        'is_need_closure_content' => 0,
        'content' => '您提交的{project_name}资料已审核通过，群编号{group_no}，请留意群内后续安排。',
    ],
    'project_center_application_rejected' => [
        'is_need_closure_content' => 0,
        'content' => '您提交的{project_name}资料需修改：{review_reason}。请重新扫码查看后提交。',
    ],
    'project_center_refund_completed' => [
        'is_need_closure_content' => 0,
        'content' => '您参与的{project_name}已完成退款，群编号{group_no}，退款金额{refund_amount}元，请留意到账信息。',
    ],
    'project_center_distribution_settled' => [
        'is_need_closure_content' => 0,
        'content' => '您推广的{project_name}{relation_level}佣金已到账{commission_amount}元，请进入项目推广中心查看。',
    ],
    'project_center_distribution_pending' => [
        'is_need_closure_content' => 0,
        'content' => '您推广的{project_name}{relation_level}预计佣金{commission_amount}元已生成，保护期结束后结算。',
    ],
    'project_center_distribution_reversed' => [
        'is_need_closure_content' => 0,
        'content' => '因参与客户退款，您在{project_name}的{relation_level}佣金变动{commission_amount}元，{status_text}。',
    ],
];
