<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use addon\hsx_erp\app\model\ErpPurchaseOrder;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class ErpPurchaseService extends BaseAdminService
{
    private static bool $schemaEnsured = false;

    public function getPage(array $where): array
    {
        (new ErpWarehouseService())->ensureReady();
        $this->ensureSchema();
        $orderTable = (new ErpPurchaseOrder())->getTable();
        $query = ErpAsset::alias('a')
            ->leftJoin($orderTable . ' o', 'o.id = a.purchase_order_id AND o.site_id = a.site_id')
            ->where([['a.site_id', '=', $this->site_id]]);
        if (!empty($where['keyword'])) {
            $kw = trim((string)$where['keyword']);
            $query->whereLike('a.asset_no|a.imei|a.sn|a.model|a.spec|a.party_name|a.warehouse_name|a.location_name|o.purchase_no|o.m_no', '%' . $kw . '%');
        }
        if (!empty($where['finance_status'])) {
            $query->where('o.finance_status', '=', (string)$where['finance_status']);
        }
        return $query->field([
            'a.id',
            'a.asset_no',
            'a.purchase_order_id',
            'a.purchase_item_id',
            'a.party_id',
            'a.party_name',
            'a.warehouse_id',
            'a.warehouse_name',
            'a.location_id',
            'a.location_name',
            'a.imei',
            'a.sn',
            'a.model',
            'a.spec',
            'a.purchase_cost',
            'a.adjust_cost',
            'a.refurbish_cost',
            'a.total_cost',
            'a.status',
            'a.create_at',
            'o.purchase_no',
            'o.m_no',
            'o.total_cost as order_total_cost',
            'o.paid_amount',
            'o.payable_amount',
            'o.finance_status',
            'o.purchaser_name',
            'o.inspector_name',
            'o.purchase_at',
            'o.capital_account_name',
        ])->order('a.id desc')->paginate([
            'list_rows' => (int)($where['limit'] ?? 15),
            'page' => (int)($where['page'] ?? 1),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $order = $this->findOrder($id)->toArray();
        $order['items'] = ErpPurchaseItem::where([
            ['site_id', '=', $this->site_id],
            ['purchase_order_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        $assetIds = ErpAsset::where([
            ['site_id', '=', $this->site_id],
            ['purchase_order_id', '=', $id],
        ])->column('id');
        $payableQuery = ErpPayable::where([['site_id', '=', $this->site_id]]);
        if (!empty($assetIds)) {
            $payableQuery->where(function ($query) use ($id, $assetIds) {
                $query->where([['source_type', '=', 'purchase'], ['source_id', '=', $id]])
                    ->whereOr(function ($q) use ($assetIds) {
                        $q->where('source_type', '=', 'purchase_asset')->whereIn('source_id', $assetIds);
                    });
            });
        } else {
            $payableQuery->where([['source_type', '=', 'purchase'], ['source_id', '=', $id]]);
        }
        $order['payables'] = $payableQuery->order('id asc')->select()->toArray();
        return $order;
    }

    public function create(array $data): int
    {
        $this->ensureSchema();
        $items = (array)($data['items'] ?? []);
        if (empty($items)) {
            throw new CommonException('请至少录入一台机器');
        }
        $partyName = trim((string)($data['party_name'] ?? ''));
        if ($partyName === '') {
            throw new CommonException('请填写采购渠道/客户');
        }
        $now = time();
        $orderId = 0;
        Db::transaction(function () use ($data, $items, $partyName, $now, &$orderId) {
            $party = $this->ensureParty((int)($data['party_id'] ?? 0), $partyName, (string)($data['m_no'] ?? ''), 'supplier');
            $partyName = (string)$party->party_name;
            $purchaseNo = ErpLedgerService::makeNo('PO');
            $totalCost = 0.0;
            foreach ($items as $item) {
                $totalCost += round((float)($item['purchase_cost'] ?? 0), 2);
            }
            if ($totalCost <= 0) {
                throw new CommonException('采购成本必须大于0');
            }
            $paidAmount = round((float)($data['paid_amount'] ?? 0), 2);
            if ($paidAmount < 0) {
                throw new CommonException('本次付款不能小于0');
            }
            if ($paidAmount > $totalCost + 0.0001) {
                throw new CommonException('本次付款不能大于采购成本');
            }
            $capitalAccountId = (int)($data['capital_account_id'] ?? 0);
            $capitalAccountName = '';
            $settleMethod = trim((string)($data['settle_method'] ?? ''));
            if ($paidAmount > 0) {
                $account = $this->resolveCapitalAccount($capitalAccountId);
                $capitalAccountId = (int)$account->id;
                $capitalAccountName = (string)$account->account_name;
                $settleMethod = $this->accountTypeLabel((string)$account->account_type);
            }
            [$warehouse, $location] = (new ErpWarehouseService())->validateInboundLocation(
                (int)($data['warehouse_id'] ?? 0),
                (int)($data['location_id'] ?? 0)
            );
            $purchaser = $this->resolveStaff((int)($data['purchaser_uid'] ?? 0));
            $warehouseId = (int)$warehouse->id;
            $warehouseName = (string)$warehouse->warehouse_name;
            $locationId = (int)$location->id;
            $locationName = (string)$location->location_name;
            $order = ErpPurchaseOrder::create([
                'site_id' => $this->site_id,
                'purchase_no' => $purchaseNo,
                'party_id' => (int)$party->id,
                'party_name' => $partyName,
                'm_no' => trim((string)($data['m_no'] ?? '')),
                'purchase_channel' => trim((string)($data['purchase_channel'] ?? '')),
                'settle_method' => $settleMethod,
                'capital_account_id' => $capitalAccountId,
                'capital_account_name' => $capitalAccountName,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouseName,
                'location_id' => $locationId,
                'location_name' => $locationName,
                'purchaser_uid' => (int)$purchaser['uid'],
                'purchaser_name' => (string)$purchaser['name'],
                'inspector_uid' => (int)$this->uid,
                'inspector_name' => (string)$this->username,
                'total_cost' => $totalCost,
                'paid_amount' => 0,
                'payable_amount' => $totalCost,
                'finance_status' => ErpDict::STATUS_PENDING,
                'status' => ErpDict::STATUS_COMPLETED,
                'source_plugin' => (string)($data['source_plugin'] ?? 'erp'),
                'source_type' => (string)($data['source_type'] ?? 'manual'),
                'source_id' => (string)($data['source_id'] ?? ''),
                'operator_uid' => (int)$this->uid,
                'operator_name' => (string)$this->username,
                'purchase_at' => (int)($data['purchase_at'] ?? $now),
                'remark' => trim((string)($data['remark'] ?? '')),
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $orderId = (int)$order->id;
            $payableItems = [];
            foreach ($items as $item) {
                $cost = round((float)($item['purchase_cost'] ?? 0), 2);
                if ($cost <= 0) {
                    throw new CommonException('机器采购成本必须大于0');
                }
                $purchaseItem = ErpPurchaseItem::create([
                    'site_id' => $this->site_id,
                    'purchase_order_id' => $orderId,
                    'imei' => trim((string)($item['imei'] ?? '')),
                    'sn' => trim((string)($item['sn'] ?? '')),
                    'model' => trim((string)($item['model'] ?? '')),
                    'spec' => trim((string)($item['spec'] ?? '')),
                    'purchase_cost' => $cost,
                    'adjust_cost' => 0,
                    'total_cost' => $cost,
                    'status' => ErpDict::ASSET_IN_STOCK,
                    'remark' => trim((string)($item['remark'] ?? '')),
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $asset = ErpAsset::create([
                    'site_id' => $this->site_id,
                    'asset_no' => ErpLedgerService::makeNo('AS'),
                    'purchase_order_id' => $orderId,
                    'purchase_item_id' => (int)$purchaseItem->id,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouseName,
                    'location_id' => $locationId,
                    'location_name' => $locationName,
                    'imei' => trim((string)($item['imei'] ?? '')),
                    'sn' => trim((string)($item['sn'] ?? '')),
                    'model' => trim((string)($item['model'] ?? '')),
                    'spec' => trim((string)($item['spec'] ?? '')),
                    'purchase_cost' => $cost,
                    'total_cost' => $cost,
                    'status' => ErpDict::ASSET_IN_STOCK,
                    'source_plugin' => (string)($data['source_plugin'] ?? 'erp'),
                    'source_type' => (string)($data['source_type'] ?? 'manual'),
                    'source_id' => (string)($data['source_id'] ?? ''),
                    'remark' => trim((string)($item['remark'] ?? '')),
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $purchaseItem->save(['asset_id' => (int)$asset->id, 'update_at' => $now]);
                (new ErpLedgerService())->asset([
                    'asset_id' => (int)$asset->id,
                    'action' => 'inbound',
                    'after_status' => ErpDict::ASSET_IN_STOCK,
                    'source_type' => 'purchase',
                    'source_id' => $orderId,
                    'remark' => '采购入库',
                ]);
                (new ErpLedgerService())->account([
                    'biz_type' => 'purchase',
                    'direction' => 'increase',
                    'amount' => $cost,
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'asset_id' => (int)$asset->id,
                    'source_type' => 'purchase',
                    'source_id' => $orderId,
                    'source_no' => $purchaseNo,
                    'remark' => '采购成本',
                ]);
                $payable = ErpPayable::create([
                    'site_id' => $this->site_id,
                    'payable_no' => ErpLedgerService::makeNo('AP'),
                    'party_id' => (int)$party->id,
                    'party_name' => $partyName,
                    'source_type' => 'purchase_asset',
                    'source_id' => (int)$asset->id,
                    'source_no' => (string)$asset->asset_no,
                    'amount' => $cost,
                    'settled_amount' => 0,
                    'status' => ErpDict::STATUS_PENDING,
                    'occurred_at' => (int)($data['purchase_at'] ?? $now),
                    'remark' => '设备采购应付',
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $payableItems[] = [
                    'payable_id' => (int)$payable->id,
                    'remain' => $cost,
                ];
            }
            if ($paidAmount > 0) {
                $paymentItems = [];
                $left = $paidAmount;
                foreach ($payableItems as $item) {
                    if ($left <= 0) {
                        break;
                    }
                    $apply = min($left, (float)$item['remain']);
                    if ($apply <= 0) {
                        continue;
                    }
                    $paymentItems[] = ['payable_id' => (int)$item['payable_id'], 'amount' => $apply];
                    $left = round($left - $apply, 2);
                }
                (new ErpFinanceService())->confirmPayableItemsInTransaction((int)$party->id, $paymentItems, [
                    'capital_account_id' => $capitalAccountId,
                    'confirmed_at' => (int)($data['purchase_at'] ?? $now),
                    'remark' => '采购开单付款',
                ]);
            }
        });
        return $orderId;
    }

    public function adjustCost(int $itemId, float $amount, string $remark = ''): bool
    {
        if (abs($amount) <= 0) {
            throw new CommonException('调整金额不能为0');
        }
        Db::transaction(function () use ($itemId, $amount, $remark) {
            $now = time();
            $item = ErpPurchaseItem::where([['site_id', '=', $this->site_id], ['id', '=', $itemId]])->findOrEmpty();
            if ($item->isEmpty()) {
                throw new CommonException('采购明细不存在');
            }
            $order = $this->findOrder((int)$item->purchase_order_id);
            $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item->asset_id]])->findOrEmpty();
            $newAdjust = round((float)$item->adjust_cost + $amount, 2);
            $newTotal = round((float)$item->purchase_cost + $newAdjust, 2);
            if ($newTotal < 0) {
                throw new CommonException('调整后成本不能小于0');
            }
            $item->save(['adjust_cost' => $newAdjust, 'total_cost' => $newTotal, 'update_at' => $now]);
            if (!$asset->isEmpty()) {
                $asset->save([
                    'adjust_cost' => round((float)$asset->adjust_cost + $amount, 2),
                    'total_cost' => round((float)$asset->total_cost + $amount, 2),
                    'update_at' => $now,
                ]);
            }
            $newOrderCost = round((float)$order->total_cost + $amount, 2);
            $newPayableAmount = round($newOrderCost - (float)$order->paid_amount, 2);
            $order->save([
                'total_cost' => $newOrderCost,
                'payable_amount' => max(0, $newPayableAmount),
                'finance_status' => ErpDict::financeStatus($newOrderCost, (float)$order->paid_amount),
                'update_at' => $now,
            ]);
            $payable = ErpPayable::where([
                ['site_id', '=', $this->site_id],
                ['source_type', '=', 'purchase_asset'],
                ['source_id', '=', (int)$item->asset_id],
            ])->findOrEmpty();
            if ($payable->isEmpty()) {
                $payable = ErpPayable::where([
                    ['site_id', '=', $this->site_id],
                    ['source_type', '=', 'purchase'],
                    ['source_id', '=', (int)$order->id],
                ])->findOrEmpty();
            }
            if (!$payable->isEmpty()) {
                $newAmount = round((float)$payable->amount + $amount, 2);
                $payable->save([
                    'amount' => max(0, $newAmount),
                    'status' => ErpDict::financeStatus(max(0, $newAmount), (float)$payable->settled_amount),
                    'update_at' => $now,
                ]);
            }
            (new ErpLedgerService())->account([
                'biz_type' => 'adjust',
                'direction' => $amount > 0 ? 'increase' : 'decrease',
                'amount' => abs(round($amount, 2)),
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'asset_id' => (int)$item->asset_id,
                'source_type' => 'purchase_adjust',
                'source_id' => $itemId,
                'source_no' => (string)$order->purchase_no,
                'remark' => $remark,
            ]);
        });
        return true;
    }

    private function ensureParty(int $id, string $name, string $mNo, string $type): ErpParty
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
            'party_type' => $type,
            'm_no' => $mNo,
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ]);
    }

    private function findOrder(int $id): ErpPurchaseOrder
    {
        $order = ErpPurchaseOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('采购单不存在');
        }
        return $order;
    }

    private function resolveCapitalAccount(int $id): ErpCapitalAccount
    {
        if ($id <= 0) {
            throw new CommonException('请选择付款账户');
        }
        $account = ErpCapitalAccount::where([['site_id', '=', $this->site_id], ['id', '=', $id], ['status', '=', 1]])->findOrEmpty();
        if ($account->isEmpty()) {
            throw new CommonException('付款账户不存在或已停用');
        }
        return $account;
    }

    private function accountTypeLabel(string $type): string
    {
        return match ($type) {
            'wechat' => '微信',
            'alipay' => '支付宝',
            'bank' => '银行卡',
            'cash' => '现金',
            default => '其他',
        };
    }

    private function resolveStaff(int $uid): array
    {
        $uid = $uid > 0 ? $uid : (int)$this->uid;
        $relation = SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $uid],
            ['delete_time', '=', 0],
        ])->findOrEmpty();
        if ($relation->isEmpty() && $uid !== (int)$this->uid) {
            throw new CommonException('采购员不属于当前站点');
        }
        $user = SysUser::where([
            ['uid', '=', $uid],
            ['delete_time', '=', 0],
        ])->field('uid,username,real_name')->findOrEmpty();
        if ($user->isEmpty()) {
            throw new CommonException('采购员不存在或已停用');
        }
        return [
            'uid' => (int)$user->uid,
            'name' => (string)($user->real_name ?: $user->username ?: ('员工#' . $user->uid)),
        ];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;
        $purchaseTable = (new ErpPurchaseOrder())->getTable();
        $this->ensureColumn($purchaseTable, 'capital_account_id', "`capital_account_id` int NOT NULL DEFAULT 0 COMMENT '本次付款账户' AFTER `settle_method`");
        $this->ensureColumn($purchaseTable, 'capital_account_name', "`capital_account_name` varchar(100) NOT NULL DEFAULT '' COMMENT '本次付款账户名称' AFTER `capital_account_id`");
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
