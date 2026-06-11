<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetCycle;
use addon\hsx_erp\app\model\ErpOperationEvent;
use addon\hsx_erp\app\model\ErpPriceLog;
use addon\hsx_erp\app\model\ErpStockLedger;
use addon\hsx_erp\app\support\ErpMoney;
use core\exception\CommonException;
use think\facade\Db;

class ErpExternalPricingService
{
    public function applyDeviceAssetPrice(array $event): array
    {
        $payload = (array)($event['payload'] ?? []);
        $siteId = (int)($event['site_id'] ?? 0);
        $assetId = (int)($payload['erp_asset_id'] ?? 0);
        $salePrice = ErpMoney::normalize($payload['sale_price'] ?? 0);
        if ($siteId <= 0 || $assetId <= 0 || ErpMoney::compare($salePrice, '0.00') <= 0) {
            return ['skipped' => true, 'reason' => 'invalid_event'];
        }

        $now = time();
        Db::startTrans();
        try {
            $asset = ErpAsset::where([
                ['site_id', '=', $siteId],
                ['id', '=', $assetId],
            ])->lock(true)->findOrEmpty();
            if ($asset->isEmpty()) {
                throw new CommonException('ERP资产不存在');
            }

            $beforeStatus = (string)$asset->inventory_status;
            $beforePrice = ErpMoney::normalize($asset->current_sale_price);
            $currentCost = ErpMoney::normalize($asset->current_cost);
            $grossProfit = ErpMoney::subtract($salePrice, $currentCost);
            $grossMargin = ErpMoney::compare($salePrice, '0.00') > 0
                ? round(((float)$grossProfit / (float)$salePrice) * 100, 4)
                : 0;
            $action = $beforeStatus === ErpDict::INVENTORY_AVAILABLE_FOR_SALE
                ? ErpDict::PRICE_ACTION_ADJUST
                : ErpDict::PRICE_ACTION_INITIAL;

            $asset->save([
                'inventory_status' => ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
                'current_sale_price' => $salePrice,
                'version' => (int)$asset->version + 1,
                'update_at' => $now,
            ]);
            ErpAssetCycle::where([
                ['site_id', '=', $siteId],
                ['id', '=', (int)$asset->cycle_id],
            ])->update(['status' => ErpDict::INVENTORY_AVAILABLE_FOR_SALE, 'update_at' => $now]);

            ErpPriceLog::create([
                'site_id' => $siteId,
                'price_no' => $this->makeNo('PL'),
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'action' => $action,
                'before_price' => $beforePrice,
                'after_price' => $salePrice,
                'current_cost' => $currentCost,
                'gross_profit' => $grossProfit,
                'gross_margin' => $grossMargin,
                'min_profit' => ErpMoney::normalize($payload['min_price'] ?? 0),
                'operator_id' => (int)($event['operator']['id'] ?? 0),
                'operator_name' => (string)($event['operator']['name'] ?? ''),
                'remark' => (string)($payload['remark'] ?? ''),
                'occurred_at' => $now,
            ]);

            if ($beforeStatus !== ErpDict::INVENTORY_AVAILABLE_FOR_SALE) {
                ErpStockLedger::create([
                    'site_id' => $siteId,
                    'ledger_no' => $this->makeNo('SL'),
                    'asset_id' => (int)$asset->id,
                    'cycle_id' => (int)$asset->cycle_id,
                    'stock_order_id' => 0,
                    'action' => 'sales_priced',
                    'before_status' => $beforeStatus,
                    'after_status' => ErpDict::INVENTORY_AVAILABLE_FOR_SALE,
                    'warehouse_id' => (int)$asset->warehouse_id,
                    'location_id' => (int)$asset->location_id,
                    'operator_id' => (int)($event['operator']['id'] ?? 0),
                    'operator_name' => (string)($event['operator']['name'] ?? ''),
                    'occurred_at' => $now,
                    'payload' => ['source' => 'device_asset.price.completed.v1'],
                ]);
            }

            ErpOperationEvent::create([
                'site_id' => $siteId,
                'event_id' => 'external-priced-' . $assetId . '-' . $now,
                'event_name' => 'erp.asset.priced.v1',
                'asset_id' => (int)$asset->id,
                'cycle_id' => (int)$asset->cycle_id,
                'document_type' => 'device_asset_pricing',
                'document_id' => (int)($event['asset_id'] ?? 0),
                'stage' => 'sales_pricing',
                'action' => $action,
                'operator_id' => (int)($event['operator']['id'] ?? 0),
                'operator_name' => (string)($event['operator']['name'] ?? ''),
                'payload' => [
                    'before_price' => $beforePrice,
                    'after_price' => $salePrice,
                    'current_cost' => $currentCost,
                    'gross_profit' => $grossProfit,
                ],
                'occurred_at' => $now,
            ]);
            Db::commit();
            return ['updated' => true, 'asset_id' => $assetId];
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    private function makeNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . random_int(1000, 9999);
    }
}
