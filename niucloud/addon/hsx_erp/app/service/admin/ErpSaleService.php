<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpSaleService extends BaseAdminService
{
    public function stockPage(array $where): array
    {
        $query = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', ErpDict::ASSET_IN_STOCK],
        ]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('asset_no|imei|sn|model|spec|party_name|warehouse_name|location_name', '%' . $kw . '%');
        }
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function getPage(array $where): array
    {
        $query = ErpSaleOrder::where([['site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('sale_no|party_name|sale_channel', '%' . $kw . '%');
        }
        if (!empty($where['finance_status'])) {
            $query->where('finance_status', '=', (string)$where['finance_status']);
        }
        return $query->order('id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $order = $this->findOrder($id)->toArray();
        $order['items'] = ErpSaleItem::where([
            ['site_id', '=', $this->site_id],
            ['sale_order_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        $order['receivables'] = ErpReceivable::where([
            ['site_id', '=', $this->site_id],
            ['source_type', '=', 'sale'],
            ['source_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        return $order;
    }

    public function create(array $data): int
    {
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请选择要出库的机器');
        }
        $partyName = trim((string)($data['party_name'] ?? ''));
        if ($partyName === '') {
            throw new CommonException('请填写销售客户/渠道');
        }
        $now = time();
        $orderId = 0;
        Db::transaction(function () use ($data, $items, $partyName, $now, &$orderId) {
            $party = $this->ensureParty((int)($data['party_id'] ?? 0), $partyName);
            $partyName = (string)$party->party_name;
            $saleNo = ErpLedgerService::makeNo('SO');
            $totalAmount = 0.0;
            $totalCost = 0.0;
            $resolved = [];
            foreach ($items as $item) {
                $assetId = (int)($item['asset_id'] ?? 0);
                $asset = ErpAsset::where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $assetId],
                    ['status', '=', ErpDict::ASSET_IN_STOCK],
                ])->findOrEmpty();
                if ($asset->isEmpty()) {
                    throw new CommonException('库存机器不存在或不可销售');
                }
                $price = round((float)($item['sale_price'] ?? 0), 2);
                if ($price <= 0) {
                    throw new CommonException('销售金额必须大于0');
                }
                $cost = round((float)$asset->total_cost, 2);
                $totalAmount += $price;
                $totalCost += $cost;
                $resolved[] = [$asset, $price, trim((string)($item['remark'] ?? ''))];
            }
            $profit = round($totalAmount - $totalCost, 2);
            $order = ErpSaleOrder::create([
                'site_id' => $this->site_id,
                'sale_no' => $saleNo,
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'sale_channel' => trim((string)($data['sale_channel'] ?? '')),
                'settle_method' => trim((string)($data['settle_method'] ?? '')),
                'salesman_uid' => (int)$this->uid,
                'salesman_name' => (string)$this->username,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'received_amount' => 0,
                'receivable_amount' => $totalAmount,
                'finance_status' => ErpDict::STATUS_PENDING,
                'status' => ErpDict::STATUS_COMPLETED,
                'operator_uid' => (int)$this->uid,
                'operator_name' => (string)$this->username,
                'sale_at' => (int)($data['sale_at'] ?? $now),
                'remark' => trim((string)($data['remark'] ?? '')),
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $orderId = (int)$order->id;
            foreach ($resolved as [$asset, $price, $remark]) {
                $cost = round((float)$asset->total_cost, 2);
                $item = ErpSaleItem::create([
                    'site_id' => $this->site_id,
                    'sale_order_id' => $orderId,
                    'asset_id' => (int)$asset->id,
                    'imei' => (string)$asset->imei,
                    'model' => (string)$asset->model,
                    'cost' => $cost,
                    'sale_price' => $price,
                    'profit' => round($price - $cost, 2),
                    'status' => ErpDict::ASSET_SOLD,
                    'remark' => $remark,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $asset->save([
                    'sale_order_id' => $orderId,
                    'sale_item_id' => (int)$item->id,
                    'sale_price' => $price,
                    'profit' => round($price - $cost, 2),
                    'status' => ErpDict::ASSET_SOLD,
                    'update_at' => $now,
                ]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'sold',
                    'before_status' => ErpDict::ASSET_IN_STOCK,
                    'after_status' => ErpDict::ASSET_SOLD,
                    'source_type' => 'sale',
                    'source_id' => $orderId,
                    'remark' => '销售出库',
                ]);
                (new ErpLedgerService())->account([
                    'biz_type' => 'sale',
                    'direction' => 'increase',
                    'amount' => $price,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'sale',
                    'source_id' => $orderId,
                    'source_no' => $saleNo,
                    'remark' => '销售应收',
                ]);
            }
            ErpReceivable::create([
                'site_id' => $this->site_id,
                'receivable_no' => ErpLedgerService::makeNo('AR'),
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'source_type' => 'sale',
                'source_id' => $orderId,
                'source_no' => $saleNo,
                'amount' => $totalAmount,
                'settled_amount' => 0,
                'status' => ErpDict::STATUS_PENDING,
                'occurred_at' => (int)($data['sale_at'] ?? $now),
                'remark' => '销售应收',
                'create_at' => $now,
                'update_at' => $now,
            ]);
        });
        return $orderId;
    }

    private function ensureParty(int $id, string $name): ErpParty
    {
        if ($id > 0) {
            $party = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
            if (!$party->isEmpty()) {
                return $party;
            }
        }
        $party = ErpParty::where([['site_id', '=', $this->site_id], ['party_name', '=', $name]])->findOrEmpty();
        if (!$party->isEmpty()) {
            return $party;
        }
        $now = time();
        return ErpParty::create([
            'site_id' => $this->site_id,
            'party_no' => ErpLedgerService::makeNo('PT'),
            'party_name' => $name,
            'party_type' => 'customer',
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function findOrder(int $id): ErpSaleOrder
    {
        $order = ErpSaleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('销售单不存在');
        }
        return $order;
    }
}
