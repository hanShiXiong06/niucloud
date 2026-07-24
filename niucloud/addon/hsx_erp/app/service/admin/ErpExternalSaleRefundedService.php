<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use core\exception\CommonException;

/** 商城自有商品退款冲销：保留原销售事实，以累计退款和资金支出表达逆向链路。 */
class ErpExternalSaleRefundedService extends ErpExternalSaleAccountingService
{
    public const EVENT_NAME = 'ErpExternalSaleRefundedRequested';
    public const CONTRACT_NAME = 'erp.external_sale.refunded_requested.v1';

    public function consume(array $event): array
    {
        $payload = $this->normalize($event);
        return $this->consumeOnce($payload, self::EVENT_NAME, function (array $request): array {
            return $this->refund($request);
        });
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
        $sale = ErpSaleOrder::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['origin_plugin', '=', (string)$payload['source_plugin']],
            ['origin_id', '=', (string)$payload['source_order_id']],
        ])->lock(true)->findOrEmpty();
        if ($sale->isEmpty()) throw new CommonException('ERP中未找到对应的商城销售记录');
        $item = ErpSaleItem::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['sale_order_id', '=', (int)$sale->id],
            ['external_line_id', '=', (string)$payload['source_line_id']],
        ])->lock(true)->findOrEmpty();
        if ($item->isEmpty()) throw new CommonException('ERP中未找到对应的商城销售明细');

        $remaining = round((float)$item->sale_price - (float)$item->refunded_amount, 2);
        $cashRefundAmount = round((float)$payload['refund_amount'], 2);
        $refundAmount = round((float)$payload['refund_goods_amount'], 2);
        if ($refundAmount > $remaining + 0.01) throw new CommonException('退款商品金额超过该商品尚未冲销的销售额');
        $ratio = (float)$item->sale_price > 0 ? min(1, $refundAmount / (float)$item->sale_price) : 0;
        $refundCost = round((float)$item->cost * $ratio, 2);
        $itemRefunded = round((float)$item->refunded_amount + $refundAmount, 2);
        $itemRefundedCost = min(round((float)$item->cost, 2), round((float)$item->refunded_cost + $refundCost, 2));
        $itemReturned = $itemRefunded >= round((float)$item->sale_price, 2) - 0.01;
        $item->save([
            'refunded_amount' => $itemRefunded,
            'refunded_cost' => $itemRefundedCost,
            'profit' => round(((float)$item->sale_price - $itemRefunded) - ((float)$item->cost - $itemRefundedCost), 2),
            'status' => $itemReturned ? 'returned' : 'sold',
            'update_at' => time(),
        ]);

        $saleRefunded = round((float)$sale->refunded_amount + $refundAmount, 2);
        $saleRefundedCost = min(round((float)$sale->total_cost, 2), round((float)$sale->refunded_cost + $refundCost, 2));
        $fullyReturned = $saleRefunded >= round((float)$sale->total_amount, 2) - 0.01;
        $sale->save([
            'refunded_amount' => $saleRefunded,
            'refunded_cost' => $saleRefundedCost,
            'received_amount' => max(0, round((float)$sale->total_amount - $saleRefunded, 2)),
            'profit' => round(((float)$sale->total_amount - $saleRefunded) - ((float)$sale->total_cost - $saleRefundedCost), 2),
            'status' => $fullyReturned ? 'returned' : 'completed',
            'finance_status' => 'settled',
            'update_at' => time(),
        ]);

        $now = time();
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
        $ledger = $this->ledger((int)$payload['site_id']);
        $ledger->money([
            'settlement_id' => (int)$settlement->id,
            'capital_account_id' => (int)$account->id,
            'capital_account_name' => (string)$account->account_name,
            'direction' => 'out',
            'category_key' => 'sale_refund',
            'category_name' => '商城销售退款',
            'category_statement_group' => 'sales_refund',
            'category_source_plugin' => 'phone_shop',
            'category_source_key' => 'native_goods_refund',
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
        return [
            'sale_order_id' => (int)$sale->id,
            'sale_no' => (string)$sale->sale_no,
            'sale_status' => (string)$sale->status,
            'settlement_id' => (int)$settlement->id,
            'refund_amount' => number_format($cashRefundAmount, 2, '.', ''),
            'refund_goods_amount' => number_format($refundAmount, 2, '.', ''),
            'refunded_cost' => number_format($refundCost, 2, '.', ''),
        ];
    }
}
