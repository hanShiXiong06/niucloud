<?php
/**
 * 个人中心 DIY 默认组件（对齐历史 user/index 分组，避免校园服务与更多功能重复）
 */
$link = function ($name, $title, $url) {
    return ['name' => $name, 'title' => $title, 'url' => $url, 'parent' => 'SD_XIAOYUAN_LINK'];
};
$tpl = [
    'pageStartBgColor' => '',
    'pageEndBgColor' => '',
    'pageGradientAngle' => 'to bottom',
    'componentBgUrl' => '',
    'componentBgAlpha' => 2,
    'componentStartBgColor' => '#ffffff',
    'componentEndBgColor' => '#ffffff',
    'componentGradientAngle' => 'to bottom',
    'topRounded' => 12,
    'bottomRounded' => 12,
    'margin' => ['top' => 0, 'bottom' => 0, 'both' => 0],
];

return [
    array_merge([
        'path' => 'edit-xiaoyuan-user-header',
        'uses' => 1,
        'id' => 'xiaoyuan_user_header_1',
        'componentName' => 'XiaoyuanUserHeader',
        'componentTitle' => '个人中心头部',
        'bgStartColor' => '#c0fe95',
        'bgEndColor' => '#f7f7f7',
        'welcomeText' => '欢迎使用校园帮',
        'loginTip' => '点击登录',
        'showCredit' => true,
        'settingsUrl' => $link('SD_XIAOYUAN_SETTING', '设置', '/app/pages/setting/index'),
        'creditUrl' => $link('SD_XIAOYUAN_CREDIT_LOG', '信誉记录', '/addon/sd_xiaoyuan/pages/credit/log'),
    ], $tpl),
    array_merge([
        'path' => 'edit-xiaoyuan-user-menu',
        'uses' => 0,
        'id' => 'xiaoyuan_user_menu_1',
        'componentName' => 'XiaoyuanUserMenu',
        'componentTitle' => '校园服务',
        'title' => '校园服务',
        'column' => 5,
        'list' => [
            ['name' => '成为接单员', 'icon' => 'home', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #52c41a 0%, #73d13d 100%)', 'runnerSlot' => true, 'runnerNameOk' => '接单员主页', 'runnerNameApply' => '成为接单员', 'url' => '/addon/sd_xiaoyuan/pages/runner/apply', 'link' => $link('SD_XIAOYUAN_RUNNER_APPLY', '成为接单员', '/addon/sd_xiaoyuan/pages/runner/apply'), 'isShow' => true],
            ['name' => '我的订单', 'icon' => 'list', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #ff7243 0%, #ff9a44 100%)', 'url' => '/addon/sd_xiaoyuan/pages/order/list', 'link' => $link('SD_XIAOYUAN_ORDER_LIST', '我的订单', '/addon/sd_xiaoyuan/pages/order/list'), 'isShow' => true],
            ['name' => '我的地址', 'icon' => 'map', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)', 'url' => '/addon/sd_xiaoyuan/pages/address/list', 'link' => $link('SD_XIAOYUAN_ADDRESS_LIST', '我的地址', '/addon/sd_xiaoyuan/pages/address/list'), 'isShow' => true],
            ['name' => '我的闲置', 'icon' => 'shopping-cart', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #ff9a44 0%, #fc6076 100%)', 'featureKey' => 'enable_secondhand', 'url' => '/addon/sd_xiaoyuan/pages/secondhand/my', 'link' => $link('SD_XIAOYUAN_SECONDHAND_MY', '我的闲置', '/addon/sd_xiaoyuan/pages/secondhand/my'), 'isShow' => true],
            ['name' => '我的帖子', 'icon' => 'edit-pen', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'featureKey' => 'enable_community', 'url' => '/addon/sd_xiaoyuan/pages/community/my', 'link' => $link('SD_XIAOYUAN_COMMUNITY_MY', '我的帖子', '/addon/sd_xiaoyuan/pages/community/my'), 'isShow' => true],
            ['name' => '我的表白', 'icon' => 'heart-fill', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'featureKey' => 'enable_confession', 'url' => '/addon/sd_xiaoyuan/pages/confession/my', 'link' => $link('SD_XIAOYUAN_CONFESSION_MY', '我的表白', '/addon/sd_xiaoyuan/pages/confession/my'), 'isShow' => true],
            ['name' => '失物招领', 'icon' => 'search', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #00c853 0%, #69f0ae 100%)', 'featureKey' => 'enable_lost_found', 'url' => '/addon/sd_xiaoyuan/pages/lost_found/my', 'link' => $link('SD_XIAOYUAN_LOST_FOUND_MY', '失物招领', '/addon/sd_xiaoyuan/pages/lost_found/my'), 'isShow' => true],
            ['name' => '我的课表', 'icon' => 'calendar', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'featureKey' => 'enable_schedule', 'url' => '/addon/sd_xiaoyuan/pages/schedule/index', 'link' => $link('SD_XIAOYUAN_SCHEDULE_INDEX', '课程表', '/addon/sd_xiaoyuan/pages/schedule/index'), 'isShow' => true],
        ],
    ], $tpl, ['margin' => ['top' => 6, 'bottom' => 6, 'both' => 12]]),
    array_merge([
        'path' => 'edit-xiaoyuan-user-menu',
        'uses' => 0,
        'id' => 'xiaoyuan_user_menu_2',
        'componentName' => 'XiaoyuanUserMenu',
        'componentTitle' => '推广赚钱',
        'title' => '推广赚钱',
        'column' => 5,
        'list' => [
            ['name' => '邀请有礼', 'icon' => 'gift', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%)', 'featureKey' => 'open_fenxiao', 'url' => '/addon/sd_xiaoyuan/pages/invite/index', 'link' => $link('SD_XIAOYUAN_INVITE_INDEX', '邀请有礼', '/addon/sd_xiaoyuan/pages/invite/index'), 'isShow' => true],
            ['name' => '我的团队', 'icon' => 'account-fill', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #ff9500 0%, #ffb347 100%)', 'featureKey' => 'open_fenxiao', 'url' => '/addon/sd_xiaoyuan/pages/invite/team', 'link' => $link('SD_XIAOYUAN_INVITE_TEAM', '我的团队', '/addon/sd_xiaoyuan/pages/invite/team'), 'isShow' => true],
            ['name' => '我的收益', 'icon' => 'rmb-circle', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #ff9500 0%, #ffb347 100%)', 'featureKey' => 'open_fenxiao', 'url' => '/app/pages/member/commission', 'link' => $link('SD_XIAOYUAN_COMMISSION', '我的收益', '/app/pages/member/commission'), 'isShow' => true],
            ['name' => '佣金提现', 'icon' => 'red-packet', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 'featureKey' => 'open_fenxiao', 'url' => '/app/pages/member/apply_cash_out', 'link' => $link('SD_XIAOYUAN_CASH_OUT', '佣金提现', '/app/pages/member/apply_cash_out'), 'isShow' => true],
            ['name' => '提现记录', 'icon' => 'list', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #52c41a 0%, #73d13d 100%)', 'featureKey' => 'open_fenxiao', 'url' => '/app/pages/member/cash_out', 'link' => $link('SD_XIAOYUAN_CASH_OUT_LOG', '提现记录', '/app/pages/member/cash_out'), 'isShow' => true],
        ],
    ], $tpl, ['margin' => ['top' => 0, 'bottom' => 6, 'both' => 12]]),
    array_merge([
        'path' => 'edit-xiaoyuan-user-menu',
        'uses' => 0,
        'id' => 'xiaoyuan_user_menu_3',
        'componentName' => 'XiaoyuanUserMenu',
        'componentTitle' => '账户中心',
        'title' => '账户中心',
        'column' => 5,
        'list' => [
            ['name' => '每日签到', 'icon' => 'checkmark-circle', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 'featureKey' => 'enable_sign', 'url' => '/addon/sd_xiaoyuan/pages/sign/index', 'link' => $link('SD_XIAOYUAN_SIGN_INDEX', '每日签到', '/addon/sd_xiaoyuan/pages/sign/index'), 'isShow' => true],
            ['name' => '积分商城', 'icon' => 'gift', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%)', 'featureKey' => 'enable_points_mall', 'url' => '/addon/sd_xiaoyuan/pages/points/mall', 'link' => $link('SD_XIAOYUAN_POINTS_MALL', '积分商城', '/addon/sd_xiaoyuan/pages/points/mall'), 'isShow' => true],
            ['name' => '兑换记录', 'icon' => 'order', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 'featureKey' => 'enable_points_mall', 'url' => '/addon/sd_xiaoyuan/pages/points/orders', 'link' => $link('SD_XIAOYUAN_POINTS_ORDERS', '兑换记录', '/addon/sd_xiaoyuan/pages/points/orders'), 'isShow' => true],
            ['name' => '我的评价', 'icon' => 'star', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)', 'url' => '/addon/sd_xiaoyuan/pages/user/evaluates', 'link' => $link('SD_XIAOYUAN_USER_EVALUATES', '我的评价', '/addon/sd_xiaoyuan/pages/user/evaluates'), 'isShow' => true],
            ['name' => '校园认证', 'icon' => 'integral', 'iconColor' => '#fff', 'bgColor' => 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)', 'featureKey' => 'require_auth_publish', 'url' => '/addon/sd_xiaoyuan/pages/campus/auth', 'link' => $link('SD_XIAOYUAN_CAMPUS_AUTH', '校园认证', '/addon/sd_xiaoyuan/pages/campus/auth'), 'isShow' => true],
        ],
    ], $tpl, ['margin' => ['top' => 0, 'bottom' => 12, 'both' => 12]]),
];
