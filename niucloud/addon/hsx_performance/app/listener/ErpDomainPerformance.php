<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\listener;

use addon\hsx_performance\app\model\PerformanceFact;
use addon\hsx_performance\app\service\core\PerformanceFactService;
use addon\hsx_performance\app\support\PerformanceDecimal;

final class ErpDomainPerformance
{
    public function handle(array $event): array
    {
        $eventName = (string)($event['event_name'] ?? '');
        if (!in_array($eventName, ['erp.asset.sold.v1', 'erp.asset.returned.v1', 'erp.settlement.completed.v1'], true)) {
            return ['consumer' => 'hsx_performance', 'status' => 'skipped'];
        }
        if ($eventName === 'erp.asset.returned.v1') return $this->reverseSale($event);
        if ($eventName === 'erp.settlement.completed.v1') return $this->settlement($event);
        return $this->sale($event);
    }

    private function sale(array $event): array
    {
        $payload = (array)($event['payload'] ?? []);
        $operator = (array)($event['operator'] ?? []);
        if ((int)($operator['id'] ?? 0) <= 0) return ['consumer' => 'hsx_performance', 'status' => 'skipped'];
        $price = (string)($payload['sale_price'] ?? 0);
        $cost = (string)($payload['cost'] ?? 0);
        $profit = PerformanceDecimal::subtract($price, $cost, 2);
        return (new PerformanceFactService())->consume([
            'event_name' => 'performance.fact.recorded.v1',
            'event_version' => 1,
            'site_id' => (int)$event['site_id'],
            'event_id' => 'hsx_erp:' . (string)$event['event_id'] . ':sale_output',
            'source_plugin' => 'hsx_erp',
            'business_chain' => 'sale',
            'metric_key' => 'erp.sale.outbound',
            'metric_name' => '销售出库',
            'fact_scope' => 'outcome',
            'fact_type' => 'original',
            'direction' => 1,
            'employee_uid' => (int)$operator['id'],
            'employee_name' => (string)($operator['name'] ?? ''),
            'role_key' => 'sales',
            'business_type' => 'erp_asset',
            'business_id' => (string)($payload['asset_id'] ?? $event['aggregate_id']),
            'business_no' => (string)($payload['outbound_no'] ?? ''),
            'asset_id' => (int)($payload['asset_id'] ?? $event['aggregate_id']),
            'imei' => (string)($payload['imei'] ?? ''),
            'quantity' => '1.00',
            'amount' => $price,
            'profit' => $profit,
            'unit' => 'device',
            'occurred_at' => (int)$event['occurred_at'],
            'dimensions' => [
                'model' => (string)($payload['model'] ?? ''),
                'sale_channel_key' => (string)($payload['sale_channel_key'] ?? ''),
                'ownership_type' => (string)($payload['ownership_type'] ?? ''),
            ],
            'source_route' => [
                'app' => 'adminapp',
                'path' => 'addon/hsx_erp/pages/sale/detail',
                'query' => ['id' => (int)($payload['sale_order_id'] ?? 0)],
            ],
        ]);
    }

    private function reverseSale(array $event): array
    {
        $payload = (array)($event['payload'] ?? []);
        $assetId = (int)($payload['asset_id'] ?? $event['aggregate_id'] ?? 0);
        $original = PerformanceFact::where([
            ['site_id', '=', (int)($event['site_id'] ?? 0)],
            ['source_plugin', '=', 'hsx_erp'],
            ['action_key', '=', 'erp.sale.outbound'],
            ['business_type', '=', 'erp_asset'],
            ['business_id', '=', (string)$assetId],
            ['fact_type', '=', 'original'],
        ])->order('occurred_at desc,id desc')->findOrEmpty();
        if ($original->isEmpty()) {
            return ['consumer' => 'hsx_performance', 'status' => 'skipped', 'reason' => 'sale_fact_not_found'];
        }
        $alreadyReversed = PerformanceFact::where([
            ['site_id', '=', (int)$event['site_id']],
            ['reversal_of_event_id', '=', (string)$original->event_id],
            ['fact_type', '=', 'reversal'],
        ])->count();
        if ($alreadyReversed > 0) return ['consumer' => 'hsx_performance', 'status' => 'duplicate', 'fact_id' => (int)$original->id];
        return (new PerformanceFactService())->consume([
            'event_name' => 'performance.fact.recorded.v1',
            'event_version' => 1,
            'site_id' => (int)$event['site_id'],
            'event_id' => 'hsx_erp:' . (string)$event['event_id'] . ':sale_output_reversal',
            'source_plugin' => 'hsx_erp',
            'business_chain' => 'sale',
            'metric_key' => 'erp.sale.outbound',
            'metric_name' => '销售出库',
            'fact_scope' => 'outcome',
            'fact_type' => 'reversal',
            'direction' => -1,
            'reversal_of_event_id' => (string)$original->event_id,
            'employee_uid' => (int)$original->employee_uid,
            'employee_name' => (string)$original->employee_name,
            'role_key' => (string)$original->role_key,
            'business_type' => (string)$original->business_type,
            'business_id' => (string)$original->business_id,
            'business_no' => (string)$original->business_no,
            'asset_id' => (int)$original->asset_id,
            'imei' => (string)$original->imei,
            'quantity' => ltrim((string)$original->quantity, '-'),
            'amount' => ltrim((string)$original->amount, '-'),
            'profit' => ltrim((string)$original->profit, '-'),
            'duration_seconds' => abs((int)$original->duration_seconds),
            'quality_score' => ltrim((string)$original->quality_score, '-'),
            'unit' => (string)$original->unit,
            'occurred_at' => (int)$event['occurred_at'],
            'dimensions' => ['return_reason' => (string)($payload['return_reason'] ?? '')],
            'source_route' => (array)($original->source_route_json ?? []),
        ]);
    }

    private function settlement(array $event): array
    {
        $payload = (array)($event['payload'] ?? []);
        $operator = (array)($event['operator'] ?? []);
        if ((int)($operator['id'] ?? 0) <= 0) return ['consumer' => 'hsx_performance', 'status' => 'skipped'];
        $type = (string)($payload['settlement_type'] ?? '');
        $metric = [
            'receipt' => ['key' => 'erp.finance.receipt.confirmed', 'name' => '确认收款'],
            'payment' => ['key' => 'erp.finance.payment.confirmed', 'name' => '确认付款'],
            'offset' => ['key' => 'erp.finance.offset.confirmed', 'name' => '确认折账'],
        ][$type] ?? null;
        if (!$metric) return ['consumer' => 'hsx_performance', 'status' => 'skipped'];
        return (new PerformanceFactService())->consume([
            'event_name' => 'performance.fact.recorded.v1',
            'event_version' => 1,
            'site_id' => (int)$event['site_id'],
            'event_id' => 'hsx_erp:' . (string)$event['event_id'] . ':settlement_output',
            'source_plugin' => 'hsx_erp',
            'business_chain' => 'finance',
            'metric_key' => $metric['key'],
            'metric_name' => $metric['name'],
            'fact_scope' => 'action',
            'fact_type' => 'original',
            'direction' => 1,
            'employee_uid' => (int)$operator['id'],
            'employee_name' => (string)($operator['name'] ?? ''),
            'role_key' => 'finance',
            'business_type' => 'erp_settlement',
            'business_id' => (string)($payload['settlement_id'] ?? $event['aggregate_id']),
            'business_no' => (string)($payload['settlement_no'] ?? ''),
            'quantity' => '1.00',
            'amount' => (string)($payload['amount'] ?? 0),
            'profit' => '0.00',
            'unit' => 'settlement',
            'occurred_at' => (int)$event['occurred_at'],
            'dimensions' => [
                'settlement_type' => $type,
                'party_name' => (string)($payload['party_name'] ?? ''),
                'target_count' => count((array)($payload['targets'] ?? [])),
            ],
            'source_route' => [
                'app' => 'adminapp',
                'path' => $type === 'payment' ? 'addon/hsx_erp/pages/payable/list' : 'addon/hsx_erp/pages/receivable/list',
                'query' => ['status' => 'settled'],
            ],
        ]);
    }
}
