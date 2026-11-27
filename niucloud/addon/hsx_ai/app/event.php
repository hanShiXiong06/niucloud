<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxAiExecuteRequested' => [
            'addon\hsx_ai\app\listener\AiExecuteRequested',
        ],
        'HsxAiAgentExecuteRequested' => [
            'addon\hsx_ai\app\listener\AiAgentExecuteRequested',
        ],
        'HsxAiCapabilityRequested' => [
            'addon\hsx_ai\app\listener\AiCapabilityRequested',
        ],
        'HsxAiIntegrationAccessRequested' => [
            'addon\hsx_ai\app\listener\AiIntegrationAccessRequested',
        ],
        'DiyComponent' => [
            'addon\hsx_ai\app\listener\diy\DiyComponentListener',
        ],
    ],
];
