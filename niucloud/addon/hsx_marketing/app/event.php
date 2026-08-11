<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxMarketingFactRecorded' => ['addon\hsx_marketing\app\listener\MarketingFactRecorded'],
        'NoticeData' => ['addon\hsx_marketing\app\listener\notice\MarketingRewardNotice'],
    ],
];
