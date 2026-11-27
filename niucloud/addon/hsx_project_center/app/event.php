<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxAiIntegrationRegistryRequested' => [
            'addon\hsx_project_center\app\listener\ai\AiIntegrationRegistryRequested',
        ],
        'HsxAiBusinessContextRequested' => [
            'addon\hsx_project_center\app\listener\ai\AiProjectBusinessContextRequested',
        ],
        'NoticeData' => [
            'addon\hsx_project_center\app\listener\notice_template\ApplicationResult',
        ],
        'GetPosterType' => [
            'addon\hsx_project_center\app\listener\poster\ProjectCenterDistributionPosterType',
        ],
        'GetPosterData' => [
            'addon\hsx_project_center\app\listener\poster\ProjectCenterDistributionPoster',
        ],
    ],
];
