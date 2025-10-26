<?php
return [
    'home_service_order_service' => [
        'temp_key' => '43122',
        'content' => [
            ['师傅', '{technician}', 'thing16'],
            ['联系电话', '{mobile}', 'phone_number23'],
        ],
        'keyword_name_list' => ["服务人员", "联系电话"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商业服务——>软件/建站/技术开发'
    ],
    'home_service_store_dispatch' => [
        'temp_key' => '44268',
        'content' => [
            ['门店名称', '{store_name}', 'thing15'],
            ['项目名称', '{goods_name}', 'thing13'],
            ['工单用户', '{taker_name}', 'thing3'],
            ['联系电话', '{taker_mobile}', 'phone_number4'],
            ['服务地址', '{taker_full_address}', 'thing26'],
        ],
        'keyword_name_list' => ["门店名称", "项目名称", "工单用户", "联系电话", "服务地址"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：商业服务——>软件/建站/技术开发'
    ],
    'home_service_refund' => [
        'temp_key' => '58785',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['项目名称', '{goods_name}', 'thing6'],
            ['退款金额', '{money}', 'amount2'],
        ],
        'keyword_name_list' => ["订单编号", "项目名称", "退款金额"],
        'tips' => '使用该消息请将微信公众号服务类目选择为：休闲娱乐'
    ],
];
