<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\service\admin\ErpChannelMappingService;
use addon\hsx_erp\app\service\admin\ErpLedgerService;
use addon\hsx_erp\app\service\admin\ErpListingTaskService;

/** 商城运营完成建品后，只回写映射、渠道关联和上架状态。 */
final class PhoneShopListingMaterialCompleted
{
    public function handle(array $event = []): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $assetId = (int)($event['erp_asset_id'] ?? 0);
        $goodsId = (int)($event['goods_id'] ?? 0);
        if ($siteId <= 0 || $assetId <= 0 || $goodsId <= 0) return ['updated' => false];

        $asset = ErpAsset::where([['site_id', '=', $siteId], ['id', '=', $assetId]])->findOrEmpty();
        if ($asset->isEmpty()) return ['updated' => false, 'reason' => 'asset_not_found'];
        $mapping = (array)($event['mapping'] ?? []);
        $operator = (array)($event['operator'] ?? []);
        $operatorUid = (int)($operator['uid'] ?? 0);
        $operatorName = trim((string)($operator['name'] ?? '')) ?: '商城资料运营';
        $spec = json_decode(trim((string)$asset->spec_json), true);
        if (!is_array($spec)) $spec = [];

        // 商城可以学习并保存“ERP值 → 商城值”的映射，但不得反向覆盖 ERP 主资料。
        $erpContext = (array)($event['erp_context'] ?? []);
        $erpContext = array_replace([
            'category_path' => (string)($asset->category_path ?? ''),
            'memory' => $spec['memory'] ?? $spec['storage'] ?? $spec['capacity'] ?? '',
            'condition_grade' => $spec['condition_grade'] ?? $spec['condition'] ?? $spec['grade'] ?? '',
            'specs' => $spec,
        ], $erpContext);
        (new ErpChannelMappingService())->recordManualCompletion($event, $erpContext);

        $before = (string)$asset->listing_status;
        $asset->save(['listing_status' => 'listed', 'update_at' => time()]);

        if ($before !== 'listed') {
            $ledger = ErpLedgerService::forSite($siteId, $operatorUid, $operatorName);
            $ledger->asset([
                'asset_id' => $assetId,
                'action' => 'listing_material_complete',
                'before_status' => (string)$asset->status . '/' . $before,
                'after_status' => (string)$asset->status . '/listed',
                'source_type' => 'phone_shop_intake',
                'source_id' => (int)($event['intake_id'] ?? 0),
                'operator_uid' => $operatorUid,
                'operator_name' => $operatorName,
                'remark' => '完成商城分类、规格映射并保留渠道关联',
                'extra' => ['provider' => 'phone_shop', 'goods_id' => $goodsId],
            ]);
            $ledger->asset([
                'asset_id' => $assetId,
                'action' => 'listing_publish',
                'before_status' => (string)$asset->status . '/' . $before,
                'after_status' => (string)$asset->status . '/listed',
                'source_type' => 'phone_shop_goods',
                'source_id' => $goodsId,
                'operator_uid' => $operatorUid,
                'operator_name' => $operatorName,
                'remark' => '商城运营已完成映射并上架；ERP 主资料保持不变',
                'extra' => ['provider' => 'phone_shop', 'goods_id' => $goodsId],
            ]);
        }
        ErpListingTaskService::forSite($siteId, $operatorUid, $operatorName)->sync($assetId);
        return ['updated' => true, 'asset_id' => $assetId, 'goods_id' => $goodsId];
    }
}
