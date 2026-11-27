<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\service\admin\ErpLedgerService;
use addon\hsx_erp\app\service\admin\ErpListingTaskService;
use addon\hsx_erp\app\service\admin\ErpWarehousePolicyService;
use addon\hsx_erp\app\support\ErpListingWorkflow;

/**
 * 接收已选图片，不接收价格，不自动发布商城。
 * 由拍照确认事务同步调用：未收到本监听器的明确回执，中台不得标记交接完成。
 */
class DeviceAssetPhotosCompleted
{
    public function handle(array $event): array
    {
        $consumer = 'hsx_erp.device_asset_photos';
        if (($event['event_name'] ?? '') !== 'device_asset.photos.completed.v1') {
            return ['consumer' => $consumer, 'status' => 'ignored'];
        }
        $siteId = (int)($event['site_id'] ?? 0);
        $payload = (array)($event['payload'] ?? []);
        $assetId = (int)($payload['erp_asset_id'] ?? 0);
        $images = array_values(array_unique(array_filter(array_map('strval', (array)($payload['images'] ?? [])))));
        if ($siteId <= 0 || $assetId <= 0 || $images === [] || !empty($payload['simulated'])) {
            throw new \RuntimeException('图片交接信息不完整，或包含开发模拟图片');
        }
        $asset = ErpAsset::where([['site_id', '=', $siteId], ['id', '=', $assetId]])->lock(true)->findOrEmpty();
        if ($asset->isEmpty()) throw new \RuntimeException('ERP 设备不存在，请核对设备后重新拍摄');
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
            throw new \RuntimeException('设备已不在库存中，图片已保留，请到 ERP 确认当前业务状态');
        }
        $expectedNo = trim((string)($payload['erp_asset_no'] ?? ''));
        if ($expectedNo !== '' && $expectedNo !== (string)$asset->asset_no) {
            throw new \RuntimeException('ERP 设备编号与拍摄任务不一致，已阻止图片交接');
        }
        $encoded = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $same = (string)$asset->image_urls === $encoded;
        if (!$same && in_array((string)$asset->listing_status, ['pending_shop', 'listed'], true)) {
            throw new \RuntimeException('设备已交接商城，请先在 ERP 处理撤回或下架，再更新商品图片');
        }
        if (!$same) {
            $before = (string)$asset->listing_status;
            $projected = array_merge($asset->toArray(), ['image_urls' => $encoded]);
            $warehouse = ErpWarehouse::where([['site_id', '=', $siteId], ['id', '=', (int)$asset->warehouse_id], ['status', '=', 1]])->findOrEmpty();
            $policy = ErpWarehousePolicyService::forSite($siteId)->evaluate($projected, $warehouse->isEmpty() ? null : $warehouse->toArray());
            $next = ErpListingWorkflow::statusFromAsset($projected, $policy);
            $asset->save(['image_urls' => $encoded, 'listing_status' => $next, 'update_at' => time()]);
            ErpLedgerService::forSite($siteId, (int)($event['operator']['id'] ?? 0), (string)($event['operator']['name'] ?? '拍照员'))->asset([
                'asset_id' => $assetId, 'action' => 'listing_photo_complete',
                'before_status' => $before, 'after_status' => $next,
                'source_type' => 'hsx_device_asset', 'source_id' => (int)($event['asset_id'] ?? 0),
                'remark' => '拍照完成，已接收 ' . count($images) . ' 张选用图片；价格和库存成本未修改',
                'extra' => ['image_count' => count($images), 'event_id' => (string)($event['event_id'] ?? '')],
            ]);
        }
        ErpListingTaskService::forSite($siteId)->sync($assetId);
        return [
            'consumer' => $consumer, 'status' => $same ? 'duplicate' : 'processed',
            'erp_asset_id' => $assetId, 'listing_status' => (string)$asset->listing_status,
            'image_count' => count($images), 'message' => 'ERP 已接收 ' . count($images) . ' 张图片，请在 ERP 继续定价或完善销售资料',
        ];
    }
}
