<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;

/** 拍摄扫码的只读查询契约；只返回当前站点的唯一在库设备。 */
class DeviceAssetPhotoLookupRequested
{
    public function handle(array $request): array
    {
        $siteId = (int)($request['site_id'] ?? 0);
        $keyword = trim((string)($request['keyword'] ?? ''));
        if ($siteId <= 0 || $keyword === '') return [];
        $items = ErpAsset::where('site_id', $siteId)->where('status', ErpDict::ASSET_IN_STOCK)
            ->where(function ($query) use ($keyword) {
                $query->where('asset_no', $keyword)->whereOr('imei', $keyword)->whereOr('imei2', $keyword)->whereOr('sn', $keyword);
            })->limit(2)->select()->toArray();
        if (count($items) > 1) throw new \RuntimeException('该串号对应多台在库设备，请从 ERP 库存列表核对后发起拍摄');
        return $items ? ['asset' => $items[0]] : [];
    }
}
