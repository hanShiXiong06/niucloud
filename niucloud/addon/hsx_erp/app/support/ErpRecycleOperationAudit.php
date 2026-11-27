<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use addon\hsx_erp\app\dict\{ErpDict, FinanceDict};
use think\facade\{Db, Event, Log};

/** Optional local audit projection. No new financial operations, dispatch, or vendor credentials. */
final class ErpRecycleOperationAudit
{
    public const EVENT = 'HsxRecycleBusinessOperationRecorded';
    /** Bound to one authorized HTTP request, never shared across worker requests or CLI jobs. */
    private static ?\WeakMap $requests = null;

    public static function beginRequest(object $request): object
    {
        self::$requests ??= new \WeakMap();
        return self::$requests[$request] = (object)['domain' => false, 'changed' => false];
    }

    public static function endRequest(object $request): void
    {
        if (self::$requests !== null) unset(self::$requests[$request]);
    }

    private static function requestState(): ?object
    {
        if (!self::$requests || !count(self::$requests)) return null;
        return self::$requests[request()] ?? null;
    }

    /** The resolve-contact endpoint is also used as a pure lookup. */
    public static function markContactRoleChange(): void
    {
        try {
            if ($state = self::requestState()) $state->changed = true;
        } catch (\Throwable $ignored) {}
    }

    /** A device-level original payable is positive evidence; a legacy whole-purchase asset list is not. */
    public static function settlementAssetIds(array $targets): array
    {
        $ids = [];
        foreach ($targets as $target) {
            $id = ErpRecycleDeviceIdentity::positiveId($target['source_id'] ?? 0);
            $assets = $target['assets'] ?? [];
            if ($id && ($target['target_type'] ?? '') === ErpDict::TARGET_PAYABLE && ($target['source_type'] ?? '') === 'purchase_asset'
                && count($assets) === 1 && (int)($assets[0]['id'] ?? 0) === $id) $ids[$id] = true;
        }
        return array_keys($ids);
    }

    /** Only stored source identities count. A serial, an order-level source_id, or a sibling's ID isn't a device. */
    public static function deviceIdsForAssets(int $siteId, array $assetIds): array
    {
        if ($siteId <= 0) return [];
        $ids = array_unique(array_filter(array_map([ErpRecycleDeviceIdentity::class, 'positiveId'], $assetIds)));
        $devices = [];
        foreach (array_chunk($ids, 100) as $chunk) {
            $assets = Db::name('erp_asset')->where('site_id', $siteId)->where('source_plugin', 'hsx_recycle')
                ->whereIn('id', $chunk)->field('id,site_id,purchase_order_id,purchase_item_id,source_plugin,source_type,source_id,spec_json')->select()->toArray();
            if (!$assets) continue;
            $items = Db::name('erp_purchase_item')->where('site_id', $siteId)->whereIn('asset_id', array_column($assets,'id'))
                ->field('id,site_id,asset_id,purchase_order_id,spec_json')->select()->toArray();
            $grouped = [];
            foreach ($items as $item) $grouped[(int)$item['asset_id']][] = $item;
            foreach ($assets as $asset) {
                $identity = ErpRecycleDeviceIdentity::resolve($asset, $grouped[(int)$asset['id']] ?? []);
                if (!$identity['ambiguous'] && $identity['device_id'] > 0) $devices[$identity['device_id']] = true;
            }
        }
        $ids = array_keys($devices);
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    public static function record(string $kind, array $row, array $assetIds = []): void
    {
        try {
            if (!Event::hasListener(self::EVENT)) return;
            $event = self::summary($kind, $row);
            if (!$event) return;
            $asset = $row['asset_id'] ?? 0;
            if ($kind === 'operation' && ($row['source_type'] ?? '') === 'asset') $asset = $row['source_id'] ?? 0;
            $assetIds[] = $asset;
            $event['device_ids'] = self::deviceIdsForAssets($event['site_id'], $assetIds);
            event(self::EVENT, $event);
            if ($state = self::requestState()) $state->domain = true;
        } catch (\Throwable $e) {
            // A broken optional subscriber must never retry a completed payment or cancel a valid stock action.
            try { Log::warning('ERP统一操作日志未投影', ['site_id'=>(int)($row['site_id'] ?? 0),
                'kind'=>$kind, 'id'=>(int)($row['id'] ?? 0), 'error_type'=>get_class($e)]); } catch (\Throwable $ignored) {}
        }
    }

    /** No remarks, account names/numbers, request JSON, attachment URLs or monetary balances. */
    public static function summary(string $kind, array $r): ?array
    {
        $site = (int)($r['site_id'] ?? 0); $id = (int)($r['id'] ?? 0);
        if ($site <= 0 || $id <= 0) return null;
        $no = $r['source_no'] ?? '';
        $at = (int)($r['create_at'] ?? 0) ?: (int)($r['occurred_at'] ?? 0);
        switch ($kind) {
            case 'asset':
                $code = (string)($r['action'] ?? '');
                $label = ErpDict::getLedgerActionMap()[$code] ?? '设备库存变动';
                break;
            case 'quantity':
                $code = (string)($r['biz_type'] ?? '');
                $label = ($r['direction'] ?? '') === 'in' ? '标品库存入库' : (($r['direction'] ?? '') === 'out' ? '标品库存出库' : '标品库存调整');
                $no = $r['biz_no'] ?? '';
                break;
            case 'account':
                $code = (string)($r['biz_type'] ?? '');
                // Payment allocations and offset rows are evidence for one voucher, not additional payments.
                if (in_array($code, ['payment','receipt','offset'], true)) return null;
                $label = FinanceDict::getBizTypeMap()[$code] ?? '往来账目变动';
                break;
            case 'money':
                if ((int)($r['settlement_id'] ?? 0) > 0) return null; // represented by the confirmed voucher below
                $code = (string)($r['direction'] ?? '');
                $label = $code === 'out' ? '资金支出登记' : ($code === 'in' ? '资金收入登记' : '资金流水登记');
                break;
            case 'settlement':
                if (($r['status'] ?? '') !== 'confirmed' || (int)($r['confirmed_at'] ?? 0) <= 0) return null;
                $code = (string)($r['settlement_type'] ?? '');
                $label = ['payment'=>'登记付款凭证','receipt'=>'登记收款凭证','offset'=>'登记折账凭证'][$code] ?? '登记结算凭证';
                $no = $r['settlement_no'] ?? ''; $at = (int)$r['confirmed_at'];
                break;
            case 'operation':
                $code = (string)($r['action'] ?? '');
                if (in_array($code, ['purchase_cash_settled','consignment_inbound'], true)) return null;
                $label = ['opening_posted'=>'期初建账确认','stocktake_create'=>'创建盘点任务','stocktake_submit'=>'提交盘点差异',
                    'stocktake_complete'=>'确认盘点完成','stocktake_cancel'=>'撤销盘点任务','purchase_cancel'=>'撤销采购单',
                    'sale_cancel'=>'撤销销售单','sale_item_cancel'=>'撤销销售明细','external_sale_refund'=>'登记外部退款',
                    'mall_sale_detail_supplement'=>'补录商城销售明细','mall_inventory_link'=>'关联商城库存','consignment_buyout'=>'确认代卖买断'][$code] ?? '登记ERP业务操作';
                break;
            default: return null;
        }
        if ($at <= 0) return null;
        if (!preg_match('/^[a-z0-9_.:-]{1,50}$/D', $code)) $code = 'record';
        return ['site_id'=>$site,'source'=>'erp','event_id'=>$kind.':'.$id,'action'=>$kind.'.'.$code,'label'=>$label,
            'result'=>'recorded','business_no'=>(string)($no !== '' ? $no : ($r['ledger_no'] ?? '')),
            'operator_id'=>(int)($r['operator_uid'] ?? 0),'operator_name'=>(string)($r['operator_name'] ?? ''),
            'occurred_at'=>$at,'order_ids'=>[],'device_ids'=>[]];
    }
}
