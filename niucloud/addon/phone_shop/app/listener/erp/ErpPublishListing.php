<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\intake\DeviceIntake;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\service\admin\intake\DeviceIntakeService;
use addon\phone_shop\app\service\core\intake\CoreDeviceIntakeService;
use addon\phone_shop\app\support\IntakeMaterialTask;
use think\facade\Log;

/** phone_shop 消费 ERP 发布请求；ERP 不直接依赖商城模型和建品服务。 */
final class ErpPublishListing
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

            (new \addon\phone_shop\app\service\core\order\CoreOrderInventoryService())->guardErpSale($siteId, $assetId);

            // 必须先经过货源服务做设备身份校验。ERP 插件重装后自增 asset_id
            // 可能复用，不能仅凭 SKU 上的数字外键把一台旧机器判成当前设备。
            $intakeResult = (new CoreDeviceIntakeService())->saveFromEvent([
                'event_name' => 'erp.asset.publish_listing.v1',
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
            ])->field('intake_id,status,goods_id,raw_payload')->findOrEmpty();
            if ($intake->isEmpty()) return compact('provider') + ['status' => 'failed', 'message' => '商城未找到对应货源'];
            if ((int)$intake->status === DeviceIntake::STATUS_BUILT && (int)$intake->goods_id > 0) {
                $goods = (new Goods())->where('site_id', $siteId)->where('goods_id', (int)$intake->goods_id)->findOrEmpty();
                if ($goods->isEmpty() || (int)$goods->status !== 1 || (int)$goods->stock <= 0
                    || !in_array((string)$goods->sale_status, ['', 'available'], true)
                    || (int)$goods->is_online_sellable !== 1) {
                    return compact('provider') + ['status' => 'failed', 'message' => '商品已建品但当前不可售，请在商城核对下架、库存或订单占用；不会通过重复交接重新上架'];
                }
                $materialPending = in_array(IntakeMaterialTask::read($intake->raw_payload)['status'], ['pending', 'processing'], true);
                return compact('provider') + ['status' => 'duplicate', 'intake_id' => (int)$intake->intake_id, 'goods_id' => (int)$intake->goods_id,
                    'material_pending' => $materialPending ? 1 : 0, 'message' => $materialPending ? '已上架可售，资料待商城运营完善' : '商品已上架可售，无需重复建品'];
            }
            $basicFirst = (int)($payload['basic_first'] ?? 0) === 1;
            if ($basicFirst && empty($payload['goods_category'])) {
                return compact('provider') + ['status' => 'pending', 'intake_id' => (int)$intake->intake_id,
                    'message' => (string)($payload['basic_block_reason'] ?? '') ?: '图片和价格已交接；缺少商城分类，请运营对应分类后上架，目前客户不可见'];
            }
            if ((string)($payload['completion_mode'] ?? 'erp') === 'phone_shop') {
                return compact('provider') + ['status' => 'pending', 'intake_id' => (int)$intake->intake_id, 'message' => '已进入商城运营待办'];
            }

            $goodsId = DeviceIntakeService::forSite($siteId)->build([
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
                'goods_video' => trim((string)($payload['video_url'] ?? '')),
                'status' => 1,
                'sync_back_erp' => false,
            ]);
            $saved = (new DeviceIntake())->where('site_id', $siteId)->where('intake_id', (int)$intake->intake_id)->findOrEmpty();
            $distribution = (array)(IntakeMaterialTask::payload($saved->raw_payload)['_agent_distribution'] ?? []);
            $warning = (int)($distribution['failed_count'] ?? 0) > 0
                ? '；部分子站同步未完成：' . implode('；', (array)($distribution['errors'] ?? [])) : '';
            return compact('provider') + ['status' => 'published', 'intake_id' => (int)$intake->intake_id, 'goods_id' => $goodsId,
                'material_pending' => 1,
                'agent_distribution' => $distribution,
                'message' => '已上架可售，资料待商城运营核对完善' . $warning];
        } catch (\Throwable $e) {
            Log::write('[phone_shop] 消费ERP上架请求失败: ' . $e->getMessage());
            try {
                (new CoreDeviceIntakeService())->recordPublishFailure((int)($siteId ?? 0), (int)($assetId ?? 0), $e->getMessage());
            } catch (\Throwable $recordError) {
                Log::write('[phone_shop] 货源失败原因记录异常: ' . $recordError->getMessage());
            }
            return compact('provider') + ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }
}
