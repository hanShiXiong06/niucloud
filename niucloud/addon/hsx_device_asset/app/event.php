<?php

return [
    'bind' => [],
    'listen' => [
        'ErpDomainEvent' => [
            'addon\hsx_device_asset\app\listener\ErpAssetReadyForPhotoListener',
        ],
    ],
    'subscribe' => [],
];
