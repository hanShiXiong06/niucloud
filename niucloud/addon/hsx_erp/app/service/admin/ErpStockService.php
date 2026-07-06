<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAccountLedger;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpAssetLedger;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use addon\hsx_erp\app\model\ErpSaleOrder;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpStockService extends BaseAdminService
{
    private static bool $schemaEnsured = false;

    public function getPage(array $where): array
    {
        $this->ensureSchema();
        $query = ErpAsset::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('asset_no|imei|sn|model|spec|category_name|party_name|warehouse_name|location_name', '%' . $kw . '%');
        }
        if (!empty($where['status'])) {
            $query->where('status', '=', (string)$where['status']);
        }
        if (!empty($where['refurbish_status'])) {
            $query->where('refurbish_status', '=', (string)$where['refurbish_status']);
        }
        if (!empty($where['sale_target'])) {
            $query->where('sale_target', '=', (string)$where['sale_target']);
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('location_id', '=', (int)$where['location_id']);
        }
        if (!empty($where['category_id'])) {
            $categoryId = (int)$where['category_id'];
            $query->where(function ($q) use ($categoryId) {
                $q->where('category_id', '=', $categoryId)
                    ->whereOr('category_path', 'like', '%,' . $categoryId . ',%')
                    ->whereOr('category_path', 'like', $categoryId . ',%')
                    ->whereOr('category_path', 'like', '%,' . $categoryId)
                    ->whereOr('category_path', '=', (string)$categoryId);
            });
        }
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $this->ensureSchema();
        $asset = $this->findAsset($id)->toArray();
        $asset['purchase_order'] = null;
        $asset['sale_order'] = null;
        if ((int)$asset['purchase_order_id'] > 0) {
            $purchase = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset['purchase_order_id']]])->findOrEmpty();
            $asset['purchase_order'] = $purchase->isEmpty() ? null : $purchase->toArray();
        }
        if ((int)$asset['sale_order_id'] > 0) {
            $sale = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset['sale_order_id']]])->findOrEmpty();
            $asset['sale_order'] = $sale->isEmpty() ? null : $sale->toArray();
        }
        $asset['asset_ledgers'] = ErpAssetLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])
            ->order('id desc')->limit(30)->select()->toArray();
        $asset['account_ledgers'] = ErpAccountLedger::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])
            ->order('id desc')->limit(30)->select()->toArray();
        return $asset;
    }

    public function ledgerPage(array $where): array
    {
        (new ErpLedgerService())->ensureAssetLedgerSchema();
        $query = ErpAssetLedger::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('ledger_no|asset_no|imei|model|party_name|source_no|remark', '%' . $kw . '%');
        }
        if (!empty($where['asset_id'])) {
            $query->where('asset_id', '=', (int)$where['asset_id']);
        }
        if (!empty($where['action'])) {
            $query->where('action', '=', (string)$where['action']);
        }
        if (!empty($where['source_type'])) {
            $query->where('source_type', '=', (string)$where['source_type']);
        }
        if (!empty($where['start_time'])) {
            $query->where('occurred_at', '>=', (int)$where['start_time']);
        }
        if (!empty($where['end_time'])) {
            $query->where('occurred_at', '<=', (int)$where['end_time']);
        }
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function updateFlow(int $id, array $data): void
    {
        $this->ensureSchema();
        $asset = $this->findAsset($id);
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) {
            throw new CommonException('只有库存中的设备才能调整流转状态');
        }
        $refurbishStatus = (string)($data['refurbish_status'] ?? '');
        $saleTarget = (string)($data['sale_target'] ?? '');
        $listingStatus = (string)($data['listing_status'] ?? '');
        $allowedRefurbish = ['none', 'pending', 'processing', 'done'];
        $allowedTarget = ['unset', 'peer', 'mall'];
        $allowedListing = ['none', 'need_photo', 'need_price', 'ready', 'listed'];
        $save = ['update_at' => time()];
        $changes = [];
        if ($refurbishStatus !== '') {
            if (!in_array($refurbishStatus, $allowedRefurbish, true)) {
                throw new CommonException('整备状态不正确');
            }
            if ($refurbishStatus !== (string)$asset->refurbish_status) {
                $save['refurbish_status'] = $refurbishStatus;
                $changes[] = '整备状态';
            }
        }
        if ($saleTarget !== '') {
            if (!in_array($saleTarget, $allowedTarget, true)) {
                throw new CommonException('销售去向不正确');
            }
            if ($saleTarget !== (string)$asset->sale_target) {
                $save['sale_target'] = $saleTarget;
                $changes[] = '销售去向';
            }
            $autoListingStatus = '';
            if ($saleTarget === 'peer' && $listingStatus === '') {
                $autoListingStatus = 'none';
            }
            if ($saleTarget === 'mall' && $listingStatus === '') {
                $autoListingStatus = ((string)$asset->image_urls === '') ? 'need_photo' : (((float)$asset->estimate_sale_price <= 0) ? 'need_price' : 'ready');
            }
            if ($autoListingStatus !== '' && $autoListingStatus !== (string)$asset->listing_status) {
                $save['listing_status'] = $autoListingStatus;
                $changes[] = '上架状态';
            }
        }
        if ($listingStatus !== '') {
            if (!in_array($listingStatus, $allowedListing, true)) {
                throw new CommonException('上架状态不正确');
            }
            if ($listingStatus !== (string)$asset->listing_status) {
                $save['listing_status'] = $listingStatus;
                $changes[] = '上架状态';
            }
        }
        if (array_key_exists('estimate_sale_price', $data) && $data['estimate_sale_price'] !== null) {
            $estimate = round((float)$data['estimate_sale_price'], 2);
            if ($estimate < 0) {
                throw new CommonException('预计卖价不能小于0');
            }
            if (abs($estimate - round((float)$asset->estimate_sale_price, 2)) > 0.0001) {
                $save['estimate_sale_price'] = $estimate;
                $changes[] = '预计卖价';
            }
        }
        if (array_key_exists('image_urls', $data) && $data['image_urls'] !== null) {
            $imageUrls = trim((string)$data['image_urls']);
            if ($imageUrls !== (string)$asset->image_urls) {
                $save['image_urls'] = $imageUrls;
                $changes[] = '图片';
            }
        }
        if (array_key_exists('quality_remark', $data) && $data['quality_remark'] !== null) {
            $qualityRemark = trim((string)$data['quality_remark']);
            if ($qualityRemark !== (string)$asset->quality_remark) {
                $save['quality_remark'] = $qualityRemark;
                $changes[] = '备注';
            }
        }
        if (count($save) <= 1) {
            throw new CommonException('没有需要保存的内容');
        }
        $before = (string)$asset->refurbish_status . '/' . (string)$asset->sale_target . '/' . (string)$asset->listing_status;
        $asset->save($save);
        $afterAsset = $this->findAsset($id);
        $after = (string)$afterAsset->refurbish_status . '/' . (string)$afterAsset->sale_target . '/' . (string)$afterAsset->listing_status;
        (new ErpLedgerService())->asset([
            'asset_id' => $id,
            'action' => 'flow',
            'before_status' => $before,
            'after_status' => $after,
            'source_type' => 'asset',
            'source_id' => $id,
            'remark' => trim((string)($data['remark'] ?? '')) ?: ('更新' . implode('、', array_unique($changes))),
        ]);
    }

    public function adjustCost(int $id, float $afterCost, string $reason = '', bool $syncPayable = true): bool
    {
        $this->ensureSchema();
        if ($afterCost < 0) {
            throw new CommonException('调整后成本不能小于0');
        }
        $asset = $this->findAsset($id);
        $beforeCost = round((float)$asset->total_cost, 2);
        $delta = round($afterCost - $beforeCost, 2);
        if (abs($delta) <= 0) {
            throw new CommonException('调整金额不能为0');
        }
        $purchaseItemId = (int)$asset->purchase_item_id;
        if ($purchaseItemId <= 0) {
            $item = ErpPurchaseItem::where([['site_id', '=', $this->site_id], ['asset_id', '=', $id]])->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('采购明细不存在，无法调整成本');
            }
            $purchaseItemId = (int)$item->id;
        }

        return (new ErpPurchaseService())->adjustCost(
            $purchaseItemId,
            $delta,
            $reason !== '' ? $reason : '移动端成本调整'
        );
    }

    private function findAsset(int $id): ErpAsset
    {
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($asset->isEmpty()) {
            throw new CommonException('设备不存在');
        }
        return $asset;
    }

    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;
        $assetTable = (new ErpAsset())->getTable();
        $this->ensureColumn($assetTable, 'category_id', "`category_id` int NOT NULL DEFAULT 0 COMMENT '商品分类ID' AFTER `spec`");
        $this->ensureColumn($assetTable, 'category_name', "`category_name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品分类名称快照' AFTER `category_id`");
        $this->ensureColumn($assetTable, 'category_path', "`category_path` varchar(255) NOT NULL DEFAULT '' COMMENT '商品分类路径' AFTER `category_name`");
        $this->ensureColumn($assetTable, 'refurbish_status', "`refurbish_status` varchar(20) NOT NULL DEFAULT 'none' COMMENT 'none无需/pending待整备/processing整备中/done已完成' AFTER `total_cost`");
        $this->ensureColumn($assetTable, 'sale_target', "`sale_target` varchar(20) NOT NULL DEFAULT 'unset' COMMENT 'unset未定/peer卖同行/mall上商城' AFTER `refurbish_status`");
        $this->ensureColumn($assetTable, 'listing_status', "`listing_status` varchar(20) NOT NULL DEFAULT 'none' COMMENT 'none无需/need_photo待拍照/need_price待定价/ready可上架/listed已上架' AFTER `sale_target`");
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $rows = Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!empty($rows)) {
            return;
        }
        Db::execute("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }
}
