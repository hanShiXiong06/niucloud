<?php

return [
    'hsx_phone_query_success' => [
        'temp_key' => '',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['查询项目', '{service_name}', 'thing2'],
            ['查询结果', '成功{success_count}条，失败{fail_count}条', 'thing3'],
            ['完成时间', '{finish_time}', 'time4'],
            ['温馨提示', '{refund_text}', 'thing5'],
        ],
        'keyword_name_list' => ['订单编号', '查询项目', '查询结果', '完成时间', '温馨提示'],
        'tips' => '公众号模板消息需要在微信公众平台申请对应模板。若框架无法自动同步，请在通知配置中手动填写模板 ID。',
    ],
    'hsx_phone_query_fail' => [
        'temp_key' => '',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['查询项目', '{service_name}', 'thing2'],
            ['失败原因', '{fail_reason}', 'thing3'],
            ['完成时间', '{finish_time}', 'time4'],
            ['温馨提示', '{refund_text}', 'thing5'],
        ],
        'keyword_name_list' => ['订单编号', '查询项目', '失败原因', '完成时间', '温馨提示'],
        'tips' => '公众号模板消息需要在微信公众平台申请对应模板。若框架无法自动同步，请在通知配置中手动填写模板 ID。',
    ],
];
