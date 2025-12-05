<?php

return [
    [
        'key' => 'ai_image_async_create',
        'name' => 'AI设计同步状态',
        'desc' => '',
        'time' => [
            'type' => 'min',
            'min' => 1
        ],
        'class' => 'addon\ai_image\app\job\async\AsyncCreateJob',
        'function' => ''
    ],
];
