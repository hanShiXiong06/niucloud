<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpInboxEvent;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\support\ErpIdempotency;
use addon\hsx_erp\app\support\ErpMoney;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 外部插件财务事实接入边界。
 *
 * 插件只描述业务事实，ERP 根据当前站点启用的动态财务分类决定生成应收或应付。
 * 本服务绝不生成结算、收付款或资金流水；实际到账/出账仍必须由财务确认。
 */
class ErpFinanceFactService extends BaseAdminService
{
    private const LEGACY_SOURCE_ID_MAX = 2147483647;

    public const EVENT_NAME = 'ErpFinanceFactRequested';
    public const CONTRACT_NAME = 'finance.fact.requested.v1';
    public const CONTRACT_VERSION = 1;

    /**
     * 契约字段：event_id/site_id、source_plugin/source_plugin_name/source_name/source_type、
     * order_no/line_id、category_key、ERP party_id/party_name、ERP asset_id、amount、
     * channel(code/name)、settlement(mode/name)、operator(id/name)、occurred_at、remark。
     * event_name/event_version 可省略，默认 v1。
     *
     * @return array{consumer:string,event_id:string,status:string,target_type?:string,target_id?:int,target_no?:string,direction?:string}
     */
    public function consume(array $event): array
    {
        $payload = $this->normalizePayload($event);
        $existing = $this->findInbox((int)$payload['site_id'], (string)$payload['event_id']);
        if ($existing !== null) $this->assertSameInboxRequest($existing, $payload);
        if ($existing !== null && $this->isProcessedInbox($existing)) {
            return $this->duplicateResult($existing, (string)$payload['event_id']);
        }

        try {
            return $this->withinTransaction(function () use ($payload): array {
                $existing = $this->findInbox((int)$payload['site_id'], (string)$payload['event_id'], true);
                if ($existing !== null) $this->assertSameInboxRequest($existing, $payload);
                if ($existing !== null && $this->isProcessedInbox($existing)) {
                    return $this->duplicateResult($existing, (string)$payload['event_id']);
                }

                // pending/failed 但没有结果的历史收件箱允许恢复处理；新请求才插入收件箱。
                $inboxId = $existing !== null ? (int)($existing['id'] ?? 0) : $this->createInbox($payload);
                if ($inboxId <= 0) throw new CommonException('财务事实收件箱记录无效');
                $category = $this->findFinanceCategory((string)$payload['category_key']);
                if ($category === null) {
                    throw new CommonException('财务分类不存在、已停用或所属插件当前不可用');
                }
                if ((int)($category['creates_finance'] ?? 1) !== 1) {
                    throw new CommonException('所选财务分类不生成应收应付');
                }
                $protectedCategories = ['inventory_purchase', 'sale_revenue', 'sale_refund', 'purchase_refund', 'after_sale_compensation'];
                if ((string)$payload['source_plugin'] !== 'hsx_erp' && in_array((string)$payload['category_key'], $protectedCategories, true)) {
                    throw new CommonException('外部插件不能绕过采购、销售或退货领域服务创建核心交易事实');
                }
                $categoryOwner = trim((string)($category['source_plugin'] ?? ''));
                if ($categoryOwner !== '' && $categoryOwner !== 'hsx_erp' && $categoryOwner !== (string)$payload['source_plugin']) {
                    throw new CommonException('插件不能使用其它插件提供的财务分类');
                }

                $partyName = $this->resolvePartyName(
                    (int)$payload['site_id'],
                    (int)$payload['party_id'],
                    (string)$payload['party_name'],
                    (int)($category['party_required'] ?? 0) === 1
                );
                $this->assertAssetInSite(
                    (int)$payload['site_id'],
                    (int)$payload['asset_id'],
                    (int)($category['affects_asset_cost'] ?? 0) === 1
                );

                $snapshot = $this->buildSnapshot($payload, $category, $partyName);
                $this->applyAssetCostEffect($snapshot, $category);
                $created = $this->createPendingFact($snapshot);
                $this->recordBusinessLedger($snapshot, $created);
                $result = [
                    'consumer' => 'hsx_erp',
                    'event_id' => (string)$payload['event_id'],
                    'status' => 'processed',
                    'direction' => (string)$snapshot['direction'],
                    'target_type' => (string)$created['target_type'],
                    'target_id' => (int)$created['target_id'],
                    'target_no' => (string)$created['target_no'],
                ];
                $this->completeInbox($inboxId, $payload, $result);
                return $result;
            });
        } catch (\Throwable $e) {
            // 并发重放时唯一索引只允许一个事务成功；失败方读取成功方结果并返回 duplicate。
            $existing = $this->findInbox((int)$payload['site_id'], (string)$payload['event_id']);
            if ($existing !== null) $this->assertSameInboxRequest($existing, $payload);
            if ($existing !== null && $this->isProcessedInbox($existing)) {
                return $this->duplicateResult($existing, (string)$payload['event_id']);
            }
            $this->recordFailedInbox($payload, $e->getMessage());
            throw $e;
        }
    }

    protected function normalizePayload(array $event): array
    {
        $contractName = trim((string)($event['event_name'] ?? self::CONTRACT_NAME));
        $contractVersion = (int)($event['event_version'] ?? self::CONTRACT_VERSION);
        if ($contractName !== self::CONTRACT_NAME || $contractVersion !== self::CONTRACT_VERSION) {
            throw new CommonException('ERP不支持该财务事实事件版本');
        }
        $siteId = (int)($event['site_id'] ?? 0);
        $currentSiteId = $this->currentSiteId();
        if ($siteId <= 0 || $currentSiteId <= 0 || $siteId !== $currentSiteId) {
            throw new CommonException('财务事实事件站点与当前请求站点不一致，已拒绝处理');
        }

        $eventId = ErpIdempotency::normalize($event['event_id'] ?? '');
        if ($eventId === '') {
            throw new CommonException('财务事实事件缺少event_id');
        }

        $sourcePlugin = $this->stableKey((string)($event['source_plugin'] ?? ''), 40, false);
        if ($sourcePlugin === '') {
            throw new CommonException('财务事实事件缺少source_plugin');
        }
        $sourcePluginName = mb_substr(trim((string)($event['source_plugin_name'] ?? '')), 0, 60);
        if ($sourcePluginName === '') {
            $sourcePluginName = $sourcePlugin;
        }
        $sourceName = mb_substr(trim((string)($event['source_name'] ?? '')), 0, 80);
        if ($sourceName === '') {
            throw new CommonException('财务事实事件缺少source_name');
        }
        $sourceType = $this->stableKey((string)($event['source_type'] ?? ''), 80, true);
        if ($sourceType === '') {
            throw new CommonException('财务事实事件缺少source_type');
        }
        if (!str_starts_with($sourceType, $sourcePlugin . '.') && !str_starts_with($sourceType, $sourcePlugin . '_')) {
            $sourceType = mb_substr($sourcePlugin . '.' . $sourceType, 0, 80);
        }

        $orderNo = mb_substr(trim((string)($event['order_no'] ?? $event['source_order_no'] ?? '')), 0, 80);
        // 外部系统的明细 ID 可能是 UUID/SKU，不能强制转 int 后丢失。ERP 旧关联列
        // 继续保存可用的数值 ID；完整原始值始终写入 origin_id 与事件快照。
        $lineId = mb_substr(trim((string)($event['line_id'] ?? $event['source_line_id'] ?? '')), 0, 80);
        $numericLineId = $this->normalizeLegacySourceId($lineId);
        $categoryKey = $this->stableKey((string)($event['category_key'] ?? ''), 80, true);
        $amount = ErpMoney::normalize($event['amount'] ?? '');
        $occurredAt = (int)($event['occurred_at'] ?? 0);
        if ($orderNo === '') throw new CommonException('财务事实事件缺少order_no');
        if ($lineId === '') throw new CommonException('财务事实事件缺少有效line_id');
        if ($categoryKey === '') throw new CommonException('财务事实事件缺少category_key');
        if (ErpMoney::compare($amount, '0') <= 0 || ErpMoney::compare($amount, '9999999999.99') > 0) {
            throw new CommonException('财务事实金额必须大于0且不能超过字段上限');
        }
        if ($occurredAt <= 0) throw new CommonException('财务事实事件缺少occurred_at');

        [$channelCode, $channelName] = $this->normalizeChannel($event);
        $operator = (array)($event['operator'] ?? []);
        $settlement = (array)($event['settlement'] ?? []);
        return [
            'event_name' => self::CONTRACT_NAME,
            'event_version' => self::CONTRACT_VERSION,
            'site_id' => $siteId,
            'event_id' => $eventId,
            'source_plugin' => $sourcePlugin,
            'source_plugin_name' => $sourcePluginName,
            'source_name' => $sourceName,
            'source_type' => $sourceType,
            'order_no' => $orderNo,
            'line_id' => $lineId,
            'source_id' => $numericLineId,
            'category_key' => $categoryKey,
            // party_id/asset_id 必须是 ERP 当前站点主键，不接受 member_id 或外部设备ID冒充。
            'party_id' => max(0, (int)($event['party_id'] ?? 0)),
            'party_name' => mb_substr(trim((string)($event['party_name'] ?? '')), 0, 100),
            'asset_id' => max(0, (int)($event['asset_id'] ?? 0)),
            'amount' => $amount,
            'channel_code' => $channelCode,
            'channel_name' => $channelName,
            'occurred_at' => $occurredAt,
            'operator_id' => max(0, (int)($operator['id'] ?? $event['operator_id'] ?? 0)),
            'operator_name' => mb_substr(trim((string)($operator['name'] ?? $event['operator_name'] ?? '')), 0, 60),
            'settlement_mode' => $this->stableKey((string)($settlement['mode'] ?? $event['settlement_mode'] ?? ''), 20, false),
            'settlement_mode_name' => mb_substr(trim((string)($settlement['name'] ?? $event['settlement_mode_name'] ?? '')), 0, 60),
            'remark' => mb_substr(trim((string)($event['remark'] ?? '')), 0, 255),
        ];
    }

    protected function buildSnapshot(array $payload, array $category, string $partyName): array
    {
        $direction = (string)($category['direction'] ?? '') === 'income' ? 'income' : 'expense';
        $scope = $this->stableKey((string)($category['scope'] ?? ''), 40, false);
        $bizScene = $scope !== '' && $scope !== 'general'
            ? $scope
            : mb_substr(str_replace('.', '_', (string)$payload['source_type']), 0, 40);
        $categoryName = mb_substr(trim((string)($category['name'] ?? $payload['category_key'])), 0, 60);
        $sourceName = trim((string)$payload['source_name']);
        $reason = $sourceName === $categoryName
            ? $categoryName . '，来源单 ' . (string)$payload['order_no']
            : $categoryName . '：' . $sourceName . '，来源单 ' . (string)$payload['order_no'];
        if ((int)$payload['asset_id'] > 0) $reason .= '，关联设备 #' . (int)$payload['asset_id'];
        if ((string)$payload['operator_name'] !== '') $reason .= '，经办人 ' . (string)$payload['operator_name'];
        if ((string)$payload['remark'] !== '') $reason .= '；' . (string)$payload['remark'];

        return array_merge($payload, [
            'direction' => $direction,
            'party_name' => $partyName,
            'biz_scene' => $bizScene,
            'category_name' => $categoryName,
            'category_statement_group' => mb_substr(trim((string)($category['statement_group'] ?? ($direction === 'income' ? 'other_income' : 'other_expense'))), 0, 40),
            'category_source_plugin' => mb_substr(trim((string)($category['source_plugin'] ?? $payload['source_plugin'])), 0, 40),
            'category_source_key' => mb_substr(trim((string)($category['source_key'] ?? $payload['category_key'])), 0, 80),
            'business_reason' => mb_substr($reason, 0, 255),
        ]);
    }

    /**
     * source_id 是兼容 ERP 旧内部关联的有符号 INT，外部大整数或字符串 ID
     * 由 origin_id 完整承载，不能按 PHP 64 位整数上限直接写入数据库。
     */
    protected function normalizeLegacySourceId(string $value): int
    {
        if ($value === '' || !ctype_digit($value)) return 0;
        $normalized = ltrim($value, '0');
        if ($normalized === '') return 0;
        $max = (string)self::LEGACY_SOURCE_ID_MAX;
        if (strlen($normalized) > strlen($max)) return 0;
        if (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0) return 0;
        return (int)$normalized;
    }

    /** @return array{target_type:string,target_id:int,target_no:string} */
    protected function createPendingFact(array $snapshot): array
    {
        $now = time();
        $common = [
            'site_id' => (int)$snapshot['site_id'],
            'party_id' => (int)$snapshot['party_id'],
            'party_name' => (string)$snapshot['party_name'],
            'source_type' => mb_substr((string)$snapshot['source_type'], 0, 40),
            'source_id' => (int)$snapshot['source_id'],
            'source_no' => mb_substr((string)$snapshot['order_no'], 0, 40),
            'origin_plugin' => (string)$snapshot['source_plugin'],
            'origin_plugin_name' => (string)$snapshot['source_plugin_name'],
            'origin_type' => (string)$snapshot['source_type'],
            'origin_name' => (string)$snapshot['source_name'],
            'origin_id' => (string)$snapshot['line_id'],
            'origin_no' => (string)$snapshot['order_no'],
            'biz_scene' => (string)$snapshot['biz_scene'],
            'category_key' => (string)$snapshot['category_key'],
            'category_name' => (string)$snapshot['category_name'],
            'category_statement_group' => (string)$snapshot['category_statement_group'],
            'category_source_plugin' => (string)$snapshot['category_source_plugin'],
            'category_source_key' => (string)$snapshot['category_source_key'],
            'channel_code' => (string)$snapshot['channel_code'],
            'channel_name' => (string)$snapshot['channel_name'],
            'business_reason' => (string)$snapshot['business_reason'],
            'settlement_mode' => (string)$snapshot['settlement_mode'],
            'settlement_mode_name' => (string)$snapshot['settlement_mode_name'],
            'business_operator_uid' => (int)$snapshot['operator_id'],
            'business_operator_name' => (string)$snapshot['operator_name'],
            'asset_id' => (int)$snapshot['asset_id'],
            'amount' => (string)$snapshot['amount'],
            // 这里只生成债权/债务事实，不能伪造任何实际收付款。
            'settled_amount' => 0,
            'status' => 'pending',
            'occurred_at' => (int)$snapshot['occurred_at'],
            'remark' => (string)$snapshot['remark'],
            'create_at' => $now,
            'update_at' => $now,
        ];

        if ((string)$snapshot['direction'] === 'income') {
            $no = ErpLedgerService::makeNo('AR');
            $row = ErpReceivable::create(array_merge($common, ['receivable_no' => $no]));
            return ['target_type' => 'receivable', 'target_id' => (int)$row->id, 'target_no' => $no];
        }
        $no = ErpLedgerService::makeNo('AP');
        $row = ErpPayable::create(array_merge($common, ['payable_no' => $no]));
        return ['target_type' => 'payable', 'target_id' => (int)$row->id, 'target_no' => $no];
    }

    /** 动态分类明确影响设备成本时，同事务更新库存成本并留下不可变设备流水。 */
    protected function applyAssetCostEffect(array $snapshot, array $category): void
    {
        if ((int)($category['affects_asset_cost'] ?? 0) !== 1) return;
        if ((string)$snapshot['direction'] !== 'expense') {
            throw new CommonException('收入分类不能直接增加设备成本');
        }
        $assetId = (int)$snapshot['asset_id'];
        $asset = ErpAsset::where([
            ['site_id', '=', (int)$snapshot['site_id']],
            ['id', '=', $assetId],
        ])->lock(true)->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('关联设备不存在或不属于当前站点');
        if (!in_array((string)$asset->status, ['in_stock', 'available_for_sale'], true)) {
            throw new CommonException('只有仍在库存中的设备才能计入维修或整备成本');
        }

        $beforeCost = ErpMoney::normalize($asset->total_cost ?? 0);
        $afterCost = ErpMoney::add($beforeCost, (string)$snapshot['amount']);
        $afterRefurbishCost = ErpMoney::add($asset->refurbish_cost ?? 0, (string)$snapshot['amount']);
        $asset->save([
            'total_cost' => $afterCost,
            'refurbish_cost' => $afterRefurbishCost,
            'refurbish_status' => 'done',
            'update_at' => time(),
        ]);

        (new ErpLedgerService())->asset([
            'asset_id' => $assetId,
            'request_id' => ErpIdempotency::child((string)$snapshot['event_id'], 'asset-cost'),
            'action' => 'refurbish',
            'before_status' => (string)$asset->status,
            'after_status' => (string)$asset->status,
            'before_total_cost' => $beforeCost,
            'after_total_cost' => $afterCost,
            'cost_delta' => (string)$snapshot['amount'],
            'party_id' => (int)$snapshot['party_id'],
            'party_name' => (string)$snapshot['party_name'],
            'source_type' => mb_substr((string)$snapshot['source_type'], 0, 40),
            'source_id' => (int)$snapshot['source_id'],
            'source_no' => mb_substr((string)$snapshot['order_no'], 0, 40),
            'remark' => (string)$snapshot['business_reason'],
            'occurred_at' => (int)$snapshot['occurred_at'],
            'extra' => [
                'origin_plugin' => (string)$snapshot['source_plugin'],
                'origin_type' => (string)$snapshot['source_type'],
                'origin_no' => (string)$snapshot['order_no'],
                'source_line_id' => (string)$snapshot['line_id'],
                'operator_id' => (int)$snapshot['operator_id'],
                'operator_name' => (string)$snapshot['operator_name'],
                'category_key' => (string)$snapshot['category_key'],
                'category_name' => (string)$snapshot['category_name'],
                'channel_code' => (string)$snapshot['channel_code'],
                'channel_name' => (string)$snapshot['channel_name'],
            ],
        ]);
    }

    /** 业务账目轨迹用于解释债权债务来源；它不是实际资金流水。 */
    protected function recordBusinessLedger(array $snapshot, array $created): void
    {
        $targetLabel = (string)$created['target_type'] === 'receivable' ? '应收' : '应付';
        (new ErpLedgerService())->account([
            'biz_type' => mb_substr((string)$snapshot['biz_scene'], 0, 40),
            'direction' => 'increase',
            'amount' => (string)$snapshot['amount'],
            'party_id' => (int)$snapshot['party_id'],
            'party_name' => (string)$snapshot['party_name'],
            'asset_id' => (int)$snapshot['asset_id'],
            'source_type' => mb_substr((string)$snapshot['source_type'], 0, 40),
            'source_id' => (int)$snapshot['source_id'],
            'source_no' => mb_substr((string)$snapshot['order_no'], 0, 40),
            'occurred_at' => (int)$snapshot['occurred_at'],
            'remark' => mb_substr(
                $targetLabel . '事实[' . (string)$snapshot['category_name'] . ']：'
                    . (string)$snapshot['business_reason'] . '；财务单号 ' . (string)$created['target_no'],
                0,
                255
            ),
        ]);
    }

    protected function currentSiteId(): int
    {
        return (int)$this->site_id;
    }

    protected function findFinanceCategory(string $key): ?array
    {
        return (new ErpConfigService())->findFinanceCategory($key);
    }

    protected function resolvePartyName(int $siteId, int $partyId, string $partyName, bool $required): string
    {
        if ($partyId <= 0) {
            if ($required) throw new CommonException('该财务分类必须关联当前站点往来主体');
            return $partyName;
        }
        $party = ErpParty::where([['site_id', '=', $siteId], ['id', '=', $partyId]])->field('party_name')->findOrEmpty();
        if ($party->isEmpty()) throw new CommonException('往来主体不存在或不属于当前站点');
        return (string)$party->party_name;
    }

    protected function assertAssetInSite(int $siteId, int $assetId, bool $required): void
    {
        if ($assetId <= 0) {
            if ($required) throw new CommonException('该财务分类会影响设备成本，必须关联当前站点设备');
            return;
        }
        $asset = ErpAsset::where([['site_id', '=', $siteId], ['id', '=', $assetId]])->field('id')->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('关联设备不存在或不属于当前站点');
    }

    protected function findInbox(int $siteId, string $eventId, bool $lock = false): ?array
    {
        $query = ErpInboxEvent::where([['site_id', '=', $siteId], ['event_id', '=', $eventId]]);
        if ($lock) $query->lock(true);
        $row = $query->findOrEmpty();
        return $row->isEmpty() ? null : $row->toArray();
    }

    protected function createInbox(array $payload): int
    {
        $now = time();
        $row = ErpInboxEvent::create([
            'site_id' => (int)$payload['site_id'],
            'event_id' => (string)$payload['event_id'],
            'source_plugin' => (string)$payload['source_plugin'],
            'event_name' => self::EVENT_NAME,
            'payload_json' => json_encode(['request' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
            'payload_json' => json_encode(['request' => $payload, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'processed',
            'update_at' => time(),
        ]);
    }

    /** 成功链路保持单事务；失败回滚后另记收件箱，便于排障并允许原 payload 重试。 */
    protected function recordFailedInbox(array $payload, string $message): void
    {
        $existing = $this->findInbox((int)$payload['site_id'], (string)$payload['event_id']);
        if ($existing !== null && $this->isProcessedInbox($existing)) return;
        $values = [
            'payload_json' => json_encode([
                'request' => $payload,
                'error' => mb_substr(trim($message), 0, 500),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'failed',
            'update_at' => time(),
        ];
        try {
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
                'event_name' => self::EVENT_NAME,
                'occurred_at' => (int)$payload['occurred_at'],
                'create_at' => $now,
            ]));
        } catch (\Throwable) {
            // 并发消费者可能已经写入 processed；失败记录不能覆盖成功结果，也不能掩盖原业务异常。
        }
    }

    protected function withinTransaction(callable $callback): array
    {
        return Db::transaction($callback);
    }

    protected function duplicateResult(array $inbox, string $eventId): array
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $result = is_array($stored) && is_array($stored['result'] ?? null) ? $stored['result'] : [];
        return array_merge([
            'consumer' => 'hsx_erp',
            'event_id' => $eventId,
            'status' => 'duplicate',
        ], array_intersect_key($result, array_flip(['direction', 'target_type', 'target_id', 'target_no'])));
    }

    /** 相同 event_id 只能代表同一个不可变业务事实，禁止碰撞后静默吞单。 */
    private function assertSameInboxRequest(array $inbox, array $payload): void
    {
        $stored = json_decode((string)($inbox['payload_json'] ?? ''), true);
        $request = is_array($stored) && is_array($stored['request'] ?? null) ? $stored['request'] : null;
        if ($request === null) return;
        // source_id 只是由 line_id 派生的旧 INT 兼容列。升级前失败记录可能保存了
        // 超范围数值，升级后会规范为 0；业务身份仍由原始 line_id 严格校验。
        unset($request['source_id']);
        ksort($request);
        $current = $payload;
        unset($current['source_id']);
        ksort($current);
        $storedHash = hash('sha256', json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $currentHash = hash('sha256', json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        if (!hash_equals($storedHash, $currentHash)) {
            throw new CommonException('event_id已被不同财务事实占用，请修正插件幂等键');
        }
    }

    private function isProcessedInbox(array $inbox): bool
    {
        return in_array((string)($inbox['status'] ?? ''), ['processed', 'done'], true);
    }

    /** @return array{0:string,1:string} */
    private function normalizeChannel(array $event): array
    {
        $channel = $event['channel'] ?? [];
        if (is_array($channel)) {
            $code = (string)($channel['code'] ?? $channel['key'] ?? '');
            $name = (string)($channel['name'] ?? $channel['label'] ?? '');
        } else {
            $code = (string)$channel;
            $name = (string)$channel;
        }
        $code = (string)($event['channel_code'] ?? $code);
        $name = (string)($event['channel_name'] ?? $name);
        return [mb_substr(trim($code), 0, 80), mb_substr(trim($name), 0, 60)];
    }

    private function stableKey(string $value, int $length, bool $allowDot): string
    {
        $pattern = $allowDot ? '/[^a-zA-Z0-9_.\-]/' : '/[^a-zA-Z0-9_\-]/';
        return mb_substr((string)preg_replace($pattern, '', trim($value)), 0, $length);
    }
}
