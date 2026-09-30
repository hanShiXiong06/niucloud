<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

/** 向 ERP 贡献当前站点可用的小程序销售业务来源。 */
class ErpBusinessSourceOptionsListener
{
    public function handle(array $params = []): array
    {
        if ((int)($params['site_id'] ?? 0) <= 0) return [];
        return [
            'sources' => [[
                'key' => 'phone_shop.mini_program_sale',
                'name' => '小程序销售',
                'direction' => 'income',
                'scene' => 'sale',
                'source_plugin' => 'phone_shop',
                'source_key' => 'mini_program_sale',
                'enabled' => 1,
                'sort' => 150,
            ]],
        ];
    }
}
