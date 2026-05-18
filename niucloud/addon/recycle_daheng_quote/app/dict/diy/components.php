<?php

return [
    'RECYCLE_DAHENG_QUOTE_COMPONENT' => [
        'title' => 'DH速收报价',
        'list' => [
            'RecycleQuotationList' => [
                'title' => 'DH报价单',
                'icon' => 'iconfont iconshangpinliebiaopc',
                'path' => 'edit-recycle-quotation-list',
                'support_page' => [],
                'uses' => 0,
                'sort' => 10007,
                'value' => [
                    'title' => '今日报价',
                    'subtitle' => '实时同步回收报价单',
                    'actionText' => '查看',
                    'limit' => 5,
                    'showRefresh' => true,
                    'displayStyle' => 'list',
                    'navRowCount' => 4,
                    'navImageSize' => 40,
                    'navAroundRadius' => 20,
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 0,
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'titleColor' => '#111827',
                    'subtitleColor' => '#6B7280',
                    'buttonColor' => '#2563EB',
                    'margin' => [
                        'top' => 10,
                        'bottom' => 10,
                        'both' => 12,
                    ],
                ],
            ],
        ],
    ],
];
