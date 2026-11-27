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

namespace addon\hsx_phone_query\app\service\admin\hsx_phone_query_category;

use addon\hsx_phone_query\app\dict\HsxPhoneQueryCategoryDict;
use addon\hsx_phone_query\app\dict\HsxPhoneQueryConfigDict;
use addon\hsx_phone_query\app\model\HsxPhoneQueryCategory;
use addon\hsx_phone_query\app\service\admin\hsx_phone_query_config\HsxPhoneQueryConfigService;

use core\base\BaseAdminService;
use core\exception\CommonException;


/**
 * 分类服务层
 * Class HsxPhoneQueryCategoryService
 * @package addon\hsx_phone_query\app\service\admin\hsx_phone_query_category
 */
class HsxPhoneQueryCategoryService extends BaseAdminService
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

        $field = 'id,site_id,channel_key,channel_name,type_id,service_code,query_param,name,price,cost_price,is_show,sort';
        $order = 'sort desc,id desc';
        $condition = [[ 'site_id' ,"=", $this->site_id ]];
        if (!empty($where['channel_key'])) {
            $condition[] = ['channel_key', '=', (string)$where['channel_key']];
        }
        if (!empty($where['service_code'])) {
            $condition[] = ['service_code', '=', (string)$where['service_code']];
        }
        if (!empty($where['query_param'])) {
            $condition[] = ['query_param', '=', (string)$where['query_param']];
        }
        $channelServiceCodes = $this->getServiceCodesByChannelFilter($where);
        if (is_array($channelServiceCodes)) {
            $condition[] = ['service_code', 'in', !empty($channelServiceCodes) ? $channelServiceCodes : ['__none__']];
        }

        $search_model = $this->model->where($condition)->withSearch(["id","type_id","name","price","is_show"], $where)->field($field)->order($order);
        
        $list = $this->pageQuery($search_model);
        if (!empty($list['data'])) {
            $config = (new HsxPhoneQueryConfigService())->getProviderConfig();
            foreach ($list['data'] as &$item) {
                $item['channel_status'] = $this->buildChannelStatus((string)($item['service_code'] ?? ''), $config);
            }
        }
        return $list;
    }

    /**
     * 获取分类信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,channel_key,channel_name,type_id,service_code,query_param,name,price,cost_price,is_show,sort';

        $info = $this->model->field($field)->where([
            ['id', "=", $id],
            ['site_id', "=", $this->site_id]
        ])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加分类
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data = $this->fillDefaultQueryMeta($data);
        $data['site_id'] = $this->site_id;
        $this->checkDuplicateService($data['channel_key'], $data['service_code']);
        // 获取最大排序值
        $max_sort = $this->model->where([['site_id', '=', $this->site_id]])->max('sort');
        $data['sort'] = $max_sort + 10;
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 修改排序
     * @param int $id
     * @param int $sort
     * @return bool
     */
    public function modifySort(int $id, int $sort)
    {
        $model = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();
        if ($model->isEmpty()) {
            throw new CommonException('查询项目不存在');
        }

        $model->save(['sort' => $sort]);
        return true;
    }

    /**
     * 修改显示状态
     * @param int $id
     * @param int $is_show
     * @return bool
     */
    public function modifyShow(int $id, int $is_show)
    {
        $model = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();
        if ($model->isEmpty()) {
            throw new CommonException('查询项目不存在');
        }

        $model->save(['is_show' => $is_show ? 1 : 0]);
        return true;
    }

    /**
     * 分类编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {
        $model = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();
        if ($model->isEmpty()) {
            throw new CommonException('查询项目不存在');
        }

        $data = $this->fillDefaultQueryMeta($data);
        if ($data['is_show'] === '') {
            unset($data['is_show']);
        } else {
            $data['is_show'] = $data['is_show'] ? 1 : 0;
        }
        if ($data['sort'] === '') {
            unset($data['sort']);
        } else {
            $data['sort'] = (int)$data['sort'];
        }
        $this->checkDuplicateService($data['channel_key'], $data['service_code'], $id);
        $model->save($this->filterSaveData($data, [
            'channel_key',
            'channel_name',
            'type_id',
            'service_code',
            'query_param',
            'name',
            'price',
            'cost_price',
            'is_show',
            'sort',
        ]));
        return true;
    }

    /**
     * 删除分类
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();
        if (!$model) {
            return false;
        }
        $res = $model->delete();
        return $res;
    }

    private function ensureDefaultItems(): void
    {
        $existingRows = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->field('channel_key,service_code')
            ->select()
            ->toArray();
        $existingKeys = [];
        foreach ($existingRows as $row) {
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

    private function fillDefaultQueryMeta(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $serviceCode = trim((string)($data['service_code'] ?? ''));
        $channelKey = trim((string)($data['channel_key'] ?? ''));
        foreach (HsxPhoneQueryCategoryDict::defaultItems() as $item) {
            if ($name !== '' && $name === $item['name'] && ($channelKey === '' || $channelKey === (string)($item['channel_key'] ?? ''))) {
                $serviceCode = $serviceCode ?: $item['service_code'];
                $data['query_param'] = $data['query_param'] ?: $item['query_param'];
                $data['channel_key'] = $data['channel_key'] ?: $item['channel_key'];
                $data['channel_name'] = $data['channel_name'] ?: $item['channel_name'];
                break;
            }
        }

        $data['service_code'] = $serviceCode;
        $data['channel_key'] = $data['channel_key'] ?: 'gkdt_main';
        $data['channel_name'] = $data['channel_name'] ?: $this->getChannelName($data['channel_key']);
        $data['query_param'] = $data['query_param'] ?: 'sn';
        $data['cost_price'] = $data['cost_price'] ?? 0;
        return $data;
    }

    private function filterSaveData(array $data, array $fields): array
    {
        $saveData = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $saveData[$field] = $data[$field];
            }
        }

        return $saveData;
    }

    private function checkDuplicateService(string $channelKey, string $serviceCode, int $ignoreId = 0): void
    {
        $query = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['channel_key', '=', $channelKey],
            ['service_code', '=', $serviceCode],
        ]);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new CommonException('当前渠道下已存在相同服务编码的查询项目');
        }
    }

    private function getChannelName(string $channelKey): string
    {
        foreach ((new HsxPhoneQueryConfigService())->getProviderConfig()['channels'] ?? [] as $channel) {
            if ((string)($channel['key'] ?? '') === $channelKey) {
                return (string)($channel['name'] ?? $channelKey);
            }
        }

        return $channelKey;
    }

    private function getServiceCodesByChannelFilter(array $where): ?array
    {
        $channelKey = trim((string)($where['channel_key'] ?? ''));
        $configuredStatus = $where['configured_status'] ?? '';
        if ($channelKey === '' && $configuredStatus === '') {
            return null;
        }

        $config = (new HsxPhoneQueryConfigService())->getProviderConfig();
        $serviceCodes = [];
        foreach ($config['mappings'] ?? [] as $mapping) {
            if ($channelKey !== '' && (string)($mapping['channel_key'] ?? '') !== $channelKey) {
                continue;
            }

            $enabled = !empty($mapping['enabled']);
            $channel = $this->getChannel($config, (string)($mapping['channel_key'] ?? ''));
            $ready = $enabled && HsxPhoneQueryConfigDict::isChannelReady($channel);
            if ($configuredStatus !== '' && (int)$configuredStatus !== (int)$ready) {
                continue;
            }

            $serviceCode = (string)($mapping['service_code'] ?? '');
            if ($serviceCode !== '') {
                $serviceCodes[] = $serviceCode;
            }
        }

        return array_values(array_unique($serviceCodes));
    }

    private function buildChannelStatus(string $serviceCode, array $config): array
    {
        $rows = [];
        foreach ($config['channels'] ?? [] as $channel) {
            $channelKey = (string)($channel['key'] ?? '');
            if ($channelKey === '') {
                continue;
            }

            $mapping = $this->getMapping($config, $serviceCode, $channelKey);
            $mapped = !empty($mapping);
            $mappingEnabled = $mapped && !empty($mapping['enabled']);
            $channelReady = HsxPhoneQueryConfigDict::isChannelReady($channel);

            $rows[] = [
                'channel_key' => $channelKey,
                'channel_name' => (string)($channel['name'] ?? $channelKey),
                'provider_key' => (string)($channel['provider'] ?? ''),
                'mapped' => $mapped ? 1 : 0,
                'enabled' => $mappingEnabled ? 1 : 0,
                'ready' => ($mappingEnabled && $channelReady) ? 1 : 0,
                'endpoint_type' => (string)($mapping['endpoint_type'] ?? ''),
                'endpoint_value' => (string)($mapping['endpoint_value'] ?? ''),
                'query_param' => (string)($mapping['query_param'] ?? $channel['query_param'] ?? ''),
                'cost_price' => round((float)($mapping['cost_price'] ?? 0), 3),
            ];
        }

        return $rows;
    }

    private function getMapping(array $config, string $serviceCode, string $channelKey): array
    {
        foreach ($config['mappings'] ?? [] as $mapping) {
            if ((string)($mapping['service_code'] ?? '') === $serviceCode && (string)($mapping['channel_key'] ?? '') === $channelKey) {
                return $mapping;
            }
        }

        return [];
    }

    private function getChannel(array $config, string $channelKey): array
    {
        foreach ($config['channels'] ?? [] as $channel) {
            if ((string)($channel['key'] ?? '') === $channelKey) {
                return $channel;
            }
        }

        return [];
    }
    
}
