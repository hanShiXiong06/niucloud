<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxBusinessTaskAssigned' => [
            'addon\hsx_wecom\app\listener\BusinessTaskAssigned',
        ],
        'HsxBusinessReportGenerated' => [
            'addon\hsx_wecom\app\listener\BusinessReportGenerated',
        ],
    ],
];
