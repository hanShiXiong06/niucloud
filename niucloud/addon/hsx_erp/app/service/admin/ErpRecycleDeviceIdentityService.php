<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\support\ErpRecycleDeviceIdentity;
use core\exception\CommonException;

/** 只读关联解析；历史 JSON 可按站点扫描，任何查询异常都向上抛出。 */
class ErpRecycleDeviceIdentityService
{
    public function deviceAssets(int $siteId, array $deviceIds): array
    {
        if ($siteId <= 0) throw new CommonException('回收设备关联查询缺少有效站点');
        foreach ($deviceIds as $deviceId) {
            if (ErpRecycleDeviceIdentity::positiveId($deviceId) <= 0) throw new CommonException('来源设备ID无效，已拒绝整批处理');
        }
        $deviceIds = array_values(array_unique(array_map([ErpRecycleDeviceIdentity::class, 'positiveId'], $deviceIds)));
        $map = [];
        foreach ($deviceIds as $id) $map[$id] = ['has_asset' => false, 'ambiguous' => false, 'asset_ids' => [], 'assets' => []];
        if ($map === []) return [];

        $assets = $this->loadAssets($siteId);
        $items = $this->loadPurchaseItems($siteId, array_map('intval', array_column($assets, 'id')));
        $itemMap = [];
        foreach ($items as $item) $itemMap[(int)($item['asset_id'] ?? 0)][] = $item;
        foreach ($assets as $asset) {
            if ((int)($asset['site_id'] ?? 0) !== $siteId) continue;
            $assetId = (int)($asset['id'] ?? 0);
            if ($assetId <= 0) continue;
            $identity = ErpRecycleDeviceIdentity::resolve($asset, $itemMap[$assetId] ?? []);
            foreach ($identity['candidate_device_ids'] as $deviceId) {
                if (!isset($map[$deviceId])) continue;
                $map[$deviceId]['assets'][$assetId] = $asset;
                $map[$deviceId]['ambiguous'] = $map[$deviceId]['ambiguous'] || $identity['ambiguous'];
            }
        }
        foreach ($map as &$entry) {
            $entry['assets'] = array_values($entry['assets']);
            $entry['asset_ids'] = array_map('intval', array_column($entry['assets'], 'id'));
            sort($entry['asset_ids'], SORT_NUMERIC);
            $entry['has_asset'] = $entry['asset_ids'] !== [];
            $entry['ambiguous'] = $entry['ambiguous'] || count($entry['asset_ids']) > 1;
        }
        unset($entry);
        return $map;
    }

    public function uniqueAssets(int $siteId, array $deviceIds): array
    {
        $map = $this->deviceAssets($siteId, $deviceIds);
        $assets = [];
        foreach ($map as $entry) {
            if ($entry['ambiguous']) throw new CommonException('回收设备关联存在歧义，请先核对资产关联，已停止付款');
            if (!$entry['has_asset']) throw new CommonException('部分设备尚未建立明确ERP资产关联，已拒绝整批付款');
            $assets[] = $entry['assets'][0];
        }
        return $assets;
    }

    public function assetDeviceId(array $asset, string $operation = '销售'): int
    {
        if ((string)($asset['source_plugin'] ?? '') !== 'hsx_recycle') return 0;
        $siteId = (int)($asset['site_id'] ?? 0);
        if ($siteId <= 0) throw new CommonException('回收资产关联缺少有效站点');
        $identity = ErpRecycleDeviceIdentity::resolve($asset, $this->loadPurchaseItems($siteId, [(int)$asset['id']]));
        if ($identity['ambiguous'] || (int)$identity['device_id'] <= 0) {
            throw new CommonException('来源设备关联待核对，未执行' . $operation . '，请先补齐唯一回收设备关联');
        }
        $map = $this->deviceAssets($siteId, [(int)$identity['device_id']]);
        $entry = $map[$identity['device_id']] ?? [];
        if (!empty($entry['ambiguous']) || ($entry['asset_ids'] ?? []) !== [(int)$asset['id']]) {
            throw new CommonException('来源设备关联待核对，未执行' . $operation . '，资产关联不一致或存在多个ERP资产');
        }
        return (int)$identity['device_id'];
    }

    public function paymentOwnership(int $siteId, array $deviceIds): array
    {
        $map = $this->deviceAssets($siteId, $deviceIds);
        $payables = $map === [] ? [] : $this->loadPayables($siteId);
        $result = [];
        foreach ($map as $deviceId => $entry) {
            $paid = 0.0;
            $remaining = 0.0;
            $hasPayable = false;
            $seenPayables = [];
            $exactSources = [];
            foreach ($payables as $payable) {
                if ((int)($payable['site_id'] ?? 0) !== $siteId || (string)($payable['status'] ?? '') === 'void') continue;
                $payableId = (int)($payable['id'] ?? 0);
                if ($payableId > 0 && isset($seenPayables[$payableId])) continue;
                $linked = false;
                foreach ($entry['assets'] as $asset) {
                    $match = self::payableAssetMatch($payable, $asset);
                    if ($match === 'none') continue;
                    $linked = true;
                    if ($match === 'ambiguous') $entry['ambiguous'] = true;
                    if ($match === 'exact') {
                        $type = (string)($payable['source_type'] ?? '');
                        $obligation = in_array($type, ['purchase', 'purchase_asset'], true) ? 'purchase' : $type;
                        $key = $obligation . ':' . (int)$asset['id'];
                        if (isset($exactSources[$key])) $entry['ambiguous'] = true;
                        $exactSources[$key] = true;
                    }
                }
                if (!$linked) continue;
                if ($payableId > 0) $seenPayables[$payableId] = true;
                $hasPayable = true;
                // 旧整单应付没有设备级分配事实：明确歧义，不把整单金额重复算给每台设备。
                if ((string)($payable['source_type'] ?? '') === 'purchase' && (int)($payable['asset_id'] ?? 0) <= 0) continue;
                $amount = max(0, round((float)($payable['amount'] ?? 0), 2));
                $settled = max(0, round((float)($payable['settled_amount'] ?? 0), 2));
                $paid += $settled;
                $remaining += max(0, round($amount - $settled, 2));
            }
            $result[$deviceId] = [
                'has_asset' => $entry['has_asset'], 'ambiguous' => $entry['ambiguous'],
                'asset_ids' => $entry['asset_ids'], 'has_payable' => $hasPayable,
                'paid_amount' => round($paid, 2), 'remaining_amount' => round($remaining, 2),
            ];
        }
        return $result;
    }

    /** 旧设备应付 source_id 是资产ID；旧整单应付不能自动分配到一台设备。 */
    public static function payableAssetMatch(array $payable, array $asset): string
    {
        if ((int)($payable['site_id'] ?? 0) !== (int)($asset['site_id'] ?? 0)) return 'none';
        $id = (int)($asset['id'] ?? 0);
        if ($id <= 0) return 'none';
        $explicit = (int)($payable['asset_id'] ?? 0);
        $type = (string)($payable['source_type'] ?? '');
        if (!in_array($type, ['purchase', 'purchase_asset', 'consignment_sale'], true)) return 'none';
        if (!in_array((string)($payable['origin_plugin'] ?? ''), ['', 'hsx_recycle'], true)) return 'none';
        $sourceId = (int)($payable['source_id'] ?? 0);
        if ($explicit > 0) {
            if ($explicit !== $id && !($type === 'purchase_asset' && $sourceId === $id)) return 'none';
            return $type === 'purchase_asset' && $sourceId > 0 && $sourceId !== $explicit ? 'ambiguous' : 'exact';
        }
        if ($type === 'purchase_asset' && $sourceId === $id) return 'exact';
        if ($type === 'purchase' && $sourceId > 0 && $sourceId === (int)($asset['purchase_order_id'] ?? 0)) return 'ambiguous';
        return 'none';
    }

    protected function loadAssets(int $siteId): array
    {
        return ErpAsset::where([['site_id', '=', $siteId], ['source_plugin', '=', 'hsx_recycle']])
            ->field('id,site_id,asset_no,status,source_plugin,source_type,source_id,purchase_item_id,purchase_order_id,spec_json,warehouse_id,warehouse_name,location_id,location_name,update_at')
            ->select()->toArray();
    }

    protected function loadPurchaseItems(int $siteId, array $assetIds): array
    {
        if ($assetIds === []) return [];
        return ErpPurchaseItem::where([['site_id', '=', $siteId]])->whereIn('asset_id', $assetIds)
            ->field('id,site_id,asset_id,purchase_order_id,spec_json')->select()->toArray();
    }

    protected function loadPayables(int $siteId): array
    {
        return ErpPayable::where([['site_id', '=', $siteId]])
            ->field('id,site_id,asset_id,source_type,source_id,origin_plugin,amount,settled_amount,status')->select()->toArray();
    }
}
