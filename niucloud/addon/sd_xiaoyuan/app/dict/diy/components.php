<?php

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
                    'schoolName' => '选择学校',
                    'schoolUrl' => '/addon/sd_xiaoyuan/pages/school/select',
                    'taskCount' => 0,
                    'taskLabel' => '今日任务',
                    'taskUrl' => '/addon/sd_xiaoyuan/pages/order/hall',
                    'earningAmount' => '0.00',
                    'earningLabel' => '累计佣金',
                    'earningUrl' => '/addon/sd_xiaoyuan/pages/runner/index',
                    'searchPlaceholder' => '搜索任务/服务',
                    'searchUrl' => '/addon/sd_xiaoyuan/pages/search/index',
                    'messageUrl' => '/addon/sd_xiaoyuan/pages/message/index',
                    'showPromoCard' => true,
                    'promoTitle' => '校园帮实名认证',
                    'promoSubtitle' => '安全可靠，快速认证',
                    'promoBtnText' => 'GO',
                    'promoIcon' => 'account-fill',
                    'promoUrl' => '/addon/sd_xiaoyuan/pages/campus/auth'
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
                        ['name' => '帮我买', 'icon' => 'shopping-cart-fill', 'iconColor' => '#ff6b00', 'bgColor' => '#fff4e6', 'url' => '/addon/sd_xiaoyuan/pages/buy/create', 'isShow' => true],
                        ['name' => '帮我送', 'icon' => 'car', 'iconColor' => '#13c2c2', 'bgColor' => '#e6fffb', 'url' => '/addon/sd_xiaoyuan/pages/send/create', 'isShow' => true],
                        ['name' => '代取快递', 'icon' => 'gift-fill', 'iconColor' => '#52c41a', 'bgColor' => '#f6ffed', 'url' => '/addon/sd_xiaoyuan/pages/express/pickup', 'isShow' => true],
                        ['name' => '帮打印', 'icon' => 'file-text-fill', 'iconColor' => '#ff9800', 'bgColor' => '#fff8e1', 'url' => '/addon/sd_xiaoyuan/pages/print/create', 'isShow' => true],
                        ['name' => '扔垃圾', 'icon' => 'trash-fill', 'iconColor' => '#9c27b0', 'bgColor' => '#f3e5f5', 'url' => '/addon/sd_xiaoyuan/pages/trash/create', 'isShow' => true],
                        ['name' => '帮搬运', 'icon' => 'car-fill', 'iconColor' => '#4caf50', 'bgColor' => '#e8f5e9', 'url' => '/addon/sd_xiaoyuan/pages/carry/create', 'isShow' => true],
                        ['name' => '代清洁', 'icon' => 'star-fill', 'iconColor' => '#2196f3', 'bgColor' => '#e3f2fd', 'url' => '/addon/sd_xiaoyuan/pages/clean/create', 'isShow' => true],
                        ['name' => '帮帮忙', 'icon' => 'question-circle-fill', 'iconColor' => '#e91e63', 'bgColor' => '#fce4ec', 'url' => '/addon/sd_xiaoyuan/pages/help/create', 'isShow' => true],
                        ['name' => '表白墙', 'icon' => 'heart-fill', 'iconColor' => '#fa709a', 'bgColor' => '#fff0f5', 'url' => '/addon/sd_xiaoyuan/pages/confession/index', 'isShow' => true],
                        ['name' => '游戏陪练', 'icon' => 'red-packet-fill', 'iconColor' => '#ff7243', 'bgColor' => '#fff3e0', 'url' => '/addon/sd_xiaoyuan/pages/game/publish', 'isShow' => true]
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
                        ['title' => '房屋租赁', 'desc' => '校园周边好房', 'icon' => 'home-fill', 'bgColor' => 'linear-gradient(135deg, #7c5cfc, #a78bfa)', 'url' => '/addon/sd_xiaoyuan/pages/house/index', 'isShow' => true],
                        ['title' => '课程表', 'desc' => '查看课程安排', 'icon' => 'calendar-fill', 'bgColor' => 'linear-gradient(135deg, #1890ff, #40a9ff)', 'url' => '/addon/sd_xiaoyuan/pages/schedule/index', 'isShow' => true],
                        ['title' => '拼单好饭', 'desc' => '一起拼更划算', 'icon' => 'coupon-fill', 'bgColor' => 'linear-gradient(135deg, #ff6b35, #ff9a5c)', 'url' => '/addon/sd_xiaoyuan/pages/group/index', 'isShow' => true],
                        ['title' => '闲置市场', 'desc' => '好物低价转', 'icon' => 'bag-fill', 'bgColor' => 'linear-gradient(135deg, #13c2c2, #36d6b7)', 'url' => '/addon/sd_xiaoyuan/pages/secondhand/index', 'isShow' => true],
                        ['title' => '失物招领', 'desc' => '帮你找回来', 'icon' => 'search', 'bgColor' => 'linear-gradient(135deg, #ff4d6a, #ff7eb3)', 'url' => '/addon/sd_xiaoyuan/pages/lost_found/index', 'isShow' => true],
                        ['title' => '校园树洞', 'desc' => '匿名说心事', 'icon' => 'chat-fill', 'bgColor' => 'linear-gradient(135deg, #722ed1, #b37feb)', 'url' => '/addon/sd_xiaoyuan/pages/community/index', 'isShow' => true]
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
                    'num' => 10,
                    'tabs' => [
                        ['name' => '全部', 'type' => 'all', 'isShow' => true],
                        ['name' => '代取快递', 'type' => 'EXPRESS', 'isShow' => true],
                        ['name' => '帮我买', 'type' => 'BUY', 'isShow' => true],
                        ['name' => '代打印', 'type' => 'PRINT', 'isShow' => true],
                        ['name' => '扔垃圾', 'type' => 'TRASH', 'isShow' => true],
                        ['name' => '帮搬运', 'type' => 'CARRY', 'isShow' => true],
                        ['name' => '代清洁', 'type' => 'CLEAN', 'isShow' => true],
                        ['name' => '帮帮忙', 'type' => 'HELP', 'isShow' => true],
                        ['name' => '游戏陪玩', 'type' => 'GAME', 'isShow' => true]
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
            ]
        ]
    ]
];
