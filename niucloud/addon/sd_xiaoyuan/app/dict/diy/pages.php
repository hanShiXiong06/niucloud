<?php

return [
    'DIY_SD_XIAOYUAN_INDEX' => [
        'sd_xiaoyuan_index_default' => [
            'title' => '校园帮首页',
            'cover' => '',
            'preview' => '',
            'desc' => '校园帮首页默认模板',
            'mode' => 'diy',
            'data' => [
                'global' => [
                    'title' => '校园帮',
                    'pageStartBgColor' => '#f7f7f7',
                    'pageEndBgColor' => '#f7f7f7',
                    'pageGradientAngle' => 'to bottom',
                    'bgUrl' => '',
                    'bgHeightScale' => 100,
                    'imgWidth' => '',
                    'imgHeight' => '',
                    'topStatusBar' => [
                        'control' => true,
                        'isShow' => true,
                        'bgColor' => '#c0fe95',
                        'rollBgColor' => '#c0fe95',
                        'style' => 'style-2',
                        'textColor' => '#333333',
                        'rollTextColor' => '#333333',
                        'textAlign' => 'center',
                        'inputPlaceholder' => '请输入搜索关键词',
                        'imgUrl' => '',
                        'link' => [
                            'name' => ''
                        ]
                    ],
                    'bottomTabBar' => [
                        'isShow' => true,
                        'control' => true
                    ],
                    'copyright' => [
                        'control' => true,
                        'isShow' => false,
                        'textColor' => '#ccc'
                    ],
                    'popWindow' => [
                        'imgUrl' => '',
                        'imgWidth' => '',
                        'imgHeight' => '',
                        'count' => -1,
                        'show' => 0,
                        'link' => [
                            'name' => ''
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
                            'both' => 12
                        ]
                    ]
                ],
                'value' => [
                    [
                        'path' => 'edit-xiaoyuan-header',
                        'uses' => 1,
                        'id' => 'xiaoyuan_header_1',
                        'componentName' => 'XiaoyuanHeader',
                        'componentTitle' => '顶部导航',
                        'bgStartColor' => '#c0fe95',
                        'bgEndColor' => '#e8ffcc',
                        'schoolName' => '北京大学',
                        'schoolUrl' => [
                            'name' => 'SD_XIAOYUAN_INDEX',
                            'title' => '首页',
                            'url' => '/addon/sd_xiaoyuan/pages/index/index',
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
                            'title' => '订单大厅',
                            'url' => '/addon/sd_xiaoyuan/pages/order/hall',
                            'parent' => 'SD_XIAOYUAN_LINK'
                        ],
                        'earningAmount' => '0.00',
                        'earningLabel' => '累计佣金',
                        'earningUrl' => [
                            'name' => 'SD_XIAOYUAN_RUNNER_INDEX',
                            'title' => '接单员中心',
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
                        'messageUrl' => [
                            'name' => 'SD_XIAOYUAN_MESSAGE_LIST',
                            'title' => '消息中心',
                            'url' => '/addon/sd_xiaoyuan/pages/message/index',
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
                        ],
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
                        'margin' => ['top' => 0, 'bottom' => 0, 'both' => 0]
                    ],
                    [
                        'path' => 'edit-xiaoyuan-notice',
                        'uses' => 1,
                        'id' => 'xiaoyuan_notice_1',
                        'componentName' => 'XiaoyuanNotice',
                        'componentTitle' => '公告栏',
                        'text' => '欢迎使用校园帮，有问题请联系客服~',
                        'bgColor' => '#fff7e6',
                        'textColor' => '#ff9500',
                        'isShow' => true,
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
                        'margin' => ['top' => 0, 'bottom' => 10, 'both' => 10]
                    ],
                    [
                        'path' => 'edit-xiaoyuan-menu-grid',
                        'uses' => 1,
                        'id' => 'xiaoyuan_menu_grid_1',
                        'componentName' => 'XiaoyuanMenuGrid',
                        'componentTitle' => '功能菜单',
                        'style' => 'style-1',
                        'column' => 5,
                        'list' => [
                            ['name' => '帮我买', 'icon' => 'shopping-cart-fill', 'iconColor' => '#ff6b00', 'bgColor' => '#fff4e6', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/buy/create', 'link' => ['name' => 'SD_XIAOYUAN_BUY_CREATE', 'title' => '帮我买', 'url' => '/addon/sd_xiaoyuan/pages/buy/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '帮我送', 'icon' => 'car', 'iconColor' => '#13c2c2', 'bgColor' => '#e6fffb', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/send/create', 'link' => ['name' => 'SD_XIAOYUAN_SEND_CREATE', 'title' => '帮我送', 'url' => '/addon/sd_xiaoyuan/pages/send/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '代取快递', 'icon' => 'gift-fill', 'iconColor' => '#52c41a', 'bgColor' => '#f6ffed', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/express/pickup', 'link' => ['name' => 'SD_XIAOYUAN_EXPRESS_PICKUP', 'title' => '代取快递', 'url' => '/addon/sd_xiaoyuan/pages/express/pickup', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '帮打印', 'icon' => 'file-text-fill', 'iconColor' => '#ff9800', 'bgColor' => '#fff8e1', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/print/create', 'link' => ['name' => 'SD_XIAOYUAN_PRINT_CREATE', 'title' => '帮打印', 'url' => '/addon/sd_xiaoyuan/pages/print/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '扔垃圾', 'icon' => 'trash-fill', 'iconColor' => '#9c27b0', 'bgColor' => '#f3e5f5', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/trash/create', 'link' => ['name' => 'SD_XIAOYUAN_TRASH_CREATE', 'title' => '扔垃圾', 'url' => '/addon/sd_xiaoyuan/pages/trash/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '帮搬运', 'icon' => 'car-fill', 'iconColor' => '#4caf50', 'bgColor' => '#e8f5e9', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/carry/create', 'link' => ['name' => 'SD_XIAOYUAN_CARRY_CREATE', 'title' => '帮搬运', 'url' => '/addon/sd_xiaoyuan/pages/carry/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '代清洁', 'icon' => 'star-fill', 'iconColor' => '#2196f3', 'bgColor' => '#e3f2fd', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/clean/create', 'link' => ['name' => 'SD_XIAOYUAN_CLEAN_CREATE', 'title' => '代清洁', 'url' => '/addon/sd_xiaoyuan/pages/clean/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '帮帮忙', 'icon' => 'question-circle-fill', 'iconColor' => '#e91e63', 'bgColor' => '#fce4ec', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/help/create', 'link' => ['name' => 'SD_XIAOYUAN_HELP_CREATE', 'title' => '帮帮忙', 'url' => '/addon/sd_xiaoyuan/pages/help/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '代占座', 'icon' => 'bookmark-fill', 'iconColor' => '#673ab7', 'bgColor' => '#ede7f6', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/seat/create', 'link' => ['name' => 'SD_XIAOYUAN_SEAT_CREATE', 'title' => '代占座', 'url' => '/addon/sd_xiaoyuan/pages/seat/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '表白墙', 'icon' => 'heart-fill', 'iconColor' => '#fa709a', 'bgColor' => '#fff0f5', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/confession/index', 'link' => ['name' => 'SD_XIAOYUAN_CONFESSION_INDEX', 'title' => '表白墙', 'url' => '/addon/sd_xiaoyuan/pages/confession/index', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '游戏陪练', 'icon' => 'red-packet-fill', 'iconColor' => '#ff7243', 'bgColor' => '#fff3e0', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/game/publish', 'link' => ['name' => 'SD_XIAOYUAN_GAME_PUBLISH', 'title' => '游戏陪练', 'url' => '/addon/sd_xiaoyuan/pages/game/publish', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '兼职招聘', 'icon' => 'account-fill', 'iconColor' => '#6a5acd', 'bgColor' => '#f3f0ff', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/parttime/create', 'link' => ['name' => 'SD_XIAOYUAN_PARTTIME_CREATE', 'title' => '兼职招聘', 'url' => '/addon/sd_xiaoyuan/pages/parttime/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['name' => '约伴组局', 'icon' => 'team-fill', 'iconColor' => '#2f8f83', 'bgColor' => '#e6fffb', 'imageUrl' => '', 'url' => '/addon/sd_xiaoyuan/pages/companion/create', 'link' => ['name' => 'SD_XIAOYUAN_COMPANION_CREATE', 'title' => '约伴组局', 'url' => '/addon/sd_xiaoyuan/pages/companion/create', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true]
                        ],
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
                        'margin' => ['top' => 10, 'bottom' => 10, 'both' => 10]
                    ],
                    [
                        'path' => 'edit-xiaoyuan-banner',
                        'uses' => 0,
                        'id' => 'xiaoyuan_banner_1',
                        'componentName' => 'XiaoyuanBanner',
                        'componentTitle' => '功能入口',
                        'style' => 'style-1',
                        'column' => 3,
                        'list' => [
                            ['title' => '房屋租赁', 'desc' => '校园周边好房', 'icon' => 'home-fill', 'imageUrl' => '', 'bgColor' => 'linear-gradient(135deg, #7c5cfc, #a78bfa)', 'url' => '/addon/sd_xiaoyuan/pages/house/index', 'link' => ['name' => 'SD_XIAOYUAN_HOUSE_INDEX', 'title' => '房屋租赁', 'url' => '/addon/sd_xiaoyuan/pages/house/index', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['title' => '课程表', 'desc' => '查看课程安排', 'icon' => 'calendar-fill', 'imageUrl' => '', 'bgColor' => 'linear-gradient(135deg, #1890ff, #40a9ff)', 'url' => '/addon/sd_xiaoyuan/pages/schedule/index', 'link' => ['name' => 'SD_XIAOYUAN_SCHEDULE_INDEX', 'title' => '课程表', 'url' => '/addon/sd_xiaoyuan/pages/schedule/index', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['title' => '拼单好饭', 'desc' => '一起拼更划算', 'icon' => 'coupon-fill', 'imageUrl' => '', 'bgColor' => 'linear-gradient(135deg, #ff6b35, #ff9a5c)', 'url' => '/addon/sd_xiaoyuan/pages/group/index', 'link' => ['name' => 'SD_XIAOYUAN_GROUP_INDEX', 'title' => '拼单好饭', 'url' => '/addon/sd_xiaoyuan/pages/group/index', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['title' => '闲置市场', 'desc' => '好物低价转', 'icon' => 'bag-fill', 'imageUrl' => '', 'bgColor' => 'linear-gradient(135deg, #13c2c2, #36d6b7)', 'url' => '/addon/sd_xiaoyuan/pages/secondhand/index', 'link' => ['name' => 'SD_XIAOYUAN_SECONDHAND_INDEX', 'title' => '闲置市场', 'url' => '/addon/sd_xiaoyuan/pages/secondhand/index', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['title' => '失物招领', 'desc' => '帮你找回来', 'icon' => 'search', 'imageUrl' => '', 'bgColor' => 'linear-gradient(135deg, #ff4d6a, #ff7eb3)', 'url' => '/addon/sd_xiaoyuan/pages/lost_found/index', 'link' => ['name' => 'SD_XIAOYUAN_LOST_FOUND_INDEX', 'title' => '失物招领', 'url' => '/addon/sd_xiaoyuan/pages/lost_found/index', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true],
                            ['title' => '校园树洞', 'desc' => '匿名说心事', 'icon' => 'chat-fill', 'imageUrl' => '', 'bgColor' => 'linear-gradient(135deg, #722ed1, #b37feb)', 'url' => '/addon/sd_xiaoyuan/pages/community/index', 'link' => ['name' => 'SD_XIAOYUAN_COMMUNITY_INDEX', 'title' => '校园树洞', 'url' => '/addon/sd_xiaoyuan/pages/community/index', 'parent' => 'SD_XIAOYUAN_LINK'], 'isShow' => true]
                        ],
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
                        'margin' => ['top' => 0, 'bottom' => 10, 'both' => 10]
                    ],
                    [
                        'path' => 'edit-xiaoyuan-order-hall',
                        'uses' => 1,
                        'id' => 'xiaoyuan_order_hall_1',
                        'componentName' => 'XiaoyuanOrderHall',
                        'componentTitle' => '订单大厅',
                        'title' => '任务大厅',
                        'showMore' => true,
                        'moreText' => '查看更多',
                        'moreUrl' => '/addon/sd_xiaoyuan/pages/order/hall',
                        'moreLink' => [
                            'name' => 'SD_XIAOYUAN_ORDER_HALL',
                            'title' => '订单大厅',
                            'url' => '/addon/sd_xiaoyuan/pages/order/hall',
                            'parent' => 'SD_XIAOYUAN_LINK'
                        ],
                        'num' => 10,
                        'tabs' => [
                            ['name' => '全部', 'type' => 'all', 'isShow' => true],
                            ['name' => '代取快递', 'type' => 'EXPRESS', 'isShow' => true],
                            ['name' => '帮我买', 'type' => 'BUY', 'isShow' => true],
                            ['name' => '帮我送', 'type' => 'ERRAND', 'isShow' => true],
                            ['name' => '代排队', 'type' => 'QUEUE', 'isShow' => true],
                            ['name' => '代打印', 'type' => 'PRINT', 'isShow' => true],
                            ['name' => '代占座', 'type' => 'SEAT', 'isShow' => true],
                            ['name' => '扔垃圾', 'type' => 'TRASH', 'isShow' => true],
                            ['name' => '帮搬运', 'type' => 'CARRY', 'isShow' => true],
                            ['name' => '代清洁', 'type' => 'CLEAN', 'isShow' => true],
                            ['name' => '帮帮忙', 'type' => 'HELP', 'isShow' => true],
                            ['name' => '游戏陪玩', 'type' => 'GAME', 'isShow' => true],
                            ['name' => '兼职招聘', 'type' => 'PARTTIME', 'isShow' => true],
                            ['name' => '约伴组局', 'type' => 'COMPANION', 'isShow' => true]
                        ],
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
                        'margin' => ['top' => 10, 'bottom' => 10, 'both' => 10]
                    ]
                ]
            ]
        ]
    ]
];
