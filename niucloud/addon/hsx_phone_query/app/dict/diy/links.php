<?php
return [
    'HSX_PHONE_QUERY_LINK' => [
        'key' => 'phone_query',
        'addon_title' => '手机信息查询系统',
        'title' => '手机信息查询系统',
        'child_list' => [
            [
                'name' => 'HSX_PHONE_QUERY_INDEX',
                'title' => get_lang('dict_diy.query_link_index'),
                'url' => '/addon/hsx_phone_query/pages/index',
                'is_share' => 1,
                'action' => ''
            ]
        ]
    ]
];