<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpInboxEvent;
use addon\hsx_erp\app\support\ErpIdempotency;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * ERP 跨插件写入契约的统一收件箱边界。
 *
 * 这里统一处理站点隔离、event_id 幂等、碰撞校验、失败留痕和并发重放。
 * 具体领域服务只负责规范自己的 payload，并实现一次业务处理。
 */
abstract class ErpExternalContractService extends BaseAdminService
{
    /**
     * 为跨插件事件显式绑定站点。
     *
     * 支付回调、队列和定时任务不一定存在后台登录请求，不能依赖 request->siteId()。
     * 站点仍必须来自事件信封，并继续参与收件箱隔离及幂等校验。
     */
    public static function forSite(int $siteId): static
    {
        if ($siteId <= 0) throw new CommonException('ERP外部请求缺少有效站点');
        $service = new static();
        $service->site_id = $siteId;
        return $service;
    }

    /**
     * @param callable(array):array $processor
     */
    protected function consumeOnce(array $payload, string $eventName, callable $processor, bool $transactional = true): array
    {
        $siteId = (int)($payload['site_id'] ?? 0);
        $eventId = (string)($payload['event_id'] ?? '');
        $existing = $this->findInbox($siteId, $eventId);
        if ($existing !== null) $this->assertSameRequest($existing, $payload);
        if ($existing !== null && $this->isProcessed($existing)) {
            return $this->duplicateResult($existing, $eventId);
        }

        $runner = function () use ($payload, $eventName, $processor): array {
            $siteId = (int)$payload['site_id'];
            $eventId = (string)$payload['event_id'];
            $existing = $this->findInbox($siteId, $eventId, true);
            if ($existing !== null) $this->assertSameRequest($existing, $payload);
            if ($existing !== null && $this->isProcessed($existing)) {
                return $this->duplicateResult($existing, $eventId);
            }

            $inboxId = $existing !== null
                ? (int)($existing['id'] ?? 0)
                : $this->createInbox($payload, $eventName);
            if ($inboxId <= 0) throw new CommonException('ERP外部请求收件箱记录无效');

            $result = $processor($payload);
            if (!is_array($result)) throw new CommonException('ERP外部请求处理结果格式不正确');
            $result = array_merge([
                'consumer' => 'hsx_erp',
                'event_id' => $eventId,
                'status' => 'processed',
            ], $result);
            $this->completeInbox($inboxId, $payload, $result);
            return $result;
        };

        try {
            return $transactional ? $this->withinTransaction($runner) : $runner();
        } catch (\Throwable $e) {
            $existing = $this->findInbox($siteId, $eventId);
            if ($existing !== null) $this->assertSameRequest($existing, $payload);
            if ($existing !== null && $this->isProcessed($existing)) {
                return $this->duplicateResult($existing, $eventId);
            }
            $this->recordFailedInbox($payload, $eventName, $e->getMessage());
            throw $e;
        }
    }

    /** @return array{site_id:int,event_id:string,source_plugin:string,occurred_at:int} */
    protected function normalizeEnvelope(array $event, string $contractName, int $contractVersion = 1): array
    {
        if (trim((string)($event['event_name'] ?? $contractName)) !== $contractName
            || (int)($event['event_version'] ?? $contractVersion) !== $contractVersion) {
            throw new CommonException('ERP不支持该外部请求契约版本');
        }
        $siteId = (int)($event['site_id'] ?? 0);
        if ($siteId <= 0 || $siteId !== $this->currentSiteId()) {
            throw new CommonException('外部请求站点与当前请求站点不一致，已拒绝处理');
        }
        $eventId = ErpIdempotency::normalize($event['event_id'] ?? '');
        if ($eventId === '') throw new CommonException('外部请求缺少event_id');
        $sourcePlugin = $this->stableKey((string)($event['source_plugin'] ?? ''), 40);
        if ($sourcePlugin === '' || $sourcePlugin === 'hsx_erp') {
            throw new CommonException('外部请求缺少有效source_plugin');
        }
        return [
            'site_id' => $siteId,
            'event_id' => $eventId,
            'source_plugin' => $sourcePlugin,
            'occurred_at' => max(1, (int)($event['occurred_at'] ?? time())),
        ];
    }

    protected function currentSiteId(): int
    {
        return (int)$this->site_id;
    }

    protected function stableKey(string $value, int $length = 80): string
    {
        return mb_substr((string)preg_replace('/[^a-zA-Z0-9_.\-]/', '', trim($value)), 0, $length);
    }

    protected function findInbox(int $siteId, string $eventId, bool $lock = false): ?array
    {
        $query = ErpInboxEvent::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]]);
        if ($lock) $query->lock(true);
        $row = $query->findOrEmpty();
        return $row->isEmpty() ? null : $row->toArray();
    }

    protected function createInbox(array $payload, string $eventName): int
    {
        $now = time();
        $row = ErpInboxEvent::create([
            'site_id' => (int)$payload['site_id'],
            'event_id' => (string)$payload['event_id'],
            'source_plugin' => (string)$payload['source_plugin'],
            'event_name' => $eventName,
            'payload_json' => $this->encode(['request' => $payload]),
            'status' => 'pending',
            'occurred_at' => (int)$payload['occurred_at'],
            'create_at' => $now,
            'update_at' => $now,
        ]);
        return (int)$row->id;
    }

    protected function completeInbox(int $inboxId, array $payload, array $result): void
    {
        ErpInboxEvent::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['id', '=', $inboxId],
        ])->update([
            'payload_json' => $this->encode(['request' => $payload, 'result' => $result]),
            'status' => 'processed',
            'update_at' => time(),
        ]);
    }

    protected function recordFailedInbox(array $payload, string $eventName, string $message): void
    {
        try {
            $existing = $this->findInbox((int)$payload['site_id'], (string)$payload['event_id']);
            if ($existing !== null && $this->isProcessed($existing)) return;
            $stored = $existing !== null
                ? json_decode((string)($existing['payload_json'] ?? ''), true)
                : [];
            $attempts = max(0, (int)($stored['_retry']['attempts'] ?? 0)) + 1;
            $values = [
                'payload_json' => $this->encode([
                    'request' => $payload,
                    'error' => mb_substr(trim($message), 0, 500),
                    '_retry' => [
                        'attempts' => $attempts,
                        'last_failed_at' => time(),
                    ],
                ]),
                'status' => 'failed',
                'update_at' => time(),
            ];
            if ($existing !== null && (int)($existing['id'] ?? 0) > 0) {
                ErpInboxEvent::where([
                    ['site_id', '=', (int)$payload['site_id']],
                    ['id', '=', (int)$existing['id']],
                ])->update($values);
                return;
            }
            $now = time();
            ErpInboxEvent::create(array_merge($values, [
                'site_id' => (int)$payload['site_id'],
                'event_id' => (string)$payload['event_id'],
                'source_plugin' => (string)$payload['source_plugin'],
                'event_name' => $eventName,
                'occurred_at' => (int)$payload['occurred_at'],
                'create_at' => $now,
            ]));
        } catch (\Throwable) {
            // 失败留痕不能覆盖并发成功结果，也不能掩盖原始领域异常。
        }
    }

    protected function withinTransaction(callable $callback): array
    {
        return Db::transaction($callback);
    }

    private function duplicateResult(array $inbox, string $eventId): array
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $result = is_array($stored) && is_array($stored['result'] ?? null) ? $stored['result'] : [];
        return array_merge($result, [
            'consumer' => 'hsx_erp',
            'event_id' => $eventId,
            'status' => 'duplicate',
        ]);
    }

    private function assertSameRequest(array $inbox, array $payload): void
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $request = is_array($stored) && is_array($stored['request'] ?? null) ? $stored['request'] : null;
        if ($request === null) return;
        // occurred_at 是投递时间而不是业务内容。同一事件重试时它可能不同，
        // 不应因此误判为幂等键碰撞；其他业务字段仍参与完整碰撞校验。
        unset($request['occurred_at'], $payload['occurred_at']);
        $storedHash = hash('sha256', $this->canonicalJson($request));
        $currentHash = hash('sha256', $this->canonicalJson($payload));
        if (!hash_equals($storedHash, $currentHash)) {
            throw new CommonException('event_id已被不同外部请求占用，请修正插件幂等键');
        }
    }

    private function isProcessed(array $inbox): bool
    {
        return in_array((string)($inbox['status'] ?? ''), ['processed', 'done'], true);
    }

    private function canonicalJson(array $value): string
    {
        foreach ($value as &$item) {
            if (is_array($item)) $item = $this->canonicalize($item);
        }
        unset($item);
        ksort($value);
        return $this->encode($value);
    }

    private function canonicalize(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) $item = $this->canonicalize($item);
        }
        unset($item);
        // 数字索引列表执行 ksort 后顺序不变，同时兼容项目当前 PHP 8.0 运行环境。
        ksort($value);
        return $value;
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
