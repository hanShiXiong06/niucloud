<?php
declare(strict_types=1);
return [
    'hsx_lead_task_assigned'=>['is_need_closure_content'=>0,'content'=>'您有新的门店工单{task_no}，请登录商户工作台查看并处理。'],
    'hsx_lead_task_changed'=>['is_need_closure_content'=>0,'content'=>'门店工单{task_no}的处理状态已更新为{status_text}，请登录商户工作台核实。'],
]+\addon\hsx_lead\app\service\notice\NoticeEventCatalog::smsDefinitions();
