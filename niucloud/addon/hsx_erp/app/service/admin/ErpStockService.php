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
        $saleTable = (new ErpSaleOrder())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($saleTable . ' s', 's.id = a.sale_order_id AND s.site_id = a.site_id')
            ->where([['a.site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|a.category_name|a.party_name|a.warehouse_name|a.location_name|s.sale_no|s.party_name', '%' . $kw . '%');
        }
        if (!empty($where['status'])) {
            $query->where('a.status', '=', (string)$where['status']);
        }
        if (!empty($where['refurbish_status'])) {
            $query->where('a.refurbish_status', '=', (string)$where['refurbish_status']);
        }
        if (!empty($where['sale_target'])) {
            $query->where('a.sale_target', '=', (string)$where['sale_target']);
        }
        if (!empty($where['listing_status'])) {
            $query->where('a.listing_status', '=', (string)$where['listing_status']);
        }
        if (!empty($where['warehouse_id'])) {
            $query->where('a.warehouse_id', '=', (int)$where['warehouse_id']);
        }
        if (!empty($where['location_id'])) {
            $query->where('a.location_id', '=', (int)$where['location_id']);
        }
        if (!empty($where['party_id'])) {
            $query->where('a.party_id', '=', (int)$where['party_id']);
        }
        if (!empty($where['category_id'])) {
            $categoryId = (int)$where['category_id'];
            $query->where(function ($q) use ($categoryId) {
                $q->where('a.category_id', '=', $categoryId)
                    ->whereOr('a.category_path', 'like', '%,' . $categoryId . ',%')
                    ->whereOr('a.category_path', 'like', $categoryId . ',%')
                    ->whereOr('a.category_path', 'like', '%,' . $categoryId)
                    ->whereOr('a.category_path', '=', (string)$categoryId);
            });
        }
        foreach ([
            'asset_no' => 'a.asset_no',
            'imei' => 'a.imei',
            'sn' => 'a.sn',
            'model' => 'a.model',
            'spec' => 'a.spec',
            'party_name' => 'a.party_name',
            'warehouse_name' => 'a.warehouse_name',
            'location_name' => 'a.location_name',
            'category_name' => 'a.category_name',
        ] as $key => $column) {
            if (!empty($where[$key])) {
                $query->whereLike($column, '%' . trim((string)$where[$key]) . '%');
            }
        }
        if (($where['min_cost'] ?? '') !== '') {
            $query->where('a.total_cost', '>=', (float)$where['min_cost']);
        }
        if (($where['max_cost'] ?? '') !== '') {
            $query->where('a.total_cost', '<=', (float)$where['max_cost']);
        }
        if (($where['min_price'] ?? '') !== '') {
            $minPrice = (float)$where['min_price'];
            $query->where(function ($q) use ($minPrice) {
                $q->where('a.retail_price', '>=', $minPrice)->whereOr('a.estimate_sale_price', '>=', $minPrice);
            });
        }
        if (($where['max_price'] ?? '') !== '') {
            $maxPrice = (float)$where['max_price'];
            $query->where(function ($q) use ($maxPrice) {
                $q->where('a.retail_price', '<=', $maxPrice)->whereOr('a.estimate_sale_price', '<=', $maxPrice);
            });
        }
        if (($where['stock_age_min'] ?? '') !== '') {
            $query->where('a.stock_in_at', '<=', time() - max(0, (int)$where['stock_age_min']) * 86400);
        }
        if (($where['stock_age_max'] ?? '') !== '') {
            $query->where('a.stock_in_at', '>=', time() - max(0, (int)$where['stock_age_max']) * 86400);
        }
        if (!empty($where['start_at'])) {
            $query->where('a.stock_in_at', '>=', (int)$where['start_at']);
        }
        if (!empty($where['end_at'])) {
            $query->where('a.stock_in_at', '<=', (int)$where['end_at']);
        }
        return $query->field([
            'a.*',
            's.sale_no',
            's.party_name as sale_party_name',
            's.sale_channel',
            's.sale_at',
            's.finance_status as sale_finance_status',
        ])->order('a.id desc')->paginate([
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
        $allowedStatuses = [ErpDict::ASSET_IN_STOCK, ErpDict::ASSET_SOLD, 'available_for_sale'];
        if (!in_array((string)$asset->status, $allowedStatuses, true)) {
            throw new CommonException('当前设备状态不可调整成本');
        }
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
            $reason !== '' ? $reason : '移动端成本调整',
            $syncPayable
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
