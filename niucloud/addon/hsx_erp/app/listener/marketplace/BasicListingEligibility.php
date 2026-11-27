<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\service\admin\ErpWarehousePolicyService;
use addon\hsx_erp\app\service\admin\ErpConfigService;
use core\exception\CommonException;
use think\facade\Db;

/** 延后补分类的货源在真正建品前，再按 ERP 实时库存核对，防止交接后已经卖出。 */
final class BasicListingEligibility
{
    public function handle(array $event = []): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $assetId = (int)($event['erp_asset_id'] ?? 0);
        if ($siteId <= 0 || $assetId <= 0) throw new CommonException('商城上架缺少设备关联');
        $channel = ErpConfigService::forSite($siteId)->getMarketplaceChannel('phone_shop', $siteId);
        if ((int)($channel['enabled'] ?? 0) !== 1) throw new CommonException('ERP 的商城渠道已关闭，货源已保留，尚未上架');
        if (!Db::connect()->getPdo()->inTransaction()) throw new CommonException('商城上架核对必须与建品处于同一事务');
        $asset = ErpAsset::where('site_id', $siteId)->where('id', $assetId)->lock(true)->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('ERP 设备不存在，尚未上架');
        if ((string)$asset->status !== 'in_stock' || (string)$asset->sale_target !== 'mall') {
            throw new CommonException('ERP 设备已不在可上商城的库存中，请核对销售或调拨状态，尚未上架');
        }
        $imei = trim((string)($event['imei'] ?? ''));
        if ($imei !== '' && $imei !== trim((string)$asset->imei)) throw new CommonException('ERP 与商城货源串号不一致，尚未上架');
        if ((int)round((float)($event['sale_price'] ?? 0) * 100) !== (int)round((float)$asset->retail_price * 100)) {
            throw new CommonException('ERP 销售价已变更，请重新交接最新价格后上架');
        }
        $warehouse = ErpWarehouse::where('site_id', $siteId)->where('id', (int)$asset->warehouse_id)->findOrEmpty();
        $policy = ErpWarehousePolicyService::forSite($siteId)->evaluate($asset->toArray(), $warehouse->isEmpty() ? null : $warehouse->toArray());
        if ((int)($policy['can_prepare_mall'] ?? 0) !== 1) throw new CommonException('当前仓库或整备状态不允许上架商城');
        return ['consumer' => 'hsx_erp.basic_listing', 'allowed' => true, 'erp_asset_id' => $assetId];
    }
}
