<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpSaleItem;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use core\exception\CommonException;

/** 已线上支付的商城自有商品销售事实：不触碰 ERP 设备库存与采购应付。 */
class ErpExternalSaleRecordedService extends ErpExternalSaleAccountingService
{
    public const EVENT_NAME = 'ErpExternalSaleRecordedRequested';
    public const CONTRACT_NAME = 'erp.external_sale.recorded_requested.v1';

    public function consume(array $event): array
    {
        $payload = $this->normalize($event);
        return $this->consumeOnce($payload, self::EVENT_NAME, function (array $request): array {
            return $this->record($request);
        });
    }

    private function normalize(array $event): array
    {
        $payload = $this->normalizeEnvelope($event, self::CONTRACT_NAME, 1);
        $orderId = mb_substr(trim((string)($event['source_id'] ?? '')), 0, 80);
        $orderNo = mb_substr(trim((string)($event['source_order_no'] ?? '')), 0, 80);
        if ($orderId === '' || $orderNo === '') throw new CommonException('外部销售缺少来源订单');

        $items = [];
        foreach ((array)($event['items'] ?? []) as $index => $item) {
            if (!is_array($item)) continue;
            $lineId = mb_substr(trim((string)($item['source_line_id'] ?? '')), 0, 80);
            $amount = round((float)($item['sale_amount'] ?? 0), 2);
            $cost = round((float)($item['total_cost'] ?? 0), 2);
            if ($lineId === '' || $amount <= 0 || $cost < 0) {
                throw new CommonException('第' . ($index + 1) . '项商城商品缺少有效销售额、成本或来源明细');
            }
            $source = (string)($item['inventory_source'] ?? 'self_owned');
            if (!in_array($source, ['supplier', 'self_owned', 'opening'], true)) $source = 'self_owned';
            $items[] = [
                'line_id' => $lineId,
                'goods_id' => max(0, (int)($item['goods_id'] ?? 0)),
                'sku_id' => max(0, (int)($item['sku_id'] ?? 0)),
                'goods_name' => mb_substr(trim((string)($item['goods_name'] ?? '商城商品')), 0, 200),
                'sku_name' => mb_substr(trim((string)($item['sku_name'] ?? '')), 0, 120),
                'quantity' => max(1, (int)($item['quantity'] ?? 1)),
                'amount' => $amount,
                'cost' => $cost,
                'supplier_id' => max(0, (int)($item['supplier_id'] ?? 0)),
                'inventory_source' => $source,
            ];
        }
        if ($items === []) throw new CommonException('外部销售没有可记账的商城自有商品');

        $payment = (array)($event['payment'] ?? []);
        $gross = round((float)($payment['gross_amount'] ?? 0), 2);
        $net = round((float)($payment['merchant_net_amount'] ?? $gross), 2);
        if ($gross <= 0 || $net < 0 || $net > $gross + 0.01) throw new CommonException('外部销售支付金额不正确');
        $fee = round(max(0, $gross - $net), 2);
        return array_merge($payload, [
            'source_plugin_name' => mb_substr(trim((string)($event['source_plugin_name'] ?? '外部商城')), 0, 60),
            'source_type' => mb_substr(trim((string)($event['source_type'] ?? 'external.native_goods_sale')), 0, 80),
            'source_name' => mb_substr(trim((string)($event['source_name'] ?? '商城商品线上销售')), 0, 80),
            'source_order_id' => $orderId,
            'source_order_no' => $orderNo,
            'party_name' => mb_substr(trim((string)($event['party_name'] ?? '商城客户')), 0, 100),
            'channel_code' => mb_substr(trim((string)($event['channel_code'] ?? 'external_mall')), 0, 80),
            'channel_name' => mb_substr(trim((string)($event['channel_name'] ?? '外部商城')), 0, 60),
            'items' => $items,
            'payment' => [
                'status' => (string)($payment['status'] ?? 'paid') === 'unpaid' ? 'unpaid' : 'paid',
                'mode' => mb_substr(trim((string)($payment['mode'] ?? 'online')), 0, 40),
                'trade_no' => mb_substr(trim((string)($payment['out_trade_no'] ?? '')), 0, 100),
                'gross' => $gross,
                'fee' => $fee,
                'fee_rate' => max(0, (float)($payment['fee_rate'] ?? 0)),
                'fee_bearer' => in_array((string)($payment['fee_bearer'] ?? ''), ['merchant', 'customer'], true)
                    ? (string)$payment['fee_bearer'] : 'merchant',
                'net' => $net,
                'capital_account_id' => max(0, (int)($payment['capital_account_id'] ?? 0)),
                'voucher_urls' => array_slice(array_values(array_unique(array_filter(array_map(
                    static fn($url): string => mb_substr(trim((string)$url), 0, 500),
                    (array)($payment['voucher_urls'] ?? [])
                )))), 0, 6),
            ],
            'operator_id' => max(0, (int)($event['operator_id'] ?? 0)),
            'operator_name' => mb_substr(trim((string)($event['operator_name'] ?? '')), 0, 60),
            'remark' => mb_substr(trim((string)($event['remark'] ?? '')), 0, 255),
        ]);
    }

    private function record(array $payload): array
    {
        $now = time();
        $totalAmount = round(array_sum(array_column($payload['items'], 'amount')), 2);
        $totalCost = round(array_sum(array_column($payload['items'], 'cost')), 2);
        $payment = (array)$payload['payment'];
        $isCredit = (string)($payment['status'] ?? 'paid') === 'unpaid';
        $isOfflineCash = !$isCredit && (string)($payment['mode'] ?? '') === 'offline_cash';
        $operatorId = (int)($payload['operator_id'] ?? 0);
        $operatorName = trim((string)($payload['operator_name'] ?? ''))
            ?: ($isCredit || $isOfflineCash ? '商城业务员' : '商城自动入账');
        $sale = ErpSaleOrder::create([
            'site_id' => (int)$payload['site_id'],
            'request_id' => (string)$payload['event_id'],
            'sale_no' => ErpLedgerService::makeNo('MS'),
            'party_id' => 0,
            'party_name' => (string)$payload['party_name'],
            'sale_channel' => (string)$payload['channel_name'],
            'sale_channel_key' => (string)$payload['channel_code'],
            'channel_source_plugin' => (string)$payload['source_plugin'],
            'channel_source_key' => (string)$payload['channel_code'],
            'origin_plugin' => (string)$payload['source_plugin'],
            'origin_plugin_name' => (string)$payload['source_plugin_name'],
            'origin_type' => (string)$payload['source_type'],
            'origin_name' => (string)$payload['source_name'],
            'origin_id' => (string)$payload['source_order_id'],
            'origin_no' => (string)$payload['source_order_no'],
            'origin_event_id' => (string)$payload['event_id'],
            'payment_mode' => (string)$payment['mode'],
            'payment_trade_no' => (string)$payment['trade_no'],
            'payment_gross_amount' => (float)$payment['gross'],
            'payment_fee_amount' => (float)$payment['fee'],
            'payment_fee_bearer' => (string)$payment['fee_bearer'],
            'merchant_net_amount' => (float)$payment['net'],
            'refunded_amount' => 0,
            'refunded_cost' => 0,
            'settle_method' => $isCredit ? '挂账' : ($isOfflineCash ? '线下现结' : '线上现结'),
            'salesman_uid' => $operatorId,
            'salesman_name' => $operatorName,
            'total_amount' => $totalAmount,
            'total_cost' => $totalCost,
            'profit' => round($totalAmount - $totalCost, 2),
            'received_amount' => $isCredit ? 0 : $totalAmount,
            'receivable_amount' => $isCredit ? $totalAmount : 0,
            'finance_status' => $isCredit ? 'pending' : 'settled',
            'status' => 'completed',
            'operator_uid' => $operatorId,
            'operator_name' => $operatorName,
            'sale_at' => (int)$payload['occurred_at'],
            'remark' => mb_substr(trim('商城自有商品线上成交；供应来源以订单快照为准。' . (string)$payload['remark']), 0, 255),
            'create_at' => $now,
            'update_at' => $now,
        ]);
        foreach ((array)$payload['items'] as $item) {
            $model = trim((string)$item['goods_name'] . ((string)$item['sku_name'] !== '' ? ' · ' . (string)$item['sku_name'] : ''));
            ErpSaleItem::create([
                'site_id' => (int)$payload['site_id'],
                'sale_order_id' => (int)$sale->id,
                'asset_id' => 0,
                'imei' => '',
                'model' => mb_substr($model, 0, 255),
                'external_goods_id' => (int)$item['goods_id'],
                'external_sku_id' => (int)$item['sku_id'],
                'external_line_id' => (string)$item['line_id'],
                'supplier_id' => (int)$item['supplier_id'],
                'inventory_source' => (string)$item['inventory_source'],
                'quantity' => (int)$item['quantity'],
                'ownership_type' => 'owned',
                'cost' => (float)$item['cost'],
                'sale_price' => (float)$item['amount'],
                'profit' => round((float)$item['amount'] - (float)$item['cost'], 2),
                'refunded_amount' => 0,
                'refunded_cost' => 0,
                'status' => 'sold',
                'remark' => (int)$item['supplier_id'] > 0
                    ? '供应商ID快照：' . (int)$item['supplier_id']
                    : '自有/期初商品（无采购应付）',
                'create_at' => $now,
                'update_at' => $now,
            ]);
        }

        $ledger = $this->ledger((int)$payload['site_id']);
        $settlementId = 0;
        $capitalAccountId = 0;
        if ($isCredit) {
            $receivable = ErpReceivable::create([
                'site_id' => (int)$payload['site_id'],
                'receivable_no' => ErpLedgerService::makeNo('AR'),
                'party_id' => 0,
                'party_name' => (string)$payload['party_name'],
                'source_type' => (string)$payload['source_type'],
                'source_id' => (int)$sale->id,
                'source_no' => (string)$payload['source_order_no'],
                'origin_plugin' => (string)$payload['source_plugin'],
                'origin_plugin_name' => (string)$payload['source_plugin_name'],
                'origin_type' => (string)$payload['source_type'],
                'origin_name' => (string)$payload['source_name'],
                'origin_id' => (string)$payload['source_order_id'],
                'origin_no' => (string)$payload['source_order_no'],
                'biz_scene' => 'sale',
                'channel_code' => (string)$payload['channel_code'],
                'channel_name' => (string)$payload['channel_name'],
                'settlement_mode' => 'credit',
                'settlement_mode_name' => '线下挂账',
                'business_operator_uid' => $operatorId,
                'business_operator_name' => $operatorName,
                'amount' => $totalAmount,
                'settled_amount' => 0,
                'status' => 'pending',
                'occurred_at' => (int)$payload['occurred_at'],
                'remark' => '商城线下挂账销售应收',
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $ledger->account([
                'biz_type' => 'external_sale',
                'direction' => 'increase',
                'amount' => $totalAmount,
                'party_id' => 0,
                'party_name' => (string)$payload['party_name'],
                'source_type' => (string)$payload['source_type'],
                'source_id' => (int)$receivable->id,
                'source_no' => (string)$payload['source_order_no'],
                'operator_uid' => $operatorId,
                'operator_name' => $operatorName,
                'occurred_at' => (int)$payload['occurred_at'],
                'remark' => '商城订单线下挂账',
            ]);
        } else {
            if ($isOfflineCash) {
                $account = ErpCapitalAccount::where([
                    ['site_id', '=', (int)$payload['site_id']],
                    ['id', '=', (int)$payment['capital_account_id']],
                    ['status', '=', 1],
                ])->lock(true)->findOrEmpty();
                if ($account->isEmpty()) throw new CommonException('线下收款账户不存在或已停用');
            } else {
                $account = $this->clearingAccount((int)$payload['site_id'], $now);
            }
            $settlement = $this->settlement(
                $payload,
                $account,
                'receipt',
                (float)$payment['gross'],
                'in',
                '商城订单 ' . (string)$payload['source_order_no'] . ($isOfflineCash ? ' 线下收款' : ' 微信支付自动入账')
            );
            $settlementId = (int)$settlement->id;
            $capitalAccountId = (int)$account->id;
            $balance = round((float)$account->balance + (float)$payment['gross'], 2);
            $account->save(['balance' => $balance, 'update_at' => $now]);
            $ledger->money([
                'settlement_id' => $settlementId,
                'capital_account_id' => (int)$account->id,
                'capital_account_name' => (string)$account->account_name,
                'direction' => 'in',
                'category_key' => 'sale_revenue',
                'category_name' => '商城销售收入',
                'category_statement_group' => 'sales_revenue',
                'category_source_plugin' => 'phone_shop',
                'category_source_key' => 'native_goods_sale',
                'amount' => (float)$payment['gross'],
                'balance_after' => $balance,
                'party_name' => (string)$payload['party_name'],
                'operator_uid' => $operatorId,
                'operator_name' => $operatorName,
                'occurred_at' => (int)$payload['occurred_at'],
                'remark' => '商城订单 ' . (string)$payload['source_order_no'] . ($isOfflineCash ? ' 线下收款' : ' 线上收款'),
                'voucher_urls' => (array)($payment['voucher_urls'] ?? []),
            ]);
            if ((float)$payment['fee'] > 0) {
                $balance = round($balance - (float)$payment['fee'], 2);
                $account->save(['balance' => $balance, 'update_at' => $now]);
                $ledger->money([
                    'settlement_id' => $settlementId,
                    'capital_account_id' => (int)$account->id,
                    'capital_account_name' => (string)$account->account_name,
                    'direction' => 'out',
                    'category_key' => 'channel_payment_fee',
                    'category_name' => '线上支付手续费',
                    'category_statement_group' => 'selling_expense',
                    'category_source_plugin' => 'phone_shop',
                    'category_source_key' => 'wechat_payment_fee',
                    'amount' => (float)$payment['fee'],
                    'balance_after' => $balance,
                    'party_name' => '微信支付',
                    'operator_uid' => $operatorId,
                    'operator_name' => $operatorName,
                    'occurred_at' => (int)$payload['occurred_at'],
                    'remark' => '商城订单 ' . (string)$payload['source_order_no'] . ' 支付渠道手续费',
                ]);
            }
        }
        return [
            'sale_order_id' => (int)$sale->id,
            'sale_no' => (string)$sale->sale_no,
            'settlement_id' => $settlementId,
            'capital_account_id' => $capitalAccountId,
            'receivable_created' => $isCredit,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'total_cost' => number_format($totalCost, 2, '.', ''),
            'profit' => number_format($totalAmount - $totalCost, 2, '.', ''),
        ];
    }
}
