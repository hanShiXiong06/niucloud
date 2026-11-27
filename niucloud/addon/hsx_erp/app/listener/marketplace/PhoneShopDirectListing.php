<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\intake\DeviceIntake;
use addon\phone_shop\app\service\admin\intake\DeviceIntakeService;
use addon\phone_shop\app\service\core\intake\CoreDeviceIntakeService;
use think\facade\Log;

/**
 * phone_shop 直上架兼容层。
 *
 * ERP 已经具备分类、规格、图片和售价时直接复用商城建品服务。拍照中台仅作为
 * 以后可选的工单分工能力，不是库存上架的必经节点。
 */
final class PhoneShopDirectListing
{
    public function handle(array $event = []): array
    {
        $provider = 'phone_shop';
        try {
            $siteId = (int)($event['site_id'] ?? 0);
            $payload = (array)($event['payload'] ?? []);
            $assetId = (int)($payload['erp_asset_id'] ?? 0);
            if ($siteId <= 0 || $assetId <= 0) {
                return compact('provider') + ['status' => 'rejected', 'message' => '上架参数不完整'];
            }
            if (!PhoneShopBridge::available($siteId)) {
                return compact('provider') + ['status' => 'ignored', 'message' => '当前站点未启用二手机商城'];
            }

            $existing = (new GoodsSku())->where([
                ['site_id', '=', $siteId],
                ['erp_asset_id', '=', $assetId],
            ])->field('goods_id')->findOrEmpty();
            if (!$existing->isEmpty()) {
                return compact('provider') + [
                    'status' => 'duplicate',
                    'goods_id' => (int)$existing->goods_id,
                    'message' => '设备已经在商城建品',
                ];
            }

            $intakeResult = (new CoreDeviceIntakeService())->saveFromEvent([
                'event_name' => 'erp.asset.direct_listing.v1',
                'site_id' => $siteId,
                'device_id' => (int)($payload['device_id'] ?? 0),
                'payload' => $payload,
            ]);
            if (!empty($intakeResult['skipped'])) {
                return compact('provider') + ['status' => 'failed', 'message' => (string)($intakeResult['reason'] ?? '商城货源写入失败')];
            }
            $intake = (new DeviceIntake())->where([
                ['site_id', '=', $siteId],
                ['erp_asset_id', '=', $assetId],
            ])->field('intake_id,status,goods_id')->findOrEmpty();
            if ($intake->isEmpty()) {
                return compact('provider') + ['status' => 'failed', 'message' => '商城未找到对应货源'];
            }
            if ((int)$intake->status === DeviceIntake::STATUS_BUILT && (int)$intake->goods_id > 0) {
                return compact('provider') + ['status' => 'duplicate', 'goods_id' => (int)$intake->goods_id, 'message' => '设备已经在商城建品'];
            }

            if ((string)($payload['completion_mode'] ?? 'erp') === 'phone_shop') {
                return compact('provider') + [
                    'status' => 'pending',
                    'intake_id' => (int)$intake->intake_id,
                    'message' => '已进入商城待上架货源，由商城运营完善资料',
                ];
            }

            $goodsId = (new DeviceIntakeService())->build([
                'intake_id' => (int)$intake->intake_id,
                'goods_name' => trim((string)($payload['goods_name'] ?? $payload['model_name'] ?? '')),
                'sub_title' => trim((string)($payload['sub_title'] ?? '')),
                'goods_category' => array_values(array_filter(array_map('intval', (array)($payload['goods_category'] ?? [])))),
                'condition_grade' => trim((string)($payload['condition_grade'] ?? '')),
                'memory' => trim((string)($payload['memory'] ?? '')),
                'price' => round((float)($payload['sale_price'] ?? 0), 2),
                'market_price' => round((float)($payload['sale_price'] ?? 0), 2),
                'cost_price' => round((float)($payload['cost_price'] ?? 0), 2),
                'goods_desc' => trim((string)($payload['goods_desc'] ?? '')),
                'status' => 1,
                // ERP 发起的直上架由 ErpStockService 统一落状态、记流水。
                'sync_back_erp' => false,
            ]);
            return compact('provider') + ['status' => 'published', 'goods_id' => $goodsId, 'message' => '已直接上架商城'];
        } catch (\Throwable $e) {
            Log::write('[hsx_erp] phone_shop直上架失败: ' . $e->getMessage());
            return compact('provider') + ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }
}
