<?php

use addon\tk_jhkd\app\service\core\CommonService;

$linkData=[
    [
        'name' => 'TK_JHKD_INDEX',
        'title' =>'快递首页',
        'url' => '/addon/tk_jhkd/pages/index',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_JHKD_MEMBER_INDEX',
        'title' =>'个人中心',
        'url' => '/addon/tk_jhkd/pages/member',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_JHKD_COUPON',
        'title' =>'优惠券',
        'url' => '/addon/tk_jhkd/pages/coupon/list',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_JHKD_FENXIAO_INDEX',
        'title' =>'分销中心',
        'url' => '/addon/tk_jhkd/pages/fenxiao/index',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_JHKD_MEMBER_FENXIAO_MEMBER',
        'title' => '分销会员',
        'url' => '/addon/tk_jhkd/pages/fenxiao/member',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_JHKD_MEMBER_FENXIAO_ORDER',
        'title' => '分销订单',
        'url' => '/addon/tk_jhkd/pages/fenxiao/order',
        'is_share' => 1,
        'action' => 'decorate'
    ],
    [
        'name' => 'TK_JHKD_ORDERSUBMIT',
        'title' =>'快递下单',
        'url' => '/addon/tk_jhkd/pages/ordersubmit',
        'is_share' => 1,
        'action' => ''
    ],
    [
        'name' => 'TK_JHKD_HELP',
        'title' => '帮助中心',
        'url' => '/addon/tk_jhkd/pages/help',
        'is_share' => 1,
        'action' => ''
    ],
    [
        'name' => 'TK_JHKD_YESGOODS',
        'title' => '违禁查询',
        'url' => '/addon/tk_jhkd/pages/agreement?type=jhkdyesgoods',
        'is_share' => 1,
        'action' => ''
    ],
    [
        'name' => 'TK_JHKD_ORDERLIST',
        'title' =>'订单列表',
        'url' => '/addon/tk_jhkd/pages/orderlist',
        'is_share' => 1,
        'action' => ''
    ],
    [
        'name' => 'TK_JHKD_ORDERADDLIST',
        'title' =>'补差价',
        'url' => '/addon/tk_jhkd/pages/orderaddlist',
        'is_share' => 1,
        'action' => ''
    ],
    [
        'name' => 'TK_JHKD_ORDERKD',
        'title' =>'寄快递',
        'url' => '/addon/tk_jhkd/pages/ordersubmit?type=kd',
        'is_share' => 1,
        'action' => ''
    ],
    [
        'name' => 'TK_JHKD_ORDERKY',
        'title' =>'寄重货',
        'url' => '/addon/tk_jhkd/pages/ordersubmit?type=ky',
        'is_share' => 1,
        'action' => ''
    ],
];
$fenxiao=(new CommonService())->checkFenxiao();
if($fenxiao['status']==true){
    $linkData=[
        [
            'name' => 'TK_JHKD_INDEX',
            'title' =>'快递首页',
            'url' => '/addon/tk_jhkd/pages/index',
            'is_share' => 1,
            'action' => 'decorate'
        ],
        [
            'name' => 'TK_JHKD_MEMBER_INDEX',
            'title' =>'个人中心',
            'url' => '/addon/tk_jhkd/pages/member',
            'is_share' => 1,
            'action' => 'decorate'
        ],
        [
            'name' => 'TK_JHKD_COUPON',
            'title' =>'优惠券',
            'url' => '/addon/tk_jhkd/pages/coupon/list',
            'is_share' => 1,
            'action' => 'decorate'
        ],
        [
            'name' => 'TK_JHKD_ORDERSUBMIT',
            'title' =>'快递下单',
            'url' => '/addon/tk_jhkd/pages/ordersubmit',
            'is_share' => 1,
            'action' => ''
        ],
        [
            'name' => 'TK_JHKD_HELP',
            'title' => '帮助中心',
            'url' => '/addon/tk_jhkd/pages/help',
            'is_share' => 1,
            'action' => ''
        ],
        [
            'name' => 'TK_JHKD_YESGOODS',
            'title' => '违禁查询',
            'url' => '/addon/tk_jhkd/pages/agreement?type=jhkdyesgoods',
            'is_share' => 1,
            'action' => ''
        ],
        [
            'name' => 'TK_JHKD_ORDERLIST',
            'title' =>'订单列表',
            'url' => '/addon/tk_jhkd/pages/orderlist',
            'is_share' => 1,
            'action' => ''
        ],
        [
            'name' => 'TK_JHKD_ORDERADDLIST',
            'title' =>'补差价',
            'url' => '/addon/tk_jhkd/pages/orderaddlist',
            'is_share' => 1,
            'action' => ''
        ],
        [
            'name' => 'TK_JHKD_ORDERKD',
            'title' =>'寄快递',
            'url' => '/addon/tk_jhkd/pages/ordersubmit?type=kd',
            'is_share' => 1,
            'action' => ''
        ],
        [
            'name' => 'TK_JHKD_ORDERKY',
            'title' =>'寄重货',
            'url' => '/addon/tk_jhkd/pages/ordersubmit?type=ky',
            'is_share' => 1,
            'action' => ''
        ],
    ];
}
return [
    'TK_JHKD_DIY_LINK' => [
        'key' => 'tk_jhkd_link',
        'addon_title' => '聚合快递',
        'title' => '聚合快递',
        'child_list' => $linkData
    ],
];