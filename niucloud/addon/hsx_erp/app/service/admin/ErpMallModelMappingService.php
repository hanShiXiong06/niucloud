<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** 本站“来源标准型号→商城分类”记忆。只在成功上架后学习，不修改来源型号或商城分类。 */
final class ErpMallModelMappingService
{
    public const CONFIG_PREFIX = 'HSX_ERP_MALL_MODEL_V1_';

    private function decode($value): array
    {
        if (is_array($value)) return $value;
        $decoded = is_string($value) ? json_decode($value, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public function sourceCatalog(int $siteId, array $asset): array
    {
        $meta = $this->decode($asset['spec_json'] ?? []);
        $catalog = $this->decode($meta['source_catalog'] ?? []);
        if ($catalog === []) {
            $source = (string)($meta['source_plugin'] ?? $asset['source_plugin'] ?? '');
            $deviceId = (int)($meta['source_device_id'] ?? 0);
            if ($source === '' || $deviceId <= 0) return [];
            try {
                foreach ((array)event('HsxErpSourceCatalogRequested', ['site_id' => $siteId, 'source_plugin' => $source, 'source_device_id' => $deviceId]) as $response) {
                    if (!is_array($response) || ($response['provider'] ?? '') !== $source) continue;
                    $catalog = $this->decode($response['catalog'] ?? []);
                    break;
                }
            } catch (\Throwable) { return []; }
        }
        $plugin = (string)($catalog['source_plugin'] ?? '');
        $namespace = (string)($catalog['namespace'] ?? '');
        $owner = $catalog['owner_site_id'] ?? null;
        if ($siteId <= 0 || $plugin === '' || $plugin !== (string)($meta['source_plugin'] ?? $asset['source_plugin'] ?? '')
            || !preg_match('/^[a-z0-9_.:-]{1,100}$/D', $namespace) || !is_int($owner) || !in_array($owner, [0, $siteId], true)
            || !is_int($catalog['model_id'] ?? null) || $catalog['model_id'] <= 0) return [];
        return ['source_plugin' => $plugin, 'namespace' => $namespace, 'owner_site_id' => $owner,
            'model_id' => $catalog['model_id'], 'model_name' => trim((string)($catalog['model_name'] ?? '')),
            'brand_name' => trim((string)($catalog['brand_name'] ?? ''))];
    }

    private function identity(int $siteId, array $catalog): array
    {
        return ['site_id' => $siteId, 'source_plugin' => $catalog['source_plugin'], 'namespace' => $catalog['namespace'], 'model_id' => $catalog['model_id']];
    }

    private function key(array $identity): string
    {
        return self::CONFIG_PREFIX . hash('sha1', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function read(int $siteId, string $key): array
    {
        $rows = Db::name('sys_config')->where('site_id', $siteId)->where('config_key', $key)->master()->field('id,value,status')->limit(2)->select()->toArray();
        if ($rows === []) return [];
        if (count($rows) !== 1) throw new CommonException('该标准型号存在多份分类对应记录，请管理员核对；本次不自动选择');
        if ((int)$rows[0]['status'] !== 1) throw new CommonException('该标准型号的分类对应已停用，请手动核对');
        return ['row_id' => (int)$rows[0]['id'], 'value' => $this->decode($rows[0]['value'])];
    }

    public function suggestion(int $siteId, array $asset): array
    {
        $base = ['category_id' => 0, 'label' => '', 'brand_id' => 0, 'brand_name' => '', 'source_name' => '', 'status' => 'unavailable', 'message' => '未取得来源标准型号，请手动选择；不会根据相似名称猜测'];
        try {
            $explicit = ErpMallListingService::selection($asset['spec_json'] ?? []);
            if ((int)($explicit['value'] ?? 0) > 0) {
                $selection = ErpMallListingService::request($siteId, 'resolve', (int)$explicit['value'])['selection'] ?? [];
                return array_replace($base, ['category_id' => (int)$selection['value'], 'label' => (string)$selection['label'],
                    'brand_id' => (int)(ErpMallListingService::material($asset['spec_json'] ?? [])['brand']['id'] ?? 0),
                    'brand_name' => (string)(ErpMallListingService::material($asset['spec_json'] ?? [])['brand']['name'] ?? ''),
                    'source_name' => '本台已确认', 'status' => 'explicit', 'message' => '沿用本台已确认的商城分类']);
            }
            $catalog = $this->sourceCatalog($siteId, $asset);
            if ($catalog === []) return $base;
            $base['source_name'] = '回收标准型号：' . $catalog['model_name'];
            $identity = $this->identity($siteId, $catalog);
            $record = $this->read($siteId, $this->key($identity));
            if ($record === []) {
                $brand = ErpMallListingService::request($siteId, 'resolve_brand', 0, ['brand_name' => $catalog['brand_name']])['brand'] ?? [];
                return array_replace($base, ['brand_id' => (int)($brand['id'] ?? 0), 'brand_name' => (string)($brand['name'] ?? ''), 'status' => 'unmapped', 'message' => '首次上架请选择商城分类，成功后会供本站同型号设备复用']);
            }
            $value = $record['value'];
            if (($value['identity'] ?? []) !== $identity || ($value['status'] ?? '') !== 'active') {
                return array_replace($base, ['status' => 'conflict', 'message' => '该标准型号曾对应不同商城分类或品牌，请管理员核对；本次不自动选择']);
            }
            $selection = ErpMallListingService::request($siteId, 'resolve', (int)($value['category_id'] ?? 0))['selection'] ?? [];
            $brandId = (int)($value['brand_id'] ?? 0); $brandName = ''; $message = '已带入本站同型号成功上架时确认的分类，请核对后上架';
            if ($brandId > 0) {
                try { $brandName = (string)(ErpMallListingService::request($siteId, 'resolve_brand', 0, ['brand_id' => $brandId])['brand']['name'] ?? ''); }
                catch (\Throwable) { $brandId = 0; $message .= '；原品牌已失效，请重新选择品牌'; }
            }
            return array_replace($base, ['category_id' => (int)$selection['value'], 'label' => (string)$selection['label'], 'brand_id' => $brandId, 'brand_name' => $brandName, 'status' => 'ready', 'message' => $message]);
        } catch (\Throwable $e) {
            return array_replace($base, ['status' => 'invalid', 'message' => $e->getMessage()]);
        }
    }

    public function recordPublished(int $siteId, array $asset, array $selection, array $material, int $goodsId): array
    {
        $catalog = $this->sourceCatalog($siteId, $asset);
        if ($catalog === [] || (int)($selection['value'] ?? 0) <= 0 || $goodsId <= 0) return ['status' => 'skipped'];
        $identity = $this->identity($siteId, $catalog); $key = $this->key($identity);
        $connection = Db::connect();
        $scope = (string)$connection->getConfig('database') . ':' . (string)$connection->getConfig('prefix');
        $lockKey = 'erp_model:' . substr(hash('sha256', $scope . ':' . $key), 0, 40);
        $locked = $connection->query('SELECT GET_LOCK(:lock_key, 3) AS acquired', ['lock_key' => $lockKey], true);
        if ((int)($locked[0]['acquired'] ?? 0) !== 1) throw new CommonException('同型号分类对应正在保存，本次商品已上架，请稍后核对映射');
        try {
            $record = $this->read($siteId, $key); $old = $record['value'] ?? [];
            $categoryId = (int)$selection['value']; $brandId = (int)($material['brand']['id'] ?? 0);
            $value = ['version' => 1, 'identity' => $identity, 'source_name' => $catalog['model_name'], 'category_id' => $categoryId,
                'brand_id' => $brandId, 'goods_id' => $goodsId, 'status' => 'active', 'updated_at' => time()];
            if ($old !== []) {
                if (($old['identity'] ?? []) !== $identity || ($old['status'] ?? '') !== 'active'
                    || (int)($old['category_id'] ?? 0) !== $categoryId
                    || ((int)($old['brand_id'] ?? 0) > 0 && $brandId > 0 && (int)$old['brand_id'] !== $brandId)) {
                    $value = array_replace($old, ['status' => 'conflict', 'last_conflict' => ['category_id' => $categoryId, 'brand_id' => $brandId, 'goods_id' => $goodsId], 'updated_at' => time()]);
                } elseif ((int)($old['brand_id'] ?? 0) > 0 && $brandId === 0) $value['brand_id'] = (int)$old['brand_id'];
            }
            $data = ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'update_time' => time()];
            if (isset($record['row_id'])) Db::name('sys_config')->where('id', $record['row_id'])->where('site_id', $siteId)->update($data);
            else Db::name('sys_config')->insert($data + ['site_id' => $siteId, 'config_key' => $key, 'status' => 1, 'create_time' => time(), 'addon' => 'hsx_erp']);
            return ['status' => $value['status'], 'message' => $value['status'] === 'conflict' ? '商品已上架，但同型号分类对应存在冲突，后续不会自动带入，请管理员核对' : '已记住本站标准型号与商城分类的对应'];
        } finally {
            try { $connection->query('SELECT RELEASE_LOCK(:lock_key) AS released', ['lock_key' => $lockKey], true); }
            catch (\Throwable) { Log::warning('[ERP 同型号商城分类映射锁释放失败] site=' . $siteId); }
        }
    }
}
