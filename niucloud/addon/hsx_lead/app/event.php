<?php
declare(strict_types=1);
return ['listen'=>[
    'GetPosterType'=>['addon\hsx_lead\app\listener\poster\LeadPosterType'],
    'GetPosterData'=>['addon\hsx_lead\app\listener\poster\LeadPosterData'],
    'BottomNavigation'=>['addon\hsx_lead\app\listener\BottomNavigationListener'],
    'HttpRun'=>['addon\hsx_lead\app\listener\pay\CooperationPaymentGuardRegistration'],
    'PayCreate'=>['addon\hsx_lead\app\listener\pay\CooperationPayCreate'],
    'PaySuccess'=>['addon\hsx_lead\app\listener\pay\CooperationPaySuccess'],
    'RefundSuccess'=>['addon\hsx_lead\app\listener\pay\MembershipRefundSuccess','addon\hsx_lead\app\listener\pay\PromotionRefundSuccess'],
    'HsxLeadCollaborationChanged'=>['addon\hsx_lead\app\listener\notice\CooperationChanged'],
    'NoticeData'=>['addon\hsx_lead\app\listener\notice\CooperationNoticeData'],
]];

