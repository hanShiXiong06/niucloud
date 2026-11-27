<?php
declare(strict_types=1);

namespace addon\hsx_phone_query\app\service\core\item;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryCategoryDict;
use addon\hsx_phone_query\app\model\HsxPhoneQueryCategory;
use core\base\BaseCoreService;

/**
 * 查询项目初始化/同步服务
 */
class QueryItemImportService extends BaseCoreService
{
    private HsxPhoneQueryCategory $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new HsxPhoneQueryCategory();
    }

    public function sync(int $siteId, bool $overwriteSaleConfig = false): array
    {
        $siteId = max(0, $siteId);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $time = time();

        foreach (HsxPhoneQueryCategoryDict::defaultItems() as $item) {
            $channelKey = (string)($item['channel_key'] ?? '');
            $serviceCode = (string)($item['service_code'] ?? '');
            if ($channelKey === '' || $serviceCode === '') {
                $skipped++;
                continue;
            }

            $old = $this->model->where([
                ['site_id', '=', $siteId],
                ['channel_key', '=', $channelKey],
                ['service_code', '=', $serviceCode],
            ])->findOrEmpty();

            if ($old->isEmpty()) {
                $this->model->create(array_merge($item, [
                    'site_id' => $siteId,
                    'create_time' => $time,
                    'update_time' => $time,
                ]));
                $created++;
                continue;
            }

            $update = [
                'channel_name' => (string)($item['channel_name'] ?? ''),
                'type_id' => (int)($item['type_id'] ?? 0),
                'query_param' => (string)($item['query_param'] ?? 'sn'),
                'update_time' => $time,
            ];

            if ($overwriteSaleConfig) {
                $update['name'] = (string)($item['name'] ?? '');
                $update['price'] = (float)($item['price'] ?? 0);
                $update['cost_price'] = (float)($item['cost_price'] ?? 0);
                $update['is_show'] = (int)($item['is_show'] ?? 1);
                $update['sort'] = (int)($item['sort'] ?? 0);
            }

            $old->save($update);
            $updated++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'overwrite_sale_config' => $overwriteSaleConfig ? 1 : 0,
        ];
    }

    public function repairChannelNames(int $siteId): array
    {
        $siteId = max(0, $siteId);
        $fixed = 0;
        $defaultMap = [];
        foreach (HsxPhoneQueryCategoryDict::defaultItems() as $item) {
            $key = (string)($item['channel_key'] ?? '') . '|' . (string)($item['service_code'] ?? '');
            if ($key !== '|') {
                $defaultMap[$key] = $item;
            }
        }

        $rows = $this->model->where([['site_id', '=', $siteId]])->field('id,channel_key,channel_name,service_code,query_param,type_id')->select();
        foreach ($rows as $row) {
            $key = (string)$row['channel_key'] . '|' . (string)$row['service_code'];
            if (empty($defaultMap[$key])) {
                continue;
            }

            $default = $defaultMap[$key];
            $update = [];
            foreach (['channel_name', 'query_param', 'type_id'] as $field) {
                if ((string)($row[$field] ?? '') !== (string)($default[$field] ?? '')) {
                    $update[$field] = $default[$field];
                }
            }

            if (!empty($update)) {
                $update['update_time'] = time();
                $row->save($update);
                $fixed++;
            }
        }

        return ['fixed' => $fixed];
    }
}

