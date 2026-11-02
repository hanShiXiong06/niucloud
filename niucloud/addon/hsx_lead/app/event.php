<?php
declare(strict_types=1);
return ['listen'=>[
    'HttpRun'=>['addon\hsx_lead\app\listener\pay\CooperationPaymentGuardRegistration'],
    'PayCreate'=>['addon\hsx_lead\app\listener\pay\CooperationPayCreate'],
    'PaySuccess'=>['addon\hsx_lead\app\listener\pay\CooperationPaySuccess'],
    'HsxLeadCollaborationChanged'=>['addon\hsx_lead\app\listener\notice\CooperationChanged'],
    'NoticeData'=>['addon\hsx_lead\app\listener\notice\TaskNoticeData'],
    'HsxLeadTaskAssigned'=>['addon\hsx_lead\app\listener\notice\TaskEventNotice'],
    'HsxLeadTaskChanged'=>['addon\hsx_lead\app\listener\notice\TaskEventNotice'],
]];

