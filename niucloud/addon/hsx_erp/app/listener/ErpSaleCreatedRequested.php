<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpInboxEvent;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\service\admin\ErpConfigService;
use addon\hsx_erp\app\service\admin\ErpSaleService;
use addon\hsx_erp\app\support\ErpIdempotency;
use core\exception\CommonException;

/**
 * 外部销售渠道统一入站：只把标准销售请求交给 ErpSaleService。
 *
 * 插件不得自行分别改库存、销售单和应收；本监听器也不会把“来源已收款”伪造成
 * ERP 资金流水，实际到账仍由财务使用明确账户确认。
 */
class ErpSaleCreatedRequested
{
    public function handle($event): ?array
    {
        if (!is_array($event)) throw new CommonException('ERP销售事件格式不正确');
        $targets = array_values(array_unique(array_filter(array_map('strval', (array)($event['targets'] ?? [])))));
        if ($targets !== [] && !in_array('self_erp', $targets, true)) return null;
        if ((string)($event['event_name'] ?? '') !== 'erp.sale.created_requested') {
            throw new CommonException('ERP不支持该销售事件');
        }
        if ((int)($event['event_version'] ?? 0) !== 1) throw new CommonException('ERP不支持该销售事件版本');

        $siteId = (int)($event['site_id'] ?? 0);
        $currentSiteId = $this->currentSiteId();
        if ($siteId <= 0 || $siteId !== $currentSiteId) throw new CommonException('销售事件站点与当前请求站点不一致');
        $eventId = ErpIdempotency::normalize($event['event_id'] ?? '');
        if ($eventId === '') throw new CommonException('ERP销售事件缺少event_id');

        $source = (array)($event['source'] ?? []);
        $plugin = $this->stableKey((string)($source['plugin'] ?? $event['source_plugin'] ?? ''), 40, false);
        if ($plugin === '') throw new CommonException('ERP销售事件缺少来源插件');
        $sourceType = $this->stableKey((string)($source['type'] ?? $event['source_type'] ?? ''), 80, true);
        if ($sourceType === '') throw new CommonException('ERP销售事件缺少业务来源类型');
        if (!str_starts_with($sourceType, $plugin . '.') && !str_starts_with($sourceType, $plugin . '_')) {
            $sourceType = $plugin . '.' . $sourceType;
        }
        $sourceOption = $this->resolveBusinessSource($sourceType);
        if (!$sourceOption || (string)($sourceOption['direction'] ?? '') !== 'income' || (string)($sourceOption['scene'] ?? '') !== 'sale') {
            throw new CommonException('该插件未为当前站点注册可用的销售来源');
        }

        [$channelCode, $channelName] = $this->normalizeChannel($event);
        $channel = $this->resolveSaleChannel($channelCode, $channelName);
        if ($channel === null) throw new CommonException('该插件未为当前站点注册可用的销售渠道');

        $payload = $this->normalizePayload($event, $eventId, $plugin, $sourceType, $sourceOption, $channel);
        $existing = $this->findInbox($siteId, $eventId);
        if ($existing !== null) {
            $this->assertSameRequest($existing, $payload);
            if ($this->isProcessed($existing)) return $this->duplicateResult($existing, $eventId);
        }

        try {
            $inboxId = $existing !== null
                ? $this->retryInbox((int)$existing['id'], $payload)
                : $this->createInbox($payload);
            $saleId = $this->createSale($this->saleData($payload));
            $sale = $this->saleResult($saleId);
            $result = [
                'consumer' => 'hsx_erp',
                'target' => 'self_erp',
                'status' => 'processed',
                'event_id' => $eventId,
                'sale_order_id' => $saleId,
                'sale_no' => (string)($sale['sale_no'] ?? ''),
                'receivable_created' => true,
                'source_payment_status' => (string)($payload['payment']['status'] ?? ''),
                'payment_reconciliation_required' => (string)($payload['payment']['status'] ?? '') === 'paid',
            ];
            $this->completeInbox($inboxId, $payload, $result);
            return $result;
        } catch (\Throwable $e) {
            $latest = $this->findInbox($siteId, $eventId);
            if ($latest !== null) {
                $this->assertSameRequest($latest, $payload);
                if ($this->isProcessed($latest)) return $this->duplicateResult($latest, $eventId);
            }
            $this->recordFailed($payload, $e->getMessage());
            throw $e;
        }
    }

    protected function normalizePayload(array $event, string $eventId, string $plugin, string $sourceType, array $sourceOption, array $channel): array
    {
        $source = (array)($event['source'] ?? []);
        $counterparty = (array)($event['counterparty'] ?? []);
        $operator = (array)($event['operator'] ?? []);
        $payment = (array)($event['payment'] ?? []);
        $orderNo = mb_substr(trim((string)($source['order_no'] ?? $event['source_order_no'] ?? '')), 0, 80);
        if ($orderNo === '') throw new CommonException('ERP销售事件缺少来源订单号');
        $partyId = max(0, (int)($counterparty['party_id'] ?? $event['party_id'] ?? 0));
        $partyName = mb_substr(trim((string)($counterparty['name'] ?? $event['party_name'] ?? '')), 0, 100);
        if ($partyName === '') throw new CommonException('ERP销售事件缺少客户名称');
        $items = [];
        foreach ((array)($event['items'] ?? $event['devices'] ?? []) as $index => $item) {
            if (!is_array($item)) continue;
            $assetId = (int)($item['asset_id'] ?? 0);
            $price = round((float)($item['sale_price'] ?? $item['amount'] ?? 0), 2);
            if ($assetId <= 0 || $price <= 0) throw new CommonException('第' . ($index + 1) . '台销售设备缺少ERP资产ID或有效售价');
            $items[] = [
                'asset_id' => $assetId,
                'sale_price' => $price,
                'source_line_id' => mb_substr(trim((string)($item['source_line_id'] ?? $item['line_id'] ?? '')), 0, 80),
                'remark' => mb_substr(trim((string)($item['remark'] ?? '')), 0, 255),
            ];
        }
        if ($items === []) throw new CommonException('ERP销售事件没有设备');
        usort($items, static fn(array $a, array $b): int => $a['asset_id'] <=> $b['asset_id']);
        $occurredAt = (int)($event['occurred_at'] ?? 0);
        if ($occurredAt <= 0) throw new CommonException('ERP销售事件缺少occurred_at');
        return [
            'site_id' => (int)$event['site_id'],
            'event_id' => $eventId,
            'event_name' => 'erp.sale.created_requested',
            'event_version' => 1,
            'source_plugin' => $plugin,
            'source_plugin_name' => mb_substr(trim((string)($source['plugin_name'] ?? $sourceOption['plugin_name'] ?? $plugin)), 0, 60),
            'source_type' => $sourceType,
            'source_name' => mb_substr(trim((string)($source['name'] ?? $sourceOption['name'] ?? '外部销售')), 0, 80),
            'source_id' => mb_substr(trim((string)($source['id'] ?? $event['source_id'] ?? '')), 0, 80),
            'source_order_no' => $orderNo,
            'party_id' => $partyId,
            'party_name' => $partyName,
            'channel_code' => (string)$channel['key'],
            'channel_name' => (string)$channel['name'],
            'salesman_uid' => max(0, (int)($operator['id'] ?? 0)),
            'operator_name' => mb_substr(trim((string)($operator['name'] ?? '')), 0, 60),
            'occurred_at' => $occurredAt,
            'remark' => mb_substr(trim((string)($event['remark'] ?? '')), 0, 255),
            // 来源平台已收款只代表线上支付事实；没有明确ERP资金账户时不伪造到账流水。
            // 该快照会保存在inbox中，供财务对账及后续自动结算扩展使用。
            'payment' => [
                'status' => in_array((string)($payment['status'] ?? ''), ['unpaid', 'paid', 'refunded'], true)
                    ? (string)$payment['status']
                    : '',
                'mode' => $this->stableKey((string)($payment['mode'] ?? ''), 40, true),
                'pricing_identity' => in_array((string)($payment['pricing_identity'] ?? ''), ['retail', 'peer'], true)
                    ? (string)$payment['pricing_identity']
                    : 'retail',
                'gross_amount' => number_format(max(0, round((float)($payment['gross_amount'] ?? 0), 2)), 2, '.', ''),
                'fee_rate' => number_format(max(0, min((float)($payment['fee_rate'] ?? 0), 0.2)), 6, '.', ''),
                'fee_amount' => number_format(max(0, round((float)($payment['fee_amount'] ?? 0), 2)), 2, '.', ''),
                'fee_bearer' => in_array((string)($payment['fee_bearer'] ?? ''), ['merchant', 'customer'], true)
                    ? (string)$payment['fee_bearer']
                    : 'merchant',
                'merchant_net_amount' => number_format(max(0, round((float)($payment['merchant_net_amount'] ?? 0), 2)), 2, '.', ''),
                'out_trade_no' => mb_substr(trim((string)($payment['out_trade_no'] ?? '')), 0, 100),
                'capital_account_id' => max(0, (int)($payment['capital_account_id'] ?? 0)),
            ],
            'items' => $items,
        ];
    }

    protected function saleData(array $payload): array
    {
        $payment = (array)($payload['payment'] ?? []);
        $isOfflineCash = (string)($payment['status'] ?? '') === 'paid'
            && (string)($payment['mode'] ?? '') === 'offline_cash'
            && (int)($payment['capital_account_id'] ?? 0) > 0;
        return [
            'request_id' => (string)$payload['event_id'],
            'event_id' => (string)$payload['event_id'],
            'party_id' => (int)$payload['party_id'],
            'party_name' => (string)$payload['party_name'],
            'sale_channel_key' => (string)$payload['channel_code'],
            'sale_channel' => (string)$payload['channel_name'],
            'salesman_uid' => (int)$payload['salesman_uid'],
            'settle_mode' => $isOfflineCash ? 'cash' : 'credit',
            'settle_method' => $isOfflineCash ? '现结' : '挂账',
            'received_amount' => $isOfflineCash ? (float)($payment['gross_amount'] ?? 0) : 0,
            'capital_account_id' => $isOfflineCash ? (int)($payment['capital_account_id'] ?? 0) : 0,
            'sale_at' => (int)$payload['occurred_at'],
            'origin_plugin' => (string)$payload['source_plugin'],
            'origin_plugin_name' => (string)$payload['source_plugin_name'],
            'origin_type' => (string)$payload['source_type'],
            'origin_name' => (string)$payload['source_name'],
            'origin_id' => (string)$payload['source_id'],
            'origin_no' => (string)$payload['source_order_no'],
            'origin_event_id' => (string)$payload['event_id'],
            'remark' => trim('外部销售同步；原单 ' . (string)$payload['source_order_no'] . ((string)$payload['remark'] !== '' ? '；' . (string)$payload['remark'] : '')),
            'items' => array_map(static fn(array $item): array => [
                'asset_id' => (int)$item['asset_id'],
                'sale_price' => (float)$item['sale_price'],
                'remark' => trim(((string)$item['source_line_id'] !== '' ? '来源明细 ' . (string)$item['source_line_id'] . '；' : '') . (string)$item['remark']),
            ], (array)$payload['items']),
        ];
    }

    protected function currentSiteId(): int { return (int)request()->siteId(); }
    protected function resolveBusinessSource(string $key): ?array { return (new ErpConfigService())->findBusinessSource($key); }

    protected function resolveSaleChannel(string $code, string $name): ?array
    {
        foreach ((new ErpConfigService())->getSaleChannelOptions() as $row) {
            if ((int)($row['enabled'] ?? 1) !== 1) continue;
            if ((string)$row['key'] === $code || ($code === '' && (string)$row['name'] === $name)) return $row;
        }
        return null;
    }

    protected function createSale(array $data): int { return (new ErpSaleService())->create($data); }

    protected function saleResult(int $saleId): array
    {
        $row = ErpSaleOrder::where([['site_id', '=', $this->currentSiteId()], ['id', '=', $saleId]])->field('id,sale_no')->findOrEmpty();
        return $row->isEmpty() ? ['id' => $saleId, 'sale_no' => ''] : $row->toArray();
    }

    /** @return array{0:string,1:string} */
    private function normalizeChannel(array $event): array
    {
        $channel = (array)($event['channel'] ?? []);
        return [
            $this->stableKey((string)($channel['code'] ?? $channel['key'] ?? $event['channel_code'] ?? ''), 80, true),
            mb_substr(trim((string)($channel['name'] ?? $channel['label'] ?? $event['channel_name'] ?? '')), 0, 60),
        ];
    }

    private function stableKey(string $value, int $length, bool $allowDot): string
    {
        $pattern = $allowDot ? '/[^a-zA-Z0-9_.\-]/' : '/[^a-zA-Z0-9_\-]/';
        return mb_substr((string)preg_replace($pattern, '', trim($value)), 0, $length);
    }

    protected function findInbox(int $siteId, string $eventId): ?array
    {
        $row = ErpInboxEvent::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]])->findOrEmpty();
        return $row->isEmpty() ? null : $row->toArray();
    }

    protected function createInbox(array $payload): int
    {
        $now = time();
        $row = ErpInboxEvent::create([
            'site_id' => (int)$payload['site_id'], 'event_id' => (string)$payload['event_id'],
            'source_plugin' => (string)$payload['source_plugin'], 'event_name' => 'erp.sale.created_requested',
            'payload_json' => json_encode(['request' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'processing', 'occurred_at' => (int)$payload['occurred_at'], 'create_at' => $now, 'update_at' => $now,
        ]);
        return (int)$row->id;
    }

    protected function retryInbox(int $id, array $payload): int
    {
        ErpInboxEvent::where('id', '=', $id)->update([
            'payload_json' => json_encode(['request' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'processing', 'update_at' => time(),
        ]);
        return $id;
    }

    protected function completeInbox(int $id, array $payload, array $result): void
    {
        ErpInboxEvent::where('id', '=', $id)->update([
            'payload_json' => json_encode(['request' => $payload, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'processed', 'update_at' => time(),
        ]);
    }

    protected function recordFailed(array $payload, string $message): void
    {
        $existing = $this->findInbox((int)$payload['site_id'], (string)$payload['event_id']);
        if ($existing !== null && $this->isProcessed($existing)) return;
        $values = [
            'payload_json' => json_encode(['request' => $payload, 'error' => mb_substr($message, 0, 500)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'failed', 'update_at' => time(),
        ];
        try {
            if ($existing !== null) {
                ErpInboxEvent::where('id', '=', (int)$existing['id'])->update($values);
            } else {
                $now = time();
                ErpInboxEvent::create(array_merge($values, [
                    'site_id' => (int)$payload['site_id'], 'event_id' => (string)$payload['event_id'],
                    'source_plugin' => (string)$payload['source_plugin'], 'event_name' => 'erp.sale.created_requested',
                    'occurred_at' => (int)$payload['occurred_at'], 'create_at' => $now,
                ]));
            }
        } catch (\Throwable) {
            // 并发重放可能已经成功，失败记录不覆盖成功事实。
        }
    }

    protected function assertSameRequest(array $inbox, array $payload): void
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $request = is_array($stored) && is_array($stored['request'] ?? null) ? $stored['request'] : null;
        if ($request === null) return;
        if (!hash_equals($this->payloadHash($request), $this->payloadHash($payload))) {
            throw new CommonException('event_id已被不同销售事实占用，请修正插件幂等键');
        }
    }

    protected function isProcessed(array $inbox): bool { return in_array((string)($inbox['status'] ?? ''), ['processed', 'done'], true); }

    protected function duplicateResult(array $inbox, string $eventId): array
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $result = is_array($stored) && is_array($stored['result'] ?? null) ? $stored['result'] : [];
        return array_merge($result, ['consumer' => 'hsx_erp', 'target' => 'self_erp', 'status' => 'duplicate', 'event_id' => $eventId]);
    }

    private function payloadHash(array $payload): string
    {
        $normalize = static function (array $value) use (&$normalize): array {
            $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
            foreach ($value as $key => $item) if (is_array($item)) $value[$key] = $normalize($item);
            if (!$isList) ksort($value);
            return $value;
        };
        return hash('sha256', json_encode($normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }
}
