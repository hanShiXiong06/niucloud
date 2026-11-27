<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

/**
 * 向 hsx_erp 动态贡献商城/小程序销售渠道。
 * 监听器是否被装配由牛云站点套餐中的插件集合决定；插件内部若再拆分套餐，可在这里继续校验后返回空数组。
 */
class ErpSaleChannelOptionsListener
{
    public function handle(array $params = []): array
    {
        if ((int)($params['site_id'] ?? 0) <= 0) return [];
        return [
            'channels' => [[
                'key' => 'phone_shop_mini_program',
                'name' => '小程序商城',
                'channel_type' => 'platform',
                'source_plugin' => 'phone_shop',
                'source_key' => 'mini_program_order',
                'enabled' => 1,
                'is_default' => 0,
                'sort' => 85,
            ]],
        ];
    }
}
