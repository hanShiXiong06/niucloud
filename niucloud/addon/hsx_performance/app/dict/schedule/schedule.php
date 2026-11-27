<?php
declare(strict_types=1);

return [
    [
        'key' => 'hsx_performance_report_tick',
        'name' => '经营报告生成与通知补偿',
        'desc' => '按站点生成日报、周报、月报，并补偿失败的报告通知',
        'time' => ['type' => 'min', 'min' => 1],
        'class' => 'addon\hsx_performance\app\job\schedule\ReportTick',
        'function' => 'doJob',
    ],
];
