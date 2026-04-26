<?php

return [
    'WJ_BOOKS_BASE_LINK' => [
        'title' => '旧书回收',
        'type' => 'folder', // 类型，folder 表示文件夹，link 表示链接
        'child_list' => [
            [
                'name' => 'WJ_BOOKS_LINK',
                'title' => '旧书回收链接',
                'child_list' => [
                    [
                        'name' => 'WJ_BOOKS_INDEX',
                        'title' => '旧书回收首页',
                        'url' => '/addon/wj_books/pages/home/index',
                        'is_share' => 1,
                        'action' => 'decorate'
                    ],
                    [
                        'name' => 'WJ_BOOKS_ORDER_LIST',
                        'title' => '回收订单列表',
                        'url' => '/addon/wj_books/pages/order/list',
                        'is_share' => 0,
                        'action' => 'decorate'
                    ],
                    [
                        'name' => 'WJ_BOOKS_MEMBER_CENTER',
                        'title' => '回收个人中心',
                        'url' => '/addon/wj_books/pages/home/my',
                        'is_share' => 0,
                        'action' => 'decorate'
                    ],
                ]
            ],
        ]
    ],
];
