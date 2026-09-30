<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

final class AiToolRegistryRequested
{
    public function handle(array $event): array
    {
        $common = [
            'source_plugin' => 'phone_shop',
            'integration_key' => 'phone_shop',
            'scenes' => ['phone_shop.customer_assistant'],
            'auth' => 'public',
            'permissions' => [],
            'read_only' => true,
        ];
        return [
            array_merge($common, [
                'key' => 'phone_shop.category.list',
                'name' => '查询商城商品分类',
                'description' => '查询当前站点前台可见的商品分类、系列层级和稳定分类 ID，用于理解客户想看的品类或进一步收窄选机范围。',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'maxLength' => 100],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                    ],
                    'additionalProperties' => false,
                ],
            ]),
            array_merge($common, [
                'key' => 'phone_shop.goods.search',
                'name' => '查询商城在售商品',
                'description' => '按客户自然语言需求查询当前站点实时在售商品、当前账号实际可购价、普通售价、会员等级价、库存、商品说明、公开参数、服务和公开质检结果。',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string', 'maxLength' => 500]],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ]),
            [
                'key' => 'phone_shop.listing.summary',
                'name' => '查询二手机商城货盘变动',
                'description' => '按今天、本周或本月查询一机一品商城的上新、离架、已售、锁定和当前可售数量。上新按创建时间，离架按更新时间且 status=0 统计。',
                'source_plugin' => 'phone_shop',
                'integration_key' => 'phone_shop',
                'scenes' => ['business.admin_assistant'],
                'auth' => 'admin',
                'permissions' => ['shop_goods_list'],
                'read_only' => true,
                'risk_level' => 'read',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['period' => ['type' => 'string', 'enum' => ['today', 'week', 'month']]],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }
}
