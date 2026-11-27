<?php
declare(strict_types=1);
return ['listen' => [
    'PayType' => ['addon\\hsx_lead\\app\\listener\\pay\\VirtualPayType'],
    'WeappMessage' => ['addon\\hsx_lead\\app\\listener\\pay\\VirtualPayMessage'],
    'MemberAccount' => ['addon\hsx_lead\app\listener\member\AccountNoticeListener'],
    'MemberAdminEntitlementDisplay' => ['addon\hsx_lead\app\listener\member\MemberAdminEntitlementDisplay'],
    'GetPosterType' => ['addon\hsx_lead\app\listener\poster\LeadPosterType'],
    'GetPosterData' => ['addon\hsx_lead\app\listener\poster\LeadPosterData'],
    'BottomNavigation' => ['addon\hsx_lead\app\listener\BottomNavigationListener'],
    'HttpRun' => ['addon\hsx_lead\app\listener\pay\CooperationPaymentGuardRegistration'],
    'PayCreate' => ['addon\hsx_lead\app\listener\pay\CooperationPayCreate'],
    'PaySuccess' => ['addon\hsx_lead\app\listener\pay\CooperationPaySuccess'],
    'PayClose' => ['addon\hsx_lead\app\listener\pay\CooperationPayCloseGuard'],
    'RefundSuccess' => [
        'addon\hsx_lead\app\listener\pay\MembershipRefundSuccess',
        'addon\hsx_lead\app\listener\pay\CooperationOrderRefundSuccess',
        'addon\hsx_lead\app\listener\pay\PromotionRefundSuccess'
    ],
    'HsxLeadCollaborationChanged' => ['addon\hsx_lead\app\listener\notice\CooperationChanged'],
    'NoticeData' => ['addon\hsx_lead\app\listener\notice\CooperationNoticeData', 'addon\hsx_lead\app\listener\notice\AccountNoticeData'],
]];
