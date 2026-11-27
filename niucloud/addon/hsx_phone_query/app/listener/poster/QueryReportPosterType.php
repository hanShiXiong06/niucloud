<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\listener\poster;

/**
 * 手机查询报告海报类型
 */
class QueryReportPosterType
{
    public function handle($data = []): array
    {
        return [
            [
                'type' => 'hsx_phone_query_report',
                'addon' => 'hsx_phone_query',
                'name' => '设备查询报告海报',
                'desc' => '分享设备查询报告，扫码查看详情并绑定推广关系',
                'icon' => 'addon/hsx_phone_query/icon.png',
            ],
        ];
    }
}
