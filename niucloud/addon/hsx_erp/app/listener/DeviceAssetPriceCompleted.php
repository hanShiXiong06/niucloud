<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\service\admin\ErpLedgerService;
use addon\hsx_erp\app\service\admin\ErpListingTaskService;
use addon\hsx_erp\app\service\admin\ErpStockService;
use addon\hsx_erp\app\service\admin\ErpWarehousePolicyService;
use addon\hsx_erp\app\support\ErpListingWorkflow;
use think\facade\Log;

/** 拍照中台完成销售定价后，先回写 ERP，再由 ERP 决定是否发布各渠道。 */
class DeviceAssetPriceCompleted
{
    public function handle(array $event): array
    {
        $consumer = 'hsx_erp.device_asset_priced';
        if ((string)($event['event_name'] ?? '') !== 'device_asset.price.completed.v1') {
            return ['consumer' => $consumer, 'status' => 'ignored', 'skipped' => true];
        }
        $payload = (array)($event['payload'] ?? []);
        $siteId = (int)($event['site_id'] ?? $payload['site_id'] ?? 0);
        $assetId = (int)($payload['erp_asset_id'] ?? 0);
        if ($siteId <= 0 || $assetId <= 0) {
            return ['consumer' => $consumer, 'status' => 'ignored', 'skipped' => true, 'reason' => 'erp_asset_not_linked'];
        }

        try {
            $asset = ErpAsset::where([['site_id', '=', $siteId], ['id', '=', $assetId]])->findOrEmpty();
            if ($asset->isEmpty()) return ['consumer' => $consumer, 'status' => 'rejected', 'skipped' => true, 'reason' => 'erp_asset_missing'];
            $before = $asset->toArray();
            $images = array_values(array_unique(array_filter(array_map(
                static fn($url): string => trim((string)$url),
                (array)($payload['images'] ?? [])
            ))));
            $save = ['update_at' => time()];
            if ($images !== []) $save['image_urls'] = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $videoUrl = trim((string)($payload['video_url'] ?? ($payload['videos'][0] ?? '')));
            if ($videoUrl !== '') $save['video_url'] = $videoUrl;
            $salePrice = round((float)($payload['sale_price'] ?? 0), 2);
            if ($salePrice > 0) $save['retail_price'] = $salePrice;
            $peerPrice = round((float)($payload['peer_price'] ?? 0), 2);
            if ($peerPrice > 0) $save['estimate_sale_price'] = $peerPrice;
            if (trim((string)$asset->model) === '' && trim((string)($payload['model_name'] ?? '')) !== '') {
                $save['model'] = trim((string)$payload['model_name']);
            }
            $asset->save($save);
            $asset = ErpAsset::where([['site_id', '=', $siteId], ['id', '=', $assetId]])->findOrEmpty();
            $warehouse = ErpWarehouse::where([['site_id', '=', $siteId], ['id', '=', (int)$asset->warehouse_id], ['status', '=', 1]])->findOrEmpty();
            $policyService = ErpWarehousePolicyService::forSite($siteId);
            $policy = $policyService->evaluate($asset->toArray(), $warehouse->isEmpty() ? null : $warehouse->toArray());
            $status = ErpListingWorkflow::statusFromAsset($asset->toArray(), $policy);
            $asset->save(['listing_status' => $status, 'update_at' => time()]);

            $ledger = ErpLedgerService::forSite($siteId, (int)($event['operator']['id'] ?? 0), (string)($event['operator']['name'] ?? '拍照中台'));
            $ledger->asset([
                'asset_id' => $assetId,
                'action' => 'listing_price_complete',
                'before_status' => (string)($before['listing_status'] ?? 'none'),
                'after_status' => $status,
                'source_type' => 'hsx_device_asset',
                'source_id' => (int)($event['asset_id'] ?? 0),
                'remark' => '拍照中台已回写商品图片、视频和销售定价',
                'extra' => ['image_count' => count($images), 'has_video' => $videoUrl !== '', 'retail_price' => $salePrice],
            ]);
            ErpListingTaskService::forSite($siteId)->sync($assetId);
            $autoPublish = ErpStockService::forSite(
                $siteId,
                (int)($event['operator']['id'] ?? 0),
                (string)($event['operator']['name'] ?? '拍照中台')
            )->autoPublishListingIfReady($assetId);
            return [
                'consumer' => $consumer,
                'status' => 'processed',
                'erp_asset_id' => $assetId,
                'listing_status' => $status,
                'auto_publish' => $autoPublish,
            ];
        } catch (\Throwable $e) {
            Log::warning('拍照中台结果回写ERP失败', ['asset_id' => $assetId, 'message' => $e->getMessage()]);
            return ['consumer' => $consumer, 'status' => 'failed', 'error' => true, 'message' => $e->getMessage()];
        }
    }
}
