<?php

$xy_link = function (string $url, string $title = '', string $name = '') {
    return [
        'name' => $name,
        'parent' => 'SD_XIAOYUAN_LINK',
        'title' => $title,
        'url' => $url,
    ];
};

$xy_menu = function (string $name, string $icon, string $iconColor, string $bgColor, string $url, string $linkName = '') use ($xy_link) {
    return [
        'name' => $name,
        'icon' => $icon,
        'iconColor' => $iconColor,
        'bgColor' => $bgColor,
        'imageUrl' => '',
        'url' => $url,
        'link' => $xy_link($url, $name, $linkName),
        'isShow' => true,
    ];
};

$xy_banner = function (string $title, string $desc, string $icon, string $bgColor, string $url, string $linkName = '') use ($xy_link) {
    return [
        'title' => $title,
        'desc' => $desc,
        'icon' => $icon,
        'imageUrl' => '',
        'bgColor' => $bgColor,
        'url' => $url,
        'link' => $xy_link($url, $title, $linkName),
        'isShow' => true,
    ];
};

return [
    'SD_XIAOYUAN_COMPONENT' => [
        'title' => '校园帮组件',
        'list' => [
            'XiaoyuanHeader' => [
                'title' => '顶部导航',
                'icon' => 'iconfont icona-tupianzhanbopc302',
                'path' => 'edit-xiaoyuan-header',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10000,
                'value' => [
                    'bgStartColor' => '#c0fe95',
                    'bgEndColor' => '#e8ffcc',
                    'messageUrl' => [
                        'name' => 'SD_XIAOYUAN_MESSAGE',
                        'title' => '消息中心',
                        'url' => '/addon/sd_xiaoyuan/pages/message/index',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'showTaskStat' => true,
                    'showEarningStat' => true,
                    'taskVirtualAdd' => 0,
                    'earningVirtualAdd' => '0.00',
                    'taskCount' => 0,
                    'taskLabel' => '今日任务',
                    'taskUrl' => [
                        'name' => 'SD_XIAOYUAN_ORDER_HALL',
                        'title' => '任务大厅',
                        'url' => '/addon/sd_xiaoyuan/pages/order/hall',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'showGrabOrderBtn' => true,
                    'grabOrderBtnText' => '接单',
                    'grabOrderHallUrl' => [
                        'name' => 'SD_XIAOYUAN_ORDER_HALL',
                        'title' => '任务大厅',
                        'url' => '/addon/sd_xiaoyuan/pages/order/hall',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'earningAmount' => '0.00',
                    'earningLabel' => '累计佣金',
                    'earningUrl' => [
                        'name' => 'SD_XIAOYUAN_RUNNER_CENTER',
                        'title' => '跑腿员中心',
                        'url' => '/addon/sd_xiaoyuan/pages/runner/index',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'searchPlaceholder' => '搜索任务/服务',
                    'searchUrl' => [
                        'name' => 'SD_XIAOYUAN_SEARCH',
                        'title' => '搜索',
                        'url' => '/addon/sd_xiaoyuan/pages/search/index',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'showPromoCard' => true,
                    'promoTitle' => '校园帮实名认证',
                    'promoSubtitle' => '安全可靠，快速认证',
                    'promoBtnText' => 'GO',
                    'promoIcon' => 'account-fill',
                    'promoIconImage' => '',
                    'promoUrl' => [
                        'name' => 'SD_XIAOYUAN_CAMPUS_AUTH',
                        'title' => '校园认证',
                        'url' => '/addon/sd_xiaoyuan/pages/campus/auth',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ]
                ],
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 0,
                        'bottom' => 0,
                        'both' => 0
                    ]
                ]
            ],
            'XiaoyuanSearch' => [
                'title' => '搜索框',
                'icon' => 'iconfont iconsousuopc',
                'path' => 'edit-xiaoyuan-search',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10001,
                'value' => [
                    'text' => '搜索任务/服务',
                    'style' => 'style-1'
                ],
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 10,
                        'bottom' => 10,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanNotice' => [
                'title' => '公告栏',
                'icon' => 'iconfont icontuwendaohang',
                'path' => 'edit-xiaoyuan-notice',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10002,
                'value' => [
                    'text' => '欢迎使用校园帮，有问题请联系客服~',
                    'bgColor' => '#fff7e6',
                    'textColor' => '#ff9500',
                    'isShow' => true
                ],
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 0,
                        'bottom' => 10,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanMenuGrid' => [
                'title' => '功能菜单',
                'icon' => 'iconfont icontuwendaohangpc',
                'path' => 'edit-xiaoyuan-menu-grid',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10003,
                'value' => [
                    'style' => 'style-1',
                    'column' => 5,
                    'list' => [
                        $xy_menu('帮我买', 'shopping-cart-fill', '#ff6b00', '#fff4e6', '/addon/sd_xiaoyuan/pages/buy/create', 'SD_XIAOYUAN_BUY_CREATE'),
                        $xy_menu('帮我送', 'car', '#13c2c2', '#e6fffb', '/addon/sd_xiaoyuan/pages/send/create', 'SD_XIAOYUAN_SEND_CREATE'),
                        $xy_menu('代取快递', 'gift-fill', '#52c41a', '#f6ffed', '/addon/sd_xiaoyuan/pages/express/pickup', 'SD_XIAOYUAN_EXPRESS_PICKUP'),
                        $xy_menu('帮打印', 'file-text-fill', '#ff9800', '#fff8e1', '/addon/sd_xiaoyuan/pages/print/create', 'SD_XIAOYUAN_PRINT_CREATE'),
                        $xy_menu('扔垃圾', 'trash-fill', '#9c27b0', '#f3e5f5', '/addon/sd_xiaoyuan/pages/trash/create', 'SD_XIAOYUAN_TRASH_CREATE'),
                        $xy_menu('帮搬运', 'car-fill', '#4caf50', '#e8f5e9', '/addon/sd_xiaoyuan/pages/carry/create', 'SD_XIAOYUAN_CARRY_CREATE'),
                        $xy_menu('代清洁', 'star-fill', '#2196f3', '#e3f2fd', '/addon/sd_xiaoyuan/pages/clean/create', 'SD_XIAOYUAN_CLEAN_CREATE'),
                        $xy_menu('帮帮忙', 'question-circle-fill', '#e91e63', '#fce4ec', '/addon/sd_xiaoyuan/pages/help/create', 'SD_XIAOYUAN_HELP_CREATE'),
                        $xy_menu('代占座', 'bookmark-fill', '#673ab7', '#ede7f6', '/addon/sd_xiaoyuan/pages/seat/create', 'SD_XIAOYUAN_SEAT_CREATE'),
                        $xy_menu('代排队', 'clock-fill', '#ff5722', '#fff3e0', '/addon/sd_xiaoyuan/pages/queue/create', 'SD_XIAOYUAN_QUEUE_CREATE'),
                        $xy_menu('代上课', 'bookmark-fill', '#5c6bc0', '#e8eaf6', '/addon/sd_xiaoyuan/pages/daike/create', 'SD_XIAOYUAN_DAIKE_CREATE'),
                        $xy_menu('表白墙', 'heart-fill', '#fa709a', '#fff0f5', '/addon/sd_xiaoyuan/pages/confession/index', 'SD_XIAOYUAN_CONFESSION_INDEX'),
                        $xy_menu('游戏陪练', 'red-packet-fill', '#ff7243', '#fff3e0', '/addon/sd_xiaoyuan/pages/game/publish', 'SD_XIAOYUAN_GAME_PUBLISH'),
                        $xy_menu('兼职招聘', 'account-fill', '#6a5acd', '#f3f0ff', '/addon/sd_xiaoyuan/pages/parttime/create', 'SD_XIAOYUAN_PARTTIME_CREATE'),
                        $xy_menu('约伴组局', 'account', '#2f8f83', '#e6fffb', '/addon/sd_xiaoyuan/pages/companion/create', 'SD_XIAOYUAN_COMPANION_CREATE'),
                    ]
                ],
                'template' => [
                    'textColor' => '#303133',
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
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 0,
                        'bottom' => 0,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanBanner' => [
                'title' => '功能入口',
                'icon' => 'iconfont icontuwenguanggaopc',
                'path' => 'edit-xiaoyuan-banner',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10004,
                'value' => [
                    'style' => 'style-1',
                    'column' => 3,
                    'list' => [
                        $xy_banner('房屋租赁', '校园周边好房', 'home-fill', 'linear-gradient(135deg, #7c5cfc, #a78bfa)', '/addon/sd_xiaoyuan/pages/house/index', 'SD_XIAOYUAN_HOUSE_INDEX'),
                        $xy_banner('课程表', '查看课程安排', 'calendar-fill', 'linear-gradient(135deg, #1890ff, #40a9ff)', '/addon/sd_xiaoyuan/pages/schedule/index', 'SD_XIAOYUAN_SCHEDULE_INDEX'),
                        $xy_banner('拼单好饭', '一起拼更划算', 'coupon-fill', 'linear-gradient(135deg, #ff6b35, #ff9a5c)', '/addon/sd_xiaoyuan/pages/group/index', 'SD_XIAOYUAN_GROUP_INDEX'),
                        $xy_banner('闲置市场', '好物低价转', 'bag-fill', 'linear-gradient(135deg, #13c2c2, #36d6b7)', '/addon/sd_xiaoyuan/pages/secondhand/index', 'SD_XIAOYUAN_SECONDHAND_INDEX'),
                        $xy_banner('失物招领', '帮你找回来', 'search', 'linear-gradient(135deg, #ff4d6a, #ff7eb3)', '/addon/sd_xiaoyuan/pages/lost_found/index', 'SD_XIAOYUAN_LOST_FOUND_INDEX'),
                        $xy_banner('校园树洞', '匿名说心事', 'chat-fill', 'linear-gradient(135deg, #722ed1, #b37feb)', '/addon/sd_xiaoyuan/pages/community/index', 'SD_XIAOYUAN_COMMUNITY_INDEX'),
                    ]
                ],
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'elementBgColor' => '',
                    'topElementRounded' => 12,
                    'bottomElementRounded' => 12,
                    'margin' => [
                        'top' => 0,
                        'bottom' => 0,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanOrderHall' => [
                'title' => '订单大厅',
                'icon' => 'iconfont icondingdanzhongxinPC',
                'path' => 'edit-xiaoyuan-order-hall',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10005,
                'value' => [
                    'title' => '任务大厅',
                    'showMore' => true,
                    'moreText' => '查看更多',
                    'moreUrl' => '/addon/sd_xiaoyuan/pages/order/hall',
                    'moreLink' => $xy_link('/addon/sd_xiaoyuan/pages/order/hall', '订单大厅', 'SD_XIAOYUAN_ORDER_HALL'),
                    'num' => 10,
                    'tabs' => [
                        ['name' => '全部', 'type' => 'all', 'isShow' => true],
                        ['name' => '代取快递', 'type' => 'EXPRESS', 'isShow' => true],
                        ['name' => '帮我买', 'type' => 'BUY', 'isShow' => true],
                        ['name' => '帮我送', 'type' => 'ERRAND', 'isShow' => true],
                        ['name' => '代排队', 'type' => 'QUEUE', 'isShow' => true],
                        ['name' => '代上课', 'type' => 'CLASS', 'isShow' => true],
                        ['name' => '代打印', 'type' => 'PRINT', 'isShow' => true],
                        ['name' => '代占座', 'type' => 'SEAT', 'isShow' => true],
                        ['name' => '扔垃圾', 'type' => 'TRASH', 'isShow' => true],
                        ['name' => '帮搬运', 'type' => 'CARRY', 'isShow' => true],
                        ['name' => '代清洁', 'type' => 'CLEAN', 'isShow' => true],
                        ['name' => '帮帮忙', 'type' => 'HELP', 'isShow' => true],
                        ['name' => '游戏陪玩', 'type' => 'GAME', 'isShow' => true],
                        ['name' => '兼职招聘', 'type' => 'PARTTIME', 'isShow' => true],
                        ['name' => '约伴组局', 'type' => 'COMPANION', 'isShow' => true]
                    ]
                ],
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 12,
                    'bottomRounded' => 12,
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 0,
                        'bottom' => 0,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanPromoCard' => [
                'title' => '推广卡片',
                'icon' => 'iconfont iconshangxiatuwenpc',
                'path' => 'edit-xiaoyuan-promo-card',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10006,
                'value' => [
                    'style' => 'style-1',
                    'title' => '校园帮互助实名认证',
                    'subtitle' => '安全可靠，快速认证',
                    'btnText' => 'GO',
                    'bgColor' => 'linear-gradient(135deg, #c0fe95, #d1ff7c)',
                    'url' => '/addon/sd_xiaoyuan/pages/campus/auth',
                    'link' => $xy_link('/addon/sd_xiaoyuan/pages/campus/auth', '校园帮互助实名认证', 'SD_XIAOYUAN_CAMPUS_AUTH'),
                    'isShow' => true
                ],
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 12,
                    'bottomRounded' => 12,
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 10,
                        'bottom' => 10,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanCardGrid' => [
                'title' => '次卡网格',
                'icon' => 'iconfont iconshangxiatuwenpc',
                'path' => 'edit-xiaoyuan-card-grid',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10005,
                'value' => [
                    'isShow' => true,
                    'showApply' => true,
                    'applyTitle' => '申请接单',
                    'applySubtitle' => '成为校园跑腿员',
                    'list' => [
                        ['cardType' => 'EXPRESS', 'name' => '快递卡', 'subtitle' => '代取快递更省心', 'isShow' => true],
                        ['cardType' => 'ERRAND', 'name' => '跑腿卡', 'subtitle' => '校园跑腿一键下单', 'isShow' => true],
                        ['cardType' => 'PRINT', 'name' => '打印卡', 'subtitle' => '代打印省时省力', 'isShow' => true],
                    ],
                ],
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 12,
                    'bottomRounded' => 12,
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 10,
                        'bottom' => 10,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanCommunity' => [
                'title' => '校园树洞',
                'icon' => 'iconfont iconshangxiatuwenpc',
                'path' => 'edit-xiaoyuan-community',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10007,
                'value' => [
                    'title' => '校园树洞',
                    'showMore' => true,
                    'moreText' => '查看更多',
                    'moreUrl' => [
                        'name' => 'SD_XIAOYUAN_COMMUNITY',
                        'title' => '校园树洞',
                        'url' => '/addon/sd_xiaoyuan/pages/community/index',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'num' => 10,
                    'category_id' => 0,
                    'sort' => 'new'
                ],
                'template' => [
                    'textColor' => '#303133',
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
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 0,
                        'bottom' => 0,
                        'both' => 10
                    ]
                ]
            ],
            'XiaoyuanUserHeader' => [
                'title' => '个人中心头部',
                'icon' => 'iconfont iconhuiyuanqiandaopc',
                'path' => 'edit-xiaoyuan-user-header',
                'support_page' => ['DIY_SD_XIAOYUAN_MEMBER'],
                'uses' => 1,
                'sort' => 10020,
                'value' => [
                    'bgStartColor' => '#c0fe95',
                    'bgEndColor' => '#f7f7f7',
                    'welcomeText' => '欢迎使用校园帮',
                    'loginTip' => '点击登录',
                    'showCredit' => true,
                    'settingsUrl' => [
                        'name' => 'SD_XIAOYUAN_SETTING',
                        'title' => '设置',
                        'url' => '/app/pages/setting/index',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'creditUrl' => [
                        'name' => 'SD_XIAOYUAN_CREDIT_LOG',
                        'title' => '信誉记录',
                        'url' => '/addon/sd_xiaoyuan/pages/credit/log',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                ],
                'template' => [
                    'textColor' => '#303133',
                    'margin' => ['top' => 0, 'bottom' => 0, 'both' => 0]
                ]
            ],
            'XiaoyuanUserMenu' => [
                'title' => '个人中心菜单',
                'icon' => 'iconfont iconhuiyuanqiandaopc',
                'path' => 'edit-xiaoyuan-user-menu',
                'support_page' => ['DIY_SD_XIAOYUAN_MEMBER'],
                'uses' => 0,
                'sort' => 10021,
                'value' => [
                    'title' => '功能菜单',
                    'column' => 5,
                    'list' => [],
                ],
                'template' => [
                    'componentStartBgColor' => '#ffffff',
                    'topRounded' => 12,
                    'bottomRounded' => 12,
                    'margin' => ['top' => 6, 'bottom' => 6, 'both' => 12]
                ]
            ],
            'XiaoyuanIdlePreview' => [
                'title' => '闲置好物',
                'icon' => 'iconfont iconshangxiatuwenpc',
                'path' => 'edit-xiaoyuan-idle-preview',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10008,
                'value' => [
                    'title' => '闲置好物',
                    'showMore' => true,
                    'moreText' => '查看更多',
                    'moreUrl' => [
                        'name' => 'SD_XIAOYUAN_SECONDHAND',
                        'title' => '闲置市场',
                        'url' => '/addon/sd_xiaoyuan/pages/secondhand/index',
                        'parent' => 'SD_XIAOYUAN_LINK'
                    ],
                    'num' => 10,
                    'sort' => 'new',
                    'category_id' => 0,
                    'goods_type' => ''
                ],
                'template' => [
                    'textColor' => '#303133',
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
                    'elementBgColor' => '',
                    'topElementRounded' => 0,
                    'bottomElementRounded' => 0,
                    'margin' => [
                        'top' => 0,
                        'bottom' => 0,
                        'both' => 10
                    ]
                ]
            ]
        ]
    ]
];
