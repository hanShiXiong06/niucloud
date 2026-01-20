<?php

return [
    'bind' => [

    ],
    'listen' => [
        //订单创建后
        'AfterKdOrderCreate' => [ 'addon\kd_api\app\listener\order\AfterKdOrderCreate' ],
        //订单支付后, 计算佣金
        'AfterKdOrderPay' => [ 'addon\kd_api\app\listener\order\AfterKdOrderPay' ],
        //订单收货后, 结算佣金
        'AfterKdOrderFinish' => [ 'addon\kd_api\app\listener\order\AfterKdOrderFinish' ],
        //订单退款后, 重新计算佣金
        'AfterKdOrderRefundFinish' => [ 'addon\kd_api\app\listener\refund\AfterKdOrderRefundFinish' ],

    ],
    'subscribe' => [
    ],
];