<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\hsx_phone_query\app\service\api\hsx_phone_query;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryCategoryDict;
use addon\hsx_phone_query\app\model\HsxPhoneQueryCategory;
use addon\hsx_phone_query\app\service\core\provider\ProviderChannelService;

use core\base\BaseApiService;


/**
 * 分类服务层
 * Class HsxPhoneQueryCategoryService
 * @package addon\hsx_phone_query\app\service\admin\hsx_phone_query_category
 */
class HsxPhoneQueryCategoryService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new HsxPhoneQueryCategory();
    }

    /**
     * 获取分类列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $this->ensureDefaultItems();

        $field = 'id,channel_key,type_id,service_code,query_param,name,price,sort';
        $providerContext = (new ProviderChannelService())->getActiveContext($this->site_id);
        if (empty($providerContext['enabled_service_codes']) || empty($providerContext['provider_item_map'])) {
            return $this->emptyTypeTree();
        }

        $enabledServiceCodes = array_values(array_intersect(
            $providerContext['enabled_service_codes'],
            array_keys($providerContext['provider_item_map'])
        ));
        if (empty($enabledServiceCodes)) {
            return $this->emptyTypeTree();
        }

        $list = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['channel_key', '=', $providerContext['channel_key']],
                ['is_show', '=', 1],
            ])
            ->where('service_code', 'in', $enabledServiceCodes)
            ->withSearch(['id', 'type_id', 'name', 'price'], $where)
            ->field($field)
            ->select()
            ->toArray();

        usort($list, function ($a, $b) use ($providerContext) {
            $left = (int)($a['sort'] ?? 0);
            $right = (int)($b['sort'] ?? 0);
            if ((int)$left === (int)$right) {
                return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
            }

            return (int)$right <=> (int)$left;
        });

        $list = array_values(array_filter($list, function ($item) use ($providerContext) {
            return isset($providerContext['provider_item_map'][(string)($item['service_code'] ?? '')]);
        }));

        $children = [];
        foreach (HsxPhoneQueryCategoryDict::types() as $type) {
            $children[$type['id']] = [];
        }

        foreach ($list as $item) {
            $serviceCode = (string)($item['service_code'] ?? '');
            $providerItem = $providerContext['provider_item_map'][$serviceCode] ?? [];
            if (empty($providerItem)) {
                continue;
            }

            $typeId = (int)($providerItem['type_id'] ?? $item['type_id']);
            if (!isset($children[$typeId])) {
                continue;
            }

            $children[$typeId][] = [
                'id' => (int)$item['id'],
                'type_id' => $typeId,
                'name' => (string)($item['name'] ?: ($providerItem['name'] ?? '')),
                'price' => $this->formatMoney($item['price'] ?? 0),
                'service_code' => $serviceCode,
                'channel_key' => $providerContext['channel_key'],
                'channel_name' => $providerContext['channel_name'],
                'query_param' => (string)($item['query_param'] ?: ($providerItem['query_param'] ?? $providerContext['query_param'])),
            ];
        }

        $result = [];
        foreach (HsxPhoneQueryCategoryDict::types() as $type) {
            if (empty($children[$type['id']])) {
                continue;
            }
            $result[] = [
                'id' => $type['id'],
                'name' => $type['name'],
                'child' => $children[$type['id']] ?? [],
            ];
        }

        return $result;
    }

    private function emptyTypeTree(): array
    {
        return [];
    }

    private function ensureDefaultItems(): void
    {
        $existingServiceCodes = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->field('channel_key,service_code')
            ->select()
            ->toArray();
        $existingKeys = [];
        foreach ($existingServiceCodes as $row) {
            $channelKey = (string)($row['channel_key'] ?? '');
            $serviceCode = (string)($row['service_code'] ?? '');
            if ($channelKey !== '' && $serviceCode !== '') {
                $existingKeys[$channelKey . '|' . $serviceCode] = true;
            }
        }

        $time = time();
        $rows = [];
        foreach (HsxPhoneQueryCategoryDict::defaultItems() as $item) {
            $key = (string)($item['channel_key'] ?? '') . '|' . (string)($item['service_code'] ?? '');
            if ($key === '|' || isset($existingKeys[$key])) {
                continue;
            }

            $rows[] = array_merge($item, [
                'site_id' => $this->site_id,
                'create_time' => $time,
                'update_time' => $time,
            ]);
        }

        if (!empty($rows)) {
            $this->model->insertAll($rows);
        }
    }

    private function formatMoney($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    
}
