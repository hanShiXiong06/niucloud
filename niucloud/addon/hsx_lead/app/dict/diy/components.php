<?php
// Component-specific presentation JSON. Outer backgrounds/margins/corners are
// the framework template below; no duplicate page renderer or business state.
$appearance = ['showTitle' => 1, 'subtitle' => '', 'primaryColor' => '', 'panelColor' => '', 'titleColor' => '#202124', 'textColor' => '#606266',
    'subtleColor' => '#909399', 'iconColor' => '#333333', 'borderColor' => '#e4e8ef', 'buttonColor' => '', 'buttonTextColor' => '#ffffff',
    'titleSize' => 18, 'textSize' => 14, 'contentPadding' => 16, 'rowGap' => 12, 'itemRadius' => 8, 'buttonRadius' => 6, 'entryImage' => ''];
$frame = ['pageStartBgColor' => '', 'pageEndBgColor' => '', 'pageGradientAngle' => 'to bottom', 'componentStartBgColor' => '#ffffff', 'componentEndBgColor' => '',
    'componentGradientAngle' => 'to bottom', 'componentBgUrl' => '', 'componentBgAlpha' => 0, 'topRounded' => 0, 'bottomRounded' => 0,
    'margin' => ['top' => 0, 'bottom' => 8, 'both' => 0]];
$groups = ['HSX_LEAD_COMPONENT' => ['title' => '商户协作', 'list' => [
    'LeadCooperationEntry' => [
        'title' => '角色主入口', 'icon' => 'nc-iconfont nc-icon-yingyongzhongxinV6xx', 'path' => 'edit-lead-cooperation-entry',
        'support_page' => ['DIY_HSX_LEAD_INDEX'], 'uses' => 1, 'sort' => 10210,
        'value' => array_replace($appearance, ['title' => '商户协作', 'showTitle' => 0, 'layoutPreset' => 'classic', 'storeTitle' => '找门店', 'storeSubtitle' => '发布客户需求，找门店交付', 'storeActionText' => '去找门店',
            'customerTitle' => '找客户', 'customerSubtitle' => '查看交付给我的客户', 'customerActionText' => '查看客户', 'businessTitle' => '服务业务', 'profileText' => '我的门店', 'walletText' => '协作收益', 'showTools' => 0, 'showBusinesses' => 0, 'animate' => 1, 'usePresetImages' => 1, 'itemRadius' => 12, 'storeImage' => '', 'customerImage' => '']),
        'template' => $frame,
    ],
    'LeadBusinessNavigation' => [
        'title' => '业务快捷入口', 'icon' => 'nc-iconfont nc-icon-yingyongzhongxinV6xx', 'path' => 'edit-lead-business-navigation',
        'support_page' => ['DIY_HSX_LEAD_INDEX'], 'uses' => 1, 'sort' => 10211,
        'value' => array_replace($appearance, ['title' => '服务业务', 'showTitle' => 1, 'layoutPreset' => 'compact', 'itemRadius' => 12, 'columns' => 4, 'iconSize' => 40, 'itemWidth' => 88, 'iconColor' => '', 'items' => (object) []]), 'template' => $frame,
    ],
    'LeadCooperationHero' => [
        'title' => '首页标题区', 'icon' => 'nc-iconfont nc-icon-biaotiV6xx', 'path' => 'edit-lead-cooperation-hero',
        'support_page' => ['DIY_HSX_LEAD_INDEX'], 'uses' => 1, 'sort' => 10208,
        'value' => array_replace($appearance, ['caption' => '同城协作 · 各有所长', 'title' => '把客户交给合适的门店', 'subtitle' => '', 'showAccent' => 1, 'accentColor' => '#398aff', 'titleColor' => '#243b5b', 'textColor' => '#718199', 'titleSize' => 22, 'contentPadding' => 20]),
        'template' => array_replace($frame, ['componentStartBgColor' => '', 'margin' => ['top' => 0, 'bottom' => 0, 'both' => 0]]),
    ],
    'LeadRoleEntry' => [
        'title' => '独立角色卡片', 'icon' => 'nc-iconfont nc-icon-yingyongzhongxinV6xx', 'path' => 'edit-lead-role-entry',
        'support_page' => ['DIY_HSX_LEAD_INDEX'], 'uses' => 0, 'sort' => 10209,
        'value' => array_replace($appearance, ['role' => 'stores', 'title' => '找门店', 'subtitle' => '发布客户需求，选门店交付', 'buttonText' => '去找门店', 'buttonStyle' => 'solid', 'image' => '', 'usePresetImages' => 1, 'showImage' => 1, 'showBorder' => 1, 'animate' => 1, 'layoutPreset' => 'classic', 'primaryColor' => '#398aff', 'titleColor' => '#243b5b', 'textColor' => '#718199', 'subtleColor' => '#94a3b5', 'iconColor' => '#536e9d', 'borderColor' => '#cddff7', 'buttonColor' => '#398aff', 'buttonTextColor' => '#ffffff', 'titleSize' => 19, 'itemRadius' => 10, 'buttonRadius' => 22]),
        'template' => array_replace($frame, ['componentStartBgColor' => '#ffffff', 'componentEndBgColor' => '#e2efff', 'componentGradientAngle' => '120deg', 'topRounded' => 10, 'bottomRounded' => 10, 'margin' => ['top' => 0, 'bottom' => 10, 'both' => 12]]),
    ],
]]];
$sections = [
    'LeadProfileIdentity' => ['title' => '门店身份', 'path' => 'edit-lead-profile-identity', 'value' => ['title' => '我的门店', 'showTitle' => 0, 'identityStyle' => 'store', 'showRegion' => 1, 'showGrade' => 1, 'showMemberNo' => 1, 'showBalance' => 1, 'showSettings' => 1, 'showStats' => 1, 'buttonText' => '门店资料', 'secondaryText' => '门店成员', 'outgoingText' => '找门店', 'incomingText' => '找客户']],
    'LeadProfileTools' => ['title' => '消息与偏好', 'path' => 'edit-lead-profile-tools', 'value' => ['title' => '消息与偏好', 'showNotice' => 1, 'showFeedback' => 1, 'noticeText' => '工单通知', 'feedbackText' => '触感反馈',
        'showProfile' => 0, 'showCapability' => 0, 'showInventory' => 0, 'showConnection' => 0, 'showMembers' => 0, 'profileText' => '门店资料', 'capabilityText' => '交付能力', 'inventoryText' => '我的货盘', 'connectionText' => 'ERP连接', 'membersText' => '门店成员',
        'profileImage' => '', 'capabilityImage' => '', 'inventoryImage' => '', 'connectionImage' => '', 'membersImage' => '', 'noticeImage' => '']],
    'LeadProfileBenefits' => ['title' => '会员权益', 'path' => 'edit-lead-profile-benefits', 'value' => ['title' => '会员权益', 'showMembership' => 1, 'buttonText' => '查看会员权益', 'secondaryText' => '']],
    'LeadProfilePromotion' => ['title' => '推广入口', 'path' => 'edit-lead-profile-promotion', 'value' => ['title' => '推广合作', 'showInviter' => 1, 'buttonText' => '推广中心', 'secondaryText' => '服务商申请']],
];
$sort = 10220;
foreach ($sections as $name => $section)$groups['HSX_LEAD_COMPONENT']['list'][$name] = [
    'title' => $section['title'], 'icon' => 'nc-iconfont nc-icon-gerenzhongxinV6xx', 'path' => $section['path'],
    'support_page' => ['DIY_HSX_LEAD_MEMBER_INDEX'], 'uses' => 1, 'sort' => $sort++,
    'value' => array_replace($appearance, $section['value']), 'template' => $frame,
];
return $groups;
