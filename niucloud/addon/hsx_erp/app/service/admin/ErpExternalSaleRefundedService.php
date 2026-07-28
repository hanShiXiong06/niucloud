<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use core\exception\CommonException;

/**
 * 商城线上退款冲销。
 *
 * 退款事件只在原支付渠道确认成功后进入本服务。ERP保留原销售、收款与退款
 * 事实，同时关闭剩余应收；一物一码商品完成全额退款时恢复原仓库存。
 */
class ErpExternalSaleRefundedService extends ErpExternalSaleAccountingService
{
    public const EVENT_NAME = 'ErpExternalSaleRefundedRequested';
    public const CONTRACT_NAME = 'erp.external_sale.refunded_requested.v1';

    /** @var int[] */
    private array $assetOutboxIds = [];

    public function consume(array $event): array
    {
        $payload = $this->normalize($event);
        $result = $this->consumeOnce($payload, self::EVENT_NAME, function (array $request): array {
            return $this->refund($request);
        });
        $this->flushAssetDomainEvents();
        return $result;
    }

    private function normalize(array $event): array
    {
        $payload = $this->normalizeEnvelope($event, self::CONTRACT_NAME, 1);
        $orderId = mb_substr(trim((string)($event['source_order_id'] ?? '')), 0, 80);
        $lineId = mb_substr(trim((string)($event['source_line_id'] ?? '')), 0, 80);
        $amount = round((float)($event['refund_amount'] ?? 0), 2);
        $goodsAmount = round((float)($event['refund_goods_amount'] ?? $amount), 2);
        $deliveryAmount = round((float)($event['refund_delivery_amount'] ?? 0), 2);
        if ($orderId === '' || $lineId === '' || $amount <= 0 || $goodsAmount <= 0) {
            throw new CommonException('外部退款缺少订单、明细或有效金额');
        }
        if ($goodsAmount > $amount + 0.01 || $deliveryAmount > $amount + 0.01) {
            throw new CommonException('外部退款的商品或运费金额不正确');
        }
        return array_merge($payload, [
            'source_order_id' => $orderId,
            'source_order_no' => mb_substr(trim((string)($event['source_order_no'] ?? '')), 0, 80),
            'source_line_id' => $lineId,
            'asset_id' => max(0, (int)($event['asset_id'] ?? 0)),
            'refund_id' => mb_substr(trim((string)($event['refund_id'] ?? '')), 0, 80),
            'refund_no' => mb_substr(trim((string)($event['refund_no'] ?? '')), 0, 80),
            'refund_amount' => $amount,
            'refund_goods_amount' => $goodsAmount,
            'refund_delivery_amount' => $deliveryAmount,
            'reason' => mb_substr(trim((string)($event['reason'] ?? '商城退款完成')), 0, 255),
        ]);
    }

    private function refund(array $payload): array
    {
        $sales = ErpSaleOrder::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['origin_plugin', '=', (string)$payload['source_plugin']],
            ['origin_id', '=', (string)$payload['source_order_id']],
        ])->lock(true)->order('id asc')->select();
        if ($sales->isEmpty()) throw new CommonException('ERP中未找到对应的商城销售记录');
        $saleIds = array_map('intval', array_column($sales->toArray(), 'id'));

        $item = ErpSaleItem::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['external_line_id', '=', (string)$payload['source_line_id']],
        ])->whereIn('sale_order_id', $saleIds)->lock(true)->findOrEmpty();
        // 兼容早期设备销售明细尚未保存 external_line_id 的历史数据。
        if ($item->isEmpty() && (int)$payload['asset_id'] > 0) {
            $item = ErpSaleItem::where([
                ['site_id', '=', (int)$payload['site_id']],
                ['asset_id', '=', (int)$payload['asset_id']],
            ])->whereIn('sale_order_id', $saleIds)->lock(true)->findOrEmpty();
        }
        if ($item->isEmpty()) throw new CommonException('ERP中未找到对应的商城销售明细');
        $sale = ErpSaleOrder::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['id', '=', (int)$item->sale_order_id],
        ])->lock(true)->findOrEmpty();
        if ($sale->isEmpty()) throw new CommonException('ERP商城销售主单不存在');

        $remaining = round((float)$item->sale_price - (float)$item->refunded_amount, 2);
        $cashRefundAmount = round((float)$payload['refund_amount'], 2);
        $refundAmount = round((float)$payload['refund_goods_amount'], 2);
        if ($refundAmount > $remaining + 0.01) throw new CommonException('退款商品金额超过该商品尚未冲销的销售额');

        $ratio = (float)$item->sale_price > 0 ? min(1, $refundAmount / (float)$item->sale_price) : 0;
        $refundCost = round((float)$item->cost * $ratio, 2);
        $itemRefunded = round((float)$item->refunded_amount + $refundAmount, 2);
        $itemRefundedCost = min(round((float)$item->cost, 2), round((float)$item->refunded_cost + $refundCost, 2));
        $itemReturned = $itemRefunded >= round((float)$item->sale_price, 2) - 0.01;
        $now = time();
        $item->save([
            'refunded_amount' => $itemRefunded,
            'refunded_cost' => $itemRefundedCost,
            'profit' => round(((float)$item->sale_price - $itemRefunded) - ((float)$item->cost - $itemRefundedCost), 2),
            'status' => $itemReturned ? 'returned' : 'sold',
            'update_at' => $now,
        ]);

        $saleRefunded = round((float)$sale->refunded_amount + $refundAmount, 2);
        $saleRefundedCost = min(round((float)$sale->total_cost, 2), round((float)$sale->refunded_cost + $refundCost, 2));
        $fullyReturned = $saleRefunded >= round((float)$sale->total_amount, 2) - 0.01;
        $activeAmount = max(0, round((float)$sale->total_amount - $saleRefunded, 2));
        $financeStatus = $this->closeReceivableRemainder($sale, $activeAmount, $fullyReturned, $payload);
        $sale->save([
            'refunded_amount' => $saleRefunded,
            'refunded_cost' => $saleRefundedCost,
            'received_amount' => $activeAmount,
            'receivable_amount' => 0,
            'profit' => round($activeAmount - ((float)$sale->total_cost - $saleRefundedCost), 2),
            'status' => $fullyReturned ? 'returned' : 'completed',
            'finance_status' => $fullyReturned ? ErpDict::STATUS_VOID : $financeStatus,
            'update_at' => $now,
        ]);

        $assetRestored = false;
        if ($itemReturned && (int)$item->asset_id > 0) {
            $assetRestored = $this->restoreAsset($sale, $item, $payload, $now);
        }

        $account = $this->clearingAccount((int)$payload['site_id'], $now);
        $settlementPayload = array_merge($payload, ['party_name' => (string)$sale->party_name]);
        $settlement = $this->settlement(
            $settlementPayload,
            $account,
            'refund',
            $cashRefundAmount,
            'out',
            '商城退款 ' . (string)$payload['refund_no'] . '，原订单 ' . (string)$sale->origin_no
        );
        $balance = round((float)$account->balance - $cashRefundAmount, 2);
        $account->save(['balance' => $balance, 'update_at' => $now]);
        $this->ledger((int)$payload['site_id'])->money([
            'settlement_id' => (int)$settlement->id,
            'capital_account_id' => (int)$account->id,
            'capital_account_name' => (string)$account->account_name,
            'direction' => 'out',
            'category_key' => 'sale_refund',
            'category_name' => '商城销售退款',
            'category_statement_group' => 'sales_refund',
            'category_source_plugin' => 'phone_shop',
            'category_source_key' => (int)$item->asset_id > 0 ? 'erp_asset_refund' : 'native_goods_refund',
            'amount' => $cashRefundAmount,
            'balance_after' => $balance,
            'party_name' => (string)$sale->party_name,
            'occurred_at' => (int)$payload['occurred_at'],
            'remark' => sprintf(
                '商城退款 %s；商品退款¥%.2f，运费退款¥%.2f；%s',
                (string)$payload['refund_no'],
                $refundAmount,
                (float)$payload['refund_delivery_amount'],
                (string)$payload['reason']
            ),
        ]);
        (new ErpOperationLogService())->record(
            'external_sale_refund',
            'sale',
            (int)$sale->id,
            (string)$sale->sale_no,
            (string)$payload['reason'],
            [
                'refund_no' => (string)$payload['refund_no'],
                'refund_amount' => $cashRefundAmount,
                'refund_goods_amount' => $refundAmount,
                'asset_id' => (int)$item->asset_id,
                'asset_restored' => $assetRestored,
            ]
        );

        return [
            'sale_order_id' => (int)$sale->id,
            'sale_no' => (string)$sale->sale_no,
            'sale_status' => (string)$sale->status,
            'finance_status' => (string)$sale->finance_status,
            'settlement_id' => (int)$settlement->id,
            'asset_restored' => $assetRestored,
            'refund_amount' => number_format($cashRefundAmount, 2, '.', ''),
            'refund_goods_amount' => number_format($refundAmount, 2, '.', ''),
            'refunded_cost' => number_format($refundCost, 2, '.', ''),
        ];
    }

    /**
     * 退款后不删除历史收款；只消除尚未收取的应收余额。
     * 已收部分由退款资金流水冲销，未收部分转为作废，因此财务不再出现待办。
     */
    private function closeReceivableRemainder(ErpSaleOrder $sale, float $activeAmount, bool $fullyReturned, array $payload): string
    {
        $rows = ErpReceivable::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['source_id', '=', (int)$sale->id],
        ])->lock(true)->order('id asc')->select();
        if ($rows->isEmpty()) return ErpDict::STATUS_SETTLED;

        $left = $activeAmount;
        $hasPending = false;
        $aggregateStatus = ErpDict::STATUS_SETTLED;
        foreach ($rows as $row) {
            $sourceType = (string)$row->source_type;
            if ($sourceType !== 'sale' && !str_starts_with($sourceType, 'phone_shop.')) continue;
            $settled = round((float)$row->settled_amount, 2);
            $allocated = min(max(0, $left), round((float)$row->amount, 2));
            $left = max(0, round($left - $allocated, 2));
            $newAmount = max($settled, $allocated);
            $status = $newAmount <= 0.01
                ? ErpDict::STATUS_VOID
                : ErpDict::financeStatus($newAmount, $settled);
            if ($fullyReturned && $settled <= 0.01) $status = ErpDict::STATUS_VOID;
            $remark = trim((string)$row->remark);
            $refundRemark = '商城退款后关闭剩余应收：' . (string)$payload['refund_no'];
            $row->save([
                'amount' => $newAmount,
                'status' => $status,
                'remark' => mb_substr($remark === '' ? $refundRemark : $remark . '；' . $refundRemark, 0, 255),
                'update_at' => time(),
            ]);
            if (in_array($status, [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL], true)) {
                $hasPending = true;
                $aggregateStatus = $status;
            }
        }
        return $hasPending ? $aggregateStatus : ErpDict::STATUS_SETTLED;
    }

    private function restoreAsset(ErpSaleOrder $sale, ErpSaleItem $item, array $payload, int $now): bool
    {
        $asset = ErpAsset::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['id', '=', (int)$item->asset_id],
        ])->lock(true)->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('退款设备在ERP中不存在，无法恢复库存');
        if ((string)$asset->status !== ErpDict::ASSET_SOLD
            || (int)$asset->sale_order_id !== (int)$sale->id
            || (int)$asset->sale_item_id !== (int)$item->id) {
            throw new CommonException('退款设备销售状态异常，已停止自动恢复库存');
        }
        $asset->save([
            'sale_order_id' => 0,
            'sale_item_id' => 0,
            'sale_price' => 0,
            'profit' => 0,
            'status' => ErpDict::ASSET_IN_STOCK,
            'update_at' => $now,
        ]);
        $this->ledger((int)$payload['site_id'])->asset([
            'asset_id' => (int)$asset->id,
            'action' => 'sale_return',
            'before_status' => ErpDict::ASSET_SOLD,
            'after_status' => ErpDict::ASSET_IN_STOCK,
            'before_total_cost' => (float)$asset->total_cost,
            'after_total_cost' => (float)$asset->total_cost,
            'party_id' => (int)$sale->party_id,
            'party_name' => (string)$sale->party_name,
            'source_type' => 'external_sale_refund',
            'source_id' => (int)$sale->id,
            'source_no' => (string)$sale->sale_no,
            'occurred_at' => (int)$payload['occurred_at'],
            'remark' => '商城线上退款成功，设备恢复原仓库存',
        ]);
        $queued = (new ErpIntegrationService())->enqueueDomainEvent(
            'erp.asset.returned.v1',
            'asset',
            (int)$asset->id,
            [
                'asset_id' => (int)$asset->id,
                'asset_no' => (string)$asset->asset_no,
                'imei' => (string)$asset->imei,
                'model' => (string)$asset->model,
                'spec' => (string)$asset->spec,
                'warehouse_id' => (int)$asset->warehouse_id,
                'location_id' => (int)$asset->location_id,
                'sale_target' => (string)$asset->sale_target,
                'sale_order_id' => (int)$sale->id,
                'sale_item_id' => (int)$item->id,
                'outbound_no' => (string)$sale->sale_no,
                'return_reason' => (string)$payload['reason'],
                'return_type' => 'online_payment_refund',
                'origin_plugin' => 'phone_shop',
                'refund_no' => (string)$payload['refund_no'],
                'snapshot_at' => $now,
            ],
            [],
            ['phone_shop.erp_asset_state']
        );
        $this->assetOutboxIds[] = (int)$queued['id'];
        return true;
    }

    private function flushAssetDomainEvents(): void
    {
        $ids = array_values(array_unique(array_filter($this->assetOutboxIds)));
        $this->assetOutboxIds = [];
        $integration = new ErpIntegrationService();
        foreach ($ids as $id) $integration->dispatchDomainEvent($id);
    }
}
