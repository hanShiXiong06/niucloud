<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\erp;

/** 向 ERP 贡献回收设备采购业务来源。 */
class ErpBusinessSourceOptionsListener
{
    public function handle(array $params = []): array
    {
        if ((int)($params['site_id'] ?? 0) <= 0) return [];
        return [
            'sources' => [[
                'key' => 'hsx_recycle.recycle_purchase',
                'name' => '回收插件采购',
                'direction' => 'expense',
                'scene' => 'purchase',
                'source_plugin' => 'hsx_recycle',
                'source_key' => 'recycle_purchase',
                'enabled' => 1,
                'sort' => 140,
            ]],
        ];
    }
}
