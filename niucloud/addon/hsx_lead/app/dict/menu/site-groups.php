<?php
/** Presentation-only directories. Existing page/action permission keys remain unchanged. */
return [
    'hsx_lead_group_overview' => ['name' => '总览', 'path' => 'hsx_lead/group/overview', 'icon' => 'nc-icon-gongzuotai', 'pages' => ['hsx_lead_cooperation_manage']],
    'hsx_lead_group_merchants' => ['name' => '商户与权益', 'path' => 'hsx_lead/group/merchants', 'icon' => 'nc-icon-huiyuanliebiao', 'pages' => ['hsx_lead_manage_merchants', 'hsx_lead_entity_manage', 'hsx_lead_manage_inventory']],
    'hsx_lead_group_supervision' => ['name' => '协作监管', 'path' => 'hsx_lead/group/supervision', 'icon' => 'nc-icon-dingdanliebiao', 'pages' => ['hsx_lead_cooperation_leads', 'hsx_lead_cooperation_partners', 'hsx_lead_cooperation_review']],
    'hsx_lead_group_finance' => ['name' => '财务结算', 'path' => 'hsx_lead/group/finance', 'icon' => 'nc-icon-jiesuanzhanghu1', 'pages' => ['hsx_lead_cooperation_finance', 'hsx_lead_deposit_manage']],
    'hsx_lead_group_promotion' => ['name' => '推广', 'path' => 'hsx_lead/group/promotion', 'icon' => 'nc-icon-fenxiangV6mm-1', 'pages' => ['hsx_lead_promotion_manage']],
    'hsx_lead_group_settings' => ['name' => '业务设置', 'path' => 'hsx_lead/group/settings', 'icon' => 'nc-icon-shezhiV6xx', 'pages' => ['hsx_lead_cooperation_config', 'hsx_lead_notice_center_read', 'hsx_lead_cooperation_home', 'hsx_lead_cooperation_profile_home']],
];
