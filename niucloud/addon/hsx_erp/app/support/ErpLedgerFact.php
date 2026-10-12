<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

use addon\hsx_erp\app\service\admin\ErpIntegrationService;
use think\facade\Db;

/** Durable, vendor-neutral journal facts, written in the originating transaction. */
final class ErpLedgerFact
{
    public static function record(string $kind, array $row): void
    {
        if (!in_array($kind, ['asset', 'quantity', 'account', 'money'], true)) {
            throw new \InvalidArgumentException('Unsupported ERP journal');
        }
        ErpIntegrationService::forSite((int)$row['site_id'], (int)($row['operator_uid'] ?? 0))
            ->enqueueDomainEvent('erp.ledger.recorded.v1', $kind . '_ledger', (int)$row['id'], [
                'kind' => $kind,
                'fact_key' => $kind . ':' . (int)$row['id'],
                'ledger' => $row,
                'source_context' => match ($kind) {
                    'quantity' => self::quantityContext($row),
                    'asset' => self::assetContext($row),
                    default => [],
                },
            ]);
    }

    private static function assetContext(array $row): array
    {
        $sources = [
            'inbound:purchase' => ['purchase', 'purchase_order_id', 'total_cost'],
            'sold:sale' => ['sale', 'sale_order_id', 'sale_price'],
            'purchase_return:purchase_return' => ['purchase_return', 'return_id', 'return_cost'],
            'sale_return:sale_return' => ['sale_return', 'return_id', 'return_price'],
        ];
        $source = $sources[($row['action'] ?? '') . ':' . ($row['source_type'] ?? '')] ?? null;
        if (!$source || (int)($row['source_id'] ?? 0) <= 0 || (int)($row['asset_id'] ?? 0) <= 0) return [];
        [$type, $orderKey, $amountKey] = $source;
        $site = (int)$row['site_id'];
        $items = Db::name('erp_' . $type . '_item')->where('site_id', $site)
            ->where($orderKey, (int)$row['source_id'])->where('asset_id', (int)$row['asset_id'])->limit(2)->select()->toArray();
        if (count($items) !== 1) return [];
        $item = $items[0];
        $context = [
            'source_line_type' => $type . '_item', 'source_line_id' => (int)$item['id'],
            'order_id' => (int)$row['source_id'], 'party_id' => (int)($row['party_id'] ?? 0),
            'unit' => (string)($item['unit'] ?? ''), 'business_amount' => (string)($item[$amountKey] ?? ''),
            'serial' => trim((string)($item['imei'] ?? '')) ?: trim((string)($item['sn'] ?? '')),
            'ownership_type' => (string)($item['ownership_type'] ?? 'owned'),
        ];
        if ($type === 'purchase_return' || $type === 'sale_return') {
            $prefix = $type === 'purchase_return' ? 'purchase' : 'sale';
            $context['original_line_type'] = $prefix . '_item';
            $context['original_line_id'] = (int)($item[$prefix . '_item_id'] ?? 0);
        }
        return $context;
    }

    private static function quantityContext(array $row): array
    {
        $type = (string)($row['biz_type'] ?? '');
        $prefix = $type === 'purchase_standard' ? 'purchase' : ($type === 'sale_standard' ? 'sale' : '');
        if ($prefix === '' || (int)($row['biz_id'] ?? 0) <= 0) return [];
        $site = (int)$row['site_id'];
        $item = Db::name('erp_' . $prefix . '_item')->where([['site_id', '=', $site], ['id', '=', (int)$row['biz_id']]])->find();
        if (!$item) return [];
        $orderId = (int)$item[$prefix . '_order_id'];
        $order = Db::name('erp_' . $prefix . '_order')->where([['site_id', '=', $site], ['id', '=', $orderId]])->find();
        if (!$order) return [];
        return [
            'source_line_type' => $prefix . '_item', 'source_line_id' => (int)$item['id'],
            'order_id' => $orderId, 'party_id' => (int)$order['party_id'],
            'unit' => (string)($item['unit'] ?? ''),
            'business_amount' => (string)($item[$prefix === 'purchase' ? 'total_cost' : 'sale_price'] ?? ''),
        ];
    }
}
