<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 客户代卖设备登记入库。
 *
 * 这里只登记“客户物权、公司保管”的库存事实，绝不创建采购单、应付或成本。
 * 当客户同意将设备卖给公司时，再由库存中心的代卖买断流程取得物权并形成应付。
 */
class ErpConsignmentInboundService extends BaseAdminService
{
    public function register(array $event, array $device, array $item): array
    {
        $source = (array)($event['_erp_source'] ?? []);
        $sourcePlugin = trim((string)($source['plugin'] ?? '')) ?: 'hsx_recycle';
        $sourceDeviceId = trim((string)($device['source_device_id'] ?? ''));
        if ($sourceDeviceId === '') {
            throw new CommonException('代卖设备缺少来源设备ID，不能登记入库');
        }

        $existing = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['source_plugin', '=', $sourcePlugin],
            ['source_id', '=', $sourceDeviceId],
        ])->lock(true)->findOrEmpty();
        if (!$existing->isEmpty()) {
            if ((string)$existing->ownership_type !== 'consigned') {
                throw new CommonException('来源设备已作为自有资产入库，不能重复登记为代卖设备');
            }
            return ['asset_id' => (int)$existing->id, 'created' => false];
        }

        $warehouseId = (int)($item['warehouse_id'] ?? 0);
        $locationId = (int)($item['location_id'] ?? 0);
        [$warehouse, $location] = (new ErpWarehouseService())->validateInboundLocation($warehouseId, $locationId);
        if ((string)$warehouse->warehouse_type !== 'consignment' && (string)$warehouse->ownership_type !== 'consigned') {
            throw new CommonException('客户代卖设备必须登记到代卖仓，不能混入公司自有库存');
        }

        $this->assertActiveIdentityAvailable((string)($item['imei'] ?? ''), (string)($item['sn'] ?? ''));
        $counterparty = (array)($device['counterparty'] ?? []);
        // 不传 hsx_recycle 给 ensureParty，避免代卖登记提前获得“采购供货商”身份；
        // 真正买断时才产生采购关系。会员绑定与姓名手机号同步仍然会执行。
        $party = (new ErpPurchaseService())->resolveExternalParty($counterparty, '', ['recycle_customer']);
        $partyName = (string)$party->party_name;
        $now = time();
        $occurredAt = max(1, (int)($device['acquired_at'] ?? $event['occurred_at'] ?? $now));
        $sourceOrderId = max(0, (int)($device['source_id'] ?? 0));
        $sourceOrderNo = trim((string)($device['source_order_no'] ?? ''));
        $refurbish = (array)($device['refurbishment'] ?? []);
        $refurbishStatus = !empty($refurbish['required']) ? 'pending' : 'none';
        $saleTarget = trim((string)($device['sale_destination'] ?? $warehouse->default_sale_target ?? 'unset'));
        if (!in_array($saleTarget, ['unset', 'peer', 'mall'], true)) $saleTarget = 'unset';
        $images = trim((string)($item['image_urls'] ?? ''));
        $retailPrice = max(0, round((float)($item['retail_price'] ?? $item['estimate_sale_price'] ?? 0), 2));
        $listingStatus = $this->listingStatus($warehouse, $saleTarget, $images, $retailPrice);
        $specJson = $item['spec_json'] ?? [];
        if (is_array($specJson)) {
            $specJson = json_encode($specJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        $asset = ErpAsset::create([
            'site_id' => $this->site_id,
            'asset_no' => ErpLedgerService::makeNo('AS'),
            'purchase_order_id' => 0,
            'purchase_item_id' => 0,
            'party_id' => (int)$party->id,
            'party_name' => $partyName,
            'ownership_type' => 'consigned',
            'owner_party_id' => (int)$party->id,
            'owner_party_name' => $partyName,
            'ownership_source_type' => 'consignment',
            'ownership_source_id' => $sourceOrderId,
            'ownership_source_no' => $sourceOrderNo,
            'ownership_changed_at' => $occurredAt,
            'warehouse_id' => (int)$warehouse->id,
            'warehouse_name' => (string)$warehouse->warehouse_name,
            'location_id' => (int)$location->id,
            'location_name' => (string)$location->location_name,
            'imei' => trim((string)($item['imei'] ?? '')),
            'sn' => trim((string)($item['sn'] ?? '')),
            'model' => trim((string)($item['model'] ?? '')),
            'spec' => trim((string)($item['spec'] ?? '')),
            'spec_json' => (string)$specJson,
            'color' => trim((string)($item['color'] ?? '')),
            'battery' => max(0, min(100, (int)($item['battery'] ?? 0))),
            'warranty' => max(0, (int)($item['warranty'] ?? 0)),
            'catalog_product_id' => max(0, (int)($item['catalog_product_id'] ?? 0)),
            'category_name' => trim((string)($item['category_name'] ?? '')),
            'category_path' => $this->categoryPath($item['category_path'] ?? ''),
            'estimate_sale_price' => max(0, round((float)($item['estimate_sale_price'] ?? 0), 2)),
            'retail_price' => $retailPrice,
            'image_urls' => $images,
            'quality_remark' => trim((string)($item['quality_remark'] ?? '')),
            'remark_public' => trim((string)($item['remark_public'] ?? '')),
            'remark_internal' => trim((string)($item['remark_internal'] ?? '')),
            'purchase_cost' => 0,
            'adjust_cost' => 0,
            'refurbish_cost' => 0,
            'total_cost' => 0,
            'refurbish_status' => $refurbishStatus,
            'refurbish_pending_at' => $refurbishStatus === 'pending' ? $occurredAt : 0,
            'refurbish_remark' => $refurbishStatus === 'pending' ? mb_substr(trim((string)($refurbish['reason'] ?? '')), 0, 500) : '',
            'sale_target' => $saleTarget,
            'listing_status' => $refurbishStatus === 'pending' ? 'none' : $listingStatus,
            'status' => ErpDict::ASSET_IN_STOCK,
            'source_plugin' => $sourcePlugin,
            'source_type' => $sourcePlugin . '.consignment',
            'source_id' => $sourceDeviceId,
            'remark' => trim((string)($item['remark'] ?? '')),
            'stock_in_at' => $occurredAt,
            'create_at' => $now,
            'update_at' => $now,
        ]);

        (new ErpLedgerService())->asset([
            'asset_id' => (int)$asset->id,
            'action' => 'consignment_inbound',
            'after_status' => ErpDict::ASSET_IN_STOCK,
            'source_type' => 'consignment',
            'source_id' => $sourceOrderId,
            'source_no' => $sourceOrderNo,
            'after_warehouse_id' => (int)$warehouse->id,
            'after_warehouse_name' => (string)$warehouse->warehouse_name,
            'after_location_id' => (int)$location->id,
            'after_location_name' => (string)$location->location_name,
            'after_total_cost' => 0,
            'party_id' => (int)$party->id,
            'party_name' => $partyName,
            'occurred_at' => $occurredAt,
            'remark' => '客户代卖设备登记入库（未取得物权、未形成应付）',
            'extra' => [
                'ownership_type' => 'consigned',
                'source_plugin' => $sourcePlugin,
                'source_device_id' => $sourceDeviceId,
                'source_order_no' => $sourceOrderNo,
            ],
        ]);
        (new ErpOperationLogService())->record(
            'consignment_inbound',
            'asset',
            (int)$asset->id,
            (string)$asset->asset_no,
            '客户代卖设备登记入库，不生成采购应付',
            ['owner_party_id' => (int)$party->id, 'owner_party_name' => $partyName, 'source_order_no' => $sourceOrderNo]
        );

        return ['asset_id' => (int)$asset->id, 'created' => true];
    }

    private function assertActiveIdentityAvailable(string $imei, string $sn): void
    {
        foreach ([['imei', trim($imei), 'IMEI'], ['sn', trim($sn), 'SN']] as [$field, $value, $label]) {
            if ($value === '') continue;
            $exists = ErpAsset::where([
                ['site_id', '=', $this->site_id],
                [$field, '=', $value],
                ['status', '=', ErpDict::ASSET_IN_STOCK],
            ])->lock(true)->findOrEmpty();
            if (!$exists->isEmpty()) {
                throw new CommonException($label . '【' . $value . '】已有在库设备，不能重复登记');
            }
        }
    }

    private function listingStatus($warehouse, string $saleTarget, string $images, float $retailPrice): string
    {
        if ($saleTarget !== 'mall') return 'none';
        if ((int)$warehouse->need_photo === 1 && $images === '') return 'need_photo';
        if ((int)$warehouse->need_pricing === 1 && $retailPrice <= 0) return 'need_price';
        return 'ready';
    }

    private function categoryPath(mixed $path): string
    {
        if (is_string($path)) return trim($path);
        if (!is_array($path)) return '';
        return implode(',', array_values(array_filter(array_map(static fn($id): string => (string)(int)$id, $path))));
    }
}
