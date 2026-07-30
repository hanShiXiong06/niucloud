<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxPerformanceFactRecorded' => [
            'addon\hsx_performance\app\listener\PerformanceFactRecorded',
        ],
        'ErpDomainEvent' => [
            'addon\hsx_performance\app\listener\ErpDomainPerformance',
        ],
    ],
];
