<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxAiIntegrationRegistryRequested' => [
            'addon\recycle_daheng_quote\app\listener\ai\AiIntegrationRegistryRequested',
        ],
        'HsxAiSkillRegistryRequested' => [
            'addon\recycle_daheng_quote\app\listener\ai\AiSkillRegistryRequested',
        ],
        'HsxAiBusinessContextRequested' => [
            'addon\recycle_daheng_quote\app\listener\ai\AiQuoteBusinessContextRequested',
        ],
    ],
];
