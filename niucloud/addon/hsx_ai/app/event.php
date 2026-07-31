<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxAiExecuteRequested' => [
            'addon\hsx_ai\app\listener\AiExecuteRequested',
        ],
        'HsxAiCapabilityRequested' => [
            'addon\hsx_ai\app\listener\AiCapabilityRequested',
        ],
        'HsxAiIntegrationAccessRequested' => [
            'addon\hsx_ai\app\listener\AiIntegrationAccessRequested',
        ],
    ],
];
