<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use think\facade\Db;

/** 回收只输出自己的稳定型号身份；不读取 ERP 或商城的表。 */
final class RecycleErpSourceCatalog
{
    public static function fromModel(int $siteId, int $modelId): array
    {
        if ($siteId <= 0 || $modelId <= 0) return [];
        $node = Db::name('recycle_device_model_dict')->where('id', $modelId)->whereIn('site_id', [$siteId, 0])->where('status', 1)->find();
        if (!$node) return [];
        $owner = (int)$node['site_id'];
        if (Db::name('recycle_device_model_dict')->where('site_id', $owner)->where('pid', $modelId)->where('status', 1)->count() > 0) return [];
        $visited = []; $cursor = $node; $brand = '';
        while ($cursor) {
            $id = (int)$cursor['id'];
            if (isset($visited[$id]) || (int)$cursor['site_id'] !== $owner || (int)$cursor['status'] !== 1) return [];
            $visited[$id] = true;
            if (($cursor['node_type'] ?? '') === 'brand') $brand = trim((string)$cursor['node_name']);
            $pid = (int)$cursor['pid'];
            if ($pid <= 0) break;
            $cursor = Db::name('recycle_device_model_dict')->where('id', $pid)->where('site_id', $owner)->find();
            if (!$cursor) return [];
        }
        return ['source_plugin' => 'hsx_recycle', 'namespace' => 'recycle_device_model_dict:' . $owner,
            'owner_site_id' => $owner, 'model_id' => $modelId,
            'model_name' => trim((string)($node['model_full_name'] ?? $node['node_name'] ?? '')),
            'brand_name' => $brand];
    }
}
