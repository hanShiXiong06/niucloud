<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

use addon\hsx_performance\app\model\PerformanceFact;
use addon\hsx_performance\app\support\PerformanceDecimal;
use core\exception\CommonException;
use think\facade\Db;

final class PerformanceFactService
{
    public function consume(array $event): array
    {
        try {
            $factData = $this->normalize($event);
        } catch (\Throwable $e) {
            (new PerformanceAnomalyService())->record(
                (int)($event['site_id'] ?? 0),
                trim((string)($event['event_id'] ?? '')),
                'invalid_fact',
                $e->getMessage(),
                $event
            );
            throw $e instanceof CommonException ? $e : new CommonException($e->getMessage());
        }

        $existing = PerformanceFact::where([
            ['site_id', '=', (int)$factData['site_id']],
            ['event_id', '=', (string)$factData['event_id']],
        ])->findOrEmpty();
        if (!$existing->isEmpty()) return $this->duplicateResult($existing, $factData, $event);

        $this->assertReversal($factData, $event);
        $factId = 0;
        try {
            Db::transaction(function () use ($factData, $event, &$factId): void {
                // 锁住原事实后再次校验，确保并发请求最多只有一笔冲红成功。
                $this->assertReversal($factData, $event, true);
                $fact = PerformanceFact::create($factData);
                $factId = (int)$fact->id;
                (new PerformanceMetricService())->ensure($factData);
                (new PerformanceProjectionService())->rebuildForFact($factData);
            });
        } catch (\Throwable $e) {
            $existing = PerformanceFact::where([
                ['site_id', '=', (int)$factData['site_id']],
                ['event_id', '=', (string)$factData['event_id']],
            ])->findOrEmpty();
            if (!$existing->isEmpty()) return $this->duplicateResult($existing, $factData, $event);
            throw $e;
        }
        return ['consumer' => 'hsx_performance', 'status' => 'processed', 'fact_id' => $factId];
    }

    public function normalize(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $eventId = mb_substr(trim((string)($event['event_id'] ?? '')), 0, 100);
        $employeeUid = (int)($event['employee_uid'] ?? $event['actor']['uid'] ?? 0);
        $metricKey = PerformanceMetricCatalog::normalizeKey(mb_substr(trim((string)($event['metric_key'] ?? $event['action_key'] ?? '')), 0, 80));
        if ($siteId <= 0) throw new CommonException('绩效事实缺少有效站点');
        if ($eventId === '') throw new CommonException('绩效事实缺少event_id');
        if ($employeeUid <= 0) throw new CommonException('绩效事实缺少有效操作人');
        if ($metricKey === '') throw new CommonException('绩效事实缺少metric_key');

        $reversalOf = mb_substr(trim((string)($event['reversal_of_event_id'] ?? '')), 0, 100);
        $direction = (int)($event['direction'] ?? 0);
        if ($direction === 0) {
            $direction = $reversalOf !== '' || $this->hasNegativeValue($event) ? -1 : 1;
        }
        $direction = $direction < 0 ? -1 : 1;
        $factType = $reversalOf !== '' || $direction < 0 ? 'reversal' : 'original';
        if ($factType === 'reversal' && $reversalOf === '') {
            throw new CommonException('冲红事实必须指定reversal_of_event_id');
        }

        $definition = PerformanceMetricCatalog::definition($metricKey);
        $factScope = trim((string)($event['fact_scope'] ?? $definition['scope']));
        if (!in_array($factScope, ['action', 'outcome', 'quality'], true)) {
            throw new CommonException('fact_scope只允许action、outcome或quality');
        }
        $occurredAt = (int)($event['occurred_at'] ?? 0);
        if ($occurredAt <= 0) throw new CommonException('绩效事实缺少有效发生时间');
        if ($occurredAt > time() + 300) throw new CommonException('绩效事实发生时间不能晚于当前时间');

        $duration = abs((int)($event['duration_seconds'] ?? 0));
        $dimensions = is_array($event['dimensions'] ?? null) ? $event['dimensions'] : [];
        $sourceRoute = is_array($event['source_route'] ?? null) ? $event['source_route'] : [];
        $now = time();
        $timezone = trim((string)config('app.default_timezone', 'Asia/Shanghai')) ?: 'Asia/Shanghai';
        try {
            $businessDate = (new \DateTimeImmutable('@' . $occurredAt))
                ->setTimezone(new \DateTimeZone($timezone))
                ->format('Y-m-d');
        } catch (\Throwable $e) {
            throw new CommonException('系统时区配置无效');
        }
        $normalized = [
            'site_id' => $siteId,
            'event_id' => $eventId,
            'event_name' => mb_substr(trim((string)($event['event_name'] ?? 'performance.fact.recorded.v1')), 0, 80),
            'event_version' => max(1, (int)($event['event_version'] ?? 1)),
            'source_plugin' => mb_substr(trim((string)($event['source_plugin'] ?? '')), 0, 50),
            'business_chain' => mb_substr(trim((string)($event['business_chain'] ?? '')), 0, 40),
            'action_key' => $metricKey,
            'metric_name' => mb_substr(trim((string)($event['metric_name'] ?? $definition['name'])), 0, 80),
            'fact_scope' => $factScope,
            'fact_type' => $factType,
            'direction' => $direction,
            'reversal_of_event_id' => $reversalOf,
            'employee_uid' => $employeeUid,
            'employee_name' => mb_substr(trim((string)($event['employee_name'] ?? $event['actor']['name'] ?? '')), 0, 60),
            'role_key' => mb_substr(trim((string)($event['role_key'] ?? $event['actor']['role_key'] ?? '')), 0, 50),
            'business_type' => mb_substr(trim((string)($event['business_type'] ?? $event['subject']['type'] ?? '')), 0, 50),
            'business_id' => mb_substr(trim((string)($event['business_id'] ?? $event['subject']['id'] ?? '')), 0, 80),
            'business_no' => mb_substr(trim((string)($event['business_no'] ?? $event['subject']['no'] ?? '')), 0, 80),
            'asset_id' => max(0, (int)($event['asset_id'] ?? $event['subject']['asset_id'] ?? 0)),
            'imei' => mb_substr(trim((string)($event['imei'] ?? $event['subject']['imei'] ?? '')), 0, 64),
            'quantity' => PerformanceDecimal::signed($event['quantity'] ?? 1, 2, $direction),
            'amount' => PerformanceDecimal::signed($event['amount'] ?? 0, 2, $direction),
            'profit' => PerformanceDecimal::signed($event['profit'] ?? 0, 2, $direction),
            'duration_seconds' => $direction * $duration,
            'quality_score' => PerformanceDecimal::signed($event['quality_score'] ?? 0, 4, $direction),
            'unit' => mb_substr(trim((string)($event['unit'] ?? $definition['unit'])), 0, 20),
            'dimensions_json' => $dimensions,
            'source_route_json' => $sourceRoute,
            'business_date' => $businessDate,
            'occurred_at' => $occurredAt,
            'received_at' => $now,
            'create_at' => $now,
            'update_at' => $now,
        ];
        if ($normalized['source_plugin'] === '') throw new CommonException('绩效事实缺少source_plugin');
        if ($normalized['business_chain'] === '') throw new CommonException('绩效事实缺少business_chain');
        if ($normalized['metric_name'] === '') $normalized['metric_name'] = $metricKey;
        if ($normalized['unit'] === '') $normalized['unit'] = 'item';

        $hashPayload = $normalized;
        unset($hashPayload['received_at'], $hashPayload['create_at'], $hashPayload['update_at']);
        $this->sortRecursive($hashPayload);
        $normalized['payload_hash'] = hash('sha256', (string)json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $normalized['payload_json'] = $hashPayload;
        return $normalized;
    }

    private function assertReversal(array $fact, array $rawEvent, bool $forUpdate = false): void
    {
        if ((string)$fact['fact_type'] !== 'reversal') return;
        $originalQuery = PerformanceFact::where([
            ['site_id', '=', (int)$fact['site_id']],
            ['event_id', '=', (string)$fact['reversal_of_event_id']],
        ]);
        if ($forUpdate) $originalQuery->lock(true);
        $original = $originalQuery->findOrEmpty();
        if ($original->isEmpty()) {
            $this->reject($fact, 'orphan_reversal', '冲红对应的原事实不存在', $rawEvent);
        }
        if ((string)$original->fact_type !== 'original') {
            $this->reject($fact, 'invalid_reversal_target', '只能冲红正向原事实', $rawEvent);
        }
        $existing = PerformanceFact::where([
            ['site_id', '=', (int)$fact['site_id']],
            ['reversal_of_event_id', '=', (string)$fact['reversal_of_event_id']],
            ['fact_type', '=', 'reversal'],
        ])->findOrEmpty();
        if (!$existing->isEmpty()) {
            $this->reject($fact, 'duplicate_reversal', '该事实已经完成冲红', $rawEvent);
        }
        foreach (['source_plugin', 'action_key', 'employee_uid', 'business_type', 'business_id'] as $field) {
            if ((string)$original->{$field} !== (string)$fact[$field]) {
                $this->reject($fact, 'reversal_mismatch', '冲红事实与原事实关键字段不一致：' . $field, $rawEvent);
            }
        }
        $pairs = [
            ['quantity', 2], ['amount', 2], ['profit', 2], ['quality_score', 4],
        ];
        foreach ($pairs as [$field, $scale]) {
            if (PerformanceDecimal::absolute($original->{$field}, $scale) !== PerformanceDecimal::absolute($fact[$field], $scale)) {
                $this->reject($fact, 'partial_reversal_not_supported', '第一期只允许完整冲红：' . $field, $rawEvent);
            }
        }
        if (abs((int)$original->duration_seconds) !== abs((int)$fact['duration_seconds'])) {
            $this->reject($fact, 'partial_reversal_not_supported', '第一期只允许完整冲红：duration_seconds', $rawEvent);
        }
    }

    private function duplicateResult(PerformanceFact $existing, array $factData, array $rawEvent): array
    {
        $storedHash = trim((string)$existing->payload_hash);
        if ($storedHash !== '' && !hash_equals($storedHash, (string)$factData['payload_hash'])) {
            $this->reject($factData, 'event_payload_conflict', '相同event_id对应了不同事实内容', $rawEvent);
        }
        return ['consumer' => 'hsx_performance', 'status' => 'duplicate', 'fact_id' => (int)$existing->id];
    }

    private function reject(array $fact, string $type, string $message, array $payload): void
    {
        (new PerformanceAnomalyService())->record(
            (int)($fact['site_id'] ?? 0),
            (string)($fact['event_id'] ?? ''),
            $type,
            $message,
            $payload
        );
        throw new CommonException($message);
    }

    private function hasNegativeValue(array $event): bool
    {
        foreach (['quantity', 'amount', 'profit', 'quality_score', 'duration_seconds'] as $field) {
            if (isset($event[$field]) && is_numeric($event[$field]) && (float)$event[$field] < 0) return true;
        }
        return false;
    }

    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) if (is_array($item)) $this->sortRecursive($item);
        unset($item);
        if (!$this->isList($value)) ksort($value);
    }

    private function isList(array $value): bool
    {
        $index = 0;
        foreach ($value as $key => $_) {
            if ($key !== $index++) return false;
        }
        return true;
    }
}
