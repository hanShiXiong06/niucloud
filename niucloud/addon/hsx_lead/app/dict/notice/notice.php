<?php
declare(strict_types=1);
$variables=[
    'task_no'=>'工单编号','status_text'=>'处理状态','notice_remark'=>'操作提示',
    'occurred_at'=>'通知时间','demand_summary'=>'需求提示（无客户资料）','task_id'=>'工单数字ID',
];
return [
    'hsx_lead_task_assigned'=>['addon'=>'hsx_lead','key'=>'hsx_lead_task_assigned','receiver_type'=>1,
        'name'=>'商户新工单提醒','title'=>'工单成功派发后提醒绑定会员到商户工作台处理','async'=>false,'variable'=>$variables],
    'hsx_lead_task_changed'=>['addon'=>'hsx_lead','key'=>'hsx_lead_task_changed','receiver_type'=>1,
        'name'=>'商户工单处理结果提醒','title'=>'平台核验、退回跟进、关闭或撤销后提醒绑定会员','async'=>false,'variable'=>$variables],
];

