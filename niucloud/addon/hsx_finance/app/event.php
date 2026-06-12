<?php
// 财务插件事件注册
// 消费: 回收/销售产出的应付/应收事实(通道名 CamelCase, payload 携带 .vN 契约名+event_id 幂等)
// 产出: FinanceSettlementCompleted (结算完成, 业务/ERP 订阅以更新展示)
return [
    'bind' => [],
    'listen' => [
        'FinancePayableCreated' => [
            'addon\hsx_finance\app\listener\PayableCreatedListener',
        ],
        'FinanceReceivableCreated' => [
            'addon\hsx_finance\app\listener\ReceivableCreatedListener',
        ],
    ],
    'subscribe' => [],
];
