<?php

return [
    'bind' => [

    ],
    'listen' => [
        'PayCreate' => [
            'addon\hsx_phone_query\app\listener\pay\PayCreateListener',
        ],
        'PaySuccess' => [
            'addon\hsx_phone_query\app\listener\pay\PaySuccessListener',
        ],
        'NoticeData' => [
            'addon\hsx_phone_query\app\listener\notice\QuerySuccess',
            'addon\hsx_phone_query\app\listener\notice\QueryFail',
        ],
        'GetPosterType' => [
            'addon\hsx_phone_query\app\listener\poster\QueryReportPosterType',
        ],
        'GetPosterData' => [
            'addon\hsx_phone_query\app\listener\poster\QueryReportPoster',
        ],
    ],
    'subscribe' => [
    ],
];
