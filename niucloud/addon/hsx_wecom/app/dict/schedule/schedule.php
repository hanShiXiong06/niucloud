<?php
declare(strict_types=1);

return [
    [
        'key' => 'hsx_wecom_message_retry',
        'name' => '企业微信消息补偿',
        'desc' => '重试任务分配等企业微信应用消息',
        'time' => ['type' => 'min', 'min' => 1],
        'class' => 'addon\hsx_wecom\app\job\schedule\MessageRetry',
        'function' => 'doJob',
    ],
];
