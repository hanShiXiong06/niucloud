<?php
declare(strict_types=1);
return ['listen'=>[
    'NoticeData'=>['addon\hsx_lead\app\listener\notice\TaskNoticeData'],
    'HsxLeadTaskAssigned'=>['addon\hsx_lead\app\listener\notice\TaskEventNotice'],
    'HsxLeadTaskChanged'=>['addon\hsx_lead\app\listener\notice\TaskEventNotice'],
]];

