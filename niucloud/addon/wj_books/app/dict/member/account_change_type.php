<?php

use app\dict\member\MemberAccountTypeDict;

return [
    // 我们只定义需要的可提现余额(MONEY)类型的变动
    MemberAccountTypeDict::MONEY => [
        // 二手书回收订单
        'wj_books_order' => [
            // 名称
            'name' => '二手书回收',
            // 是否可增加
            'inc' => 1,
            // 是否可减少
            'dec' => 0,
            // 是否累增（影响账户总收入统计）
            'is_change_get' => 1,
        ],
    ]
]; 