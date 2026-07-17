<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener\marketplace;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\service\admin\ErpLedgerService;
use addon\hsx_erp\app\service\admin\ErpListingTaskService;

/** 商城运营完成建品后，把人工分类/规格映射及上架状态回写 ERP。 */
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
        $spec = json_decode(trim((string)$asset->spec_json), true);
        if (!is_array($spec)) $spec = [];
        $spec['marketplace_mapping'] = [
            'provider' => 'phone_shop',
            'goods_id' => $goodsId,
            'intake_id' => (int)($event['intake_id'] ?? 0),
            'category_ids' => array_values((array)($mapping['category_ids'] ?? [])),
            'brand_id' => (int)($mapping['brand_id'] ?? 0),
            'label_ids' => array_values((array)($mapping['label_ids'] ?? [])),
            'service_ids' => array_values((array)($mapping['service_ids'] ?? [])),
            'completed_at' => time(),
        ];
        if (trim((string)($mapping['memory'] ?? '')) !== '') $spec['memory'] = trim((string)$mapping['memory']);
        if (trim((string)($mapping['condition_grade'] ?? '')) !== '') $spec['condition_grade'] = trim((string)$mapping['condition_grade']);

        $before = (string)$asset->listing_status;
        $save = [
            'spec_json' => json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'listing_status' => 'listed',
            'update_at' => time(),
        ];
        if ((float)($mapping['price'] ?? 0) > 0) $save['retail_price'] = round((float)$mapping['price'], 2);
        if (!empty($mapping['images'])) $save['image_urls'] = json_encode(array_values((array)$mapping['images']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!empty($mapping['qc_report'])) $save['qc_report'] = json_encode((array)$mapping['qc_report'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $asset->save($save);

        if ($before !== 'listed') {
            (new ErpLedgerService())->asset([
                'asset_id' => $assetId,
                'action' => 'listing_publish',
                'before_status' => (string)$asset->status . '/' . $before,
                'after_status' => (string)$asset->status . '/listed',
                'source_type' => 'phone_shop_goods',
                'source_id' => $goodsId,
                'remark' => '商城运营已完成分类、规格映射并上架，资料已回写 ERP',
                'extra' => ['provider' => 'phone_shop', 'goods_id' => $goodsId],
            ]);
        }
        ErpListingTaskService::forSite($siteId, 0, '商城运营')->sync($assetId);
        return ['updated' => true, 'asset_id' => $assetId, 'goods_id' => $goodsId];
    }
}
