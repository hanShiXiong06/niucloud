<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpRecycleDeviceIdentity;
use core\exception\CommonException;

/** 外部插件按自身设备ID发起付款，ERP负责定位并结清设备级应付。 */
class ErpSourcePayableSettlementService extends ErpExternalContractService
{
    public const EVENT_NAME = 'ErpSourcePayableSettlementRequested';
    public const CONTRACT_NAME = 'erp.source_payable.settlement_requested.v1';
    public const CONTRACT_VERSION = 1;

    public function consume(array $event): array
    {
        $payload = $this->normalizePayload($event);
        return $this->consumeOnce($payload, self::EVENT_NAME, fn(array $request): array => $this->settle($request), false);
    }

    private function normalizePayload(array $event): array
    {
        $rawDeviceIds = $event['source_device_ids'] ?? [];
        if (!is_array($rawDeviceIds)) throw new CommonException('付款来源设备列表格式不正确');
        foreach ($rawDeviceIds as $deviceId) {
            if (ErpRecycleDeviceIdentity::positiveId($deviceId) <= 0) throw new CommonException('来源设备ID无效，已拒绝整批付款');
        }
        $deviceIds = array_values(array_unique(array_map([ErpRecycleDeviceIdentity::class, 'positiveId'], $rawDeviceIds)));
        if ($deviceIds === []) throw new CommonException('付款请求没有选择来源设备');
        $accountId = max(0, (int)($event['capital_account_id'] ?? 0));
        if ($accountId <= 0) throw new CommonException('付款必须选择ERP资金账户');

        return array_merge($this->normalizeEnvelope($event, self::CONTRACT_NAME, self::CONTRACT_VERSION), [
            'event_name' => self::CONTRACT_NAME,
            'event_version' => self::CONTRACT_VERSION,
            'source_device_ids' => $deviceIds,
            'capital_account_id' => $accountId,
            'voucher_urls' => $this->voucherUrls($event['voucher_urls'] ?? []),
            'remark' => mb_substr(trim((string)($event['remark'] ?? '')), 0, 255),
        ]);
    }

    private function settle(array $payload): array
    {
        $siteId = (int)$payload['site_id'];
        $sourcePlugin = (string)$payload['source_plugin'];
        $sourceDeviceIds = array_map('strval', (array)$payload['source_device_ids']);
        $assets = $sourcePlugin === 'hsx_recycle'
            ? (new ErpRecycleDeviceIdentityService())->uniqueAssets($siteId, $sourceDeviceIds)
            : ErpAsset::where([
                ['site_id', '=', $siteId],
                ['source_plugin', '=', $sourcePlugin],
            ])->whereIn('source_id', $sourceDeviceIds)->field('id,source_id')->select()->toArray();
        if ($assets === []) throw new CommonException('所选设备尚未同步到ERP，不能付款');

        // 回收普通采购的 source_id 是来源订单ID，已由统一解析器逐设备确证，不能再拿它比设备ID。
        $resolvedSourceIds = $sourcePlugin === 'hsx_recycle' ? $sourceDeviceIds
            : array_values(array_unique(array_map('strval', array_column($assets, 'source_id'))));
        $missingSourceIds = array_values(array_diff($sourceDeviceIds, $resolvedSourceIds));
        if ($missingSourceIds !== []) {
            throw new CommonException('部分设备尚未同步到ERP，已拒绝整批付款：' . implode('、', $missingSourceIds));
        }

        $assetIds = array_values(array_unique(array_map('intval', array_column($assets, 'id'))));
        $payableQuery = ErpPayable::where([['site_id', '=', $siteId]]);
        if ($sourcePlugin === 'hsx_recycle') {
            $ownership = (new ErpRecycleDeviceIdentityService())->paymentOwnership($siteId, $sourceDeviceIds);
            foreach ($ownership as $entry) {
                if (!empty($entry['ambiguous'])) throw new CommonException('回收设备应付关联存在歧义，已拒绝整批付款，请先核对应付明细');
                if (empty($entry['has_payable'])) throw new CommonException('部分设备尚未形成有效ERP应付，已拒绝整批付款');
            }
            $payableQuery->whereIn('origin_plugin', ['', 'hsx_recycle'])
                ->whereIn('source_type', ['purchase', 'purchase_asset', 'consignment_sale']);
            $payableQuery->where(function ($query) use ($assetIds) {
                $query->whereIn('asset_id', $assetIds)->whereOr(function ($legacy) use ($assetIds) {
                    $legacy->where('source_type', '=', 'purchase_asset')->whereIn('source_id', $assetIds);
                });
            });
        } else {
            $payableQuery->where('origin_plugin', '=', $sourcePlugin)->whereIn('asset_id', $assetIds);
        }
        $allPayables = $payableQuery->order('party_id asc,id asc')->select()->toArray();
        if ($allPayables === []) throw new CommonException('所选设备尚未形成ERP应付，请先完成入库或代卖成交');

        $open = array_values(array_filter($allPayables, static fn(array $row): bool =>
            in_array((string)($row['status'] ?? ''), [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)
            && round((float)$row['amount'] - (float)$row['settled_amount'], 2) > 0
        ));
        if ($open === []) {
            return ['source_device_ids' => array_map('intval', $payload['source_device_ids']), 'settlements' => [], 'already_settled' => true];
        }

        $byParty = [];
        foreach ($open as $row) $byParty[(int)$row['party_id']][] = $row;
        $settlements = [];
        $finance = new ErpFinanceService();
        foreach ($byParty as $partyId => $rows) {
            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'payable_id' => (int)$row['id'],
                    'amount' => round((float)$row['amount'] - (float)$row['settled_amount'], 2),
                ];
            }
            $requestId = ErpIdempotency::child((string)$payload['event_id'], 'party-' . $partyId);
            $settlementId = $finance->confirmPayableItemsPayment((int)$partyId, $items, [
                'request_id' => $requestId !== '' ? $requestId : (string)$payload['event_id'],
                'capital_account_id' => (int)$payload['capital_account_id'],
                'voucher_urls' => (array)$payload['voucher_urls'],
                'remark' => (string)$payload['remark'] ?: '来源插件设备付款',
            ]);
            $settlement = ErpSettlement::where([['site_id', '=', $siteId], ['id', '=', $settlementId]])
                ->field('id,settlement_no,amount,capital_account_id,capital_account_name,confirmed_at')->findOrEmpty();
            $settlements[] = $settlement->isEmpty() ? ['id' => $settlementId] : $settlement->toArray();
        }

        return [
            'source_device_ids' => array_map('intval', $payload['source_device_ids']),
            'payable_ids' => array_map('intval', array_column($open, 'id')),
            'settlements' => $settlements,
            'already_settled' => false,
        ];
    }

    private function voucherUrls(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $value)));
        }
        if (!is_array($value)) return [];
        return array_slice(array_values(array_unique(array_filter(array_map(static fn($url): string => mb_substr(trim((string)$url), 0, 500), $value)))), 0, 9);
    }
}
