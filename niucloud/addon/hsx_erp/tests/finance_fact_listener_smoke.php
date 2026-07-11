<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\service\admin\ErpFinanceFactService;
use core\exception\CommonException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

class FakeFinanceFactService extends ErpFinanceFactService
{
    public array $inboxes = [];
    public array $facts = [];
    public array $categories = [];
    public array $assetChecks = [];
    public array $costEffects = [];
    public array $businessLedgers = [];
    private int $nextInboxId = 10;

    public function __construct()
    {
        $this->site_id = 100005;
        $this->categories = [
            'hsx_repair.repair_labor' => [
                'key' => 'hsx_repair.repair_labor', 'name' => '维修人工费', 'direction' => 'expense',
                'scope' => 'repair', 'statement_group' => 'operating_expense',
                'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1,
                'source_plugin' => 'hsx_repair', 'source_key' => 'labor',
            ],
            'repair_warranty_recovery' => [
                'key' => 'repair_warranty_recovery', 'name' => '维修质保赔付', 'direction' => 'income',
                'scope' => 'repair_warranty', 'statement_group' => 'other_income',
                'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1,
                'source_plugin' => 'hsx_repair', 'source_key' => 'warranty_recovery',
            ],
            'inventory_purchase' => [
                'key' => 'inventory_purchase', 'name' => '设备采购支出', 'direction' => 'expense',
                'scope' => 'purchase', 'statement_group' => 'purchase',
                'affects_asset_cost' => 1, 'creates_finance' => 1, 'party_required' => 1,
                'source_plugin' => 'hsx_erp', 'source_key' => 'inventory_purchase',
            ],
        ];
    }

    protected function findFinanceCategory(string $key): ?array
    {
        return $this->categories[$key] ?? null;
    }

    protected function resolvePartyName(int $siteId, int $partyId, string $partyName, bool $required): string
    {
        if ($siteId !== 100005 || ($required && $partyId !== 21)) {
            throw new CommonException('往来主体不存在或不属于当前站点');
        }
        return '测试维修商';
    }

    protected function assertAssetInSite(int $siteId, int $assetId, bool $required): void
    {
        $this->assetChecks[] = [$siteId, $assetId, $required];
        if ($siteId !== 100005 || ($required && $assetId !== 88)) {
            throw new CommonException('关联设备不存在或不属于当前站点');
        }
    }

    protected function findInbox(int $siteId, string $eventId, bool $lock = false): ?array
    {
        return $this->inboxes[$siteId . ':' . $eventId] ?? null;
    }

    protected function createInbox(array $payload): int
    {
        $id = ++$this->nextInboxId;
        $this->inboxes[$payload['site_id'] . ':' . $payload['event_id']] = [
            'id' => $id,
            'status' => 'pending',
            'payload_json' => json_encode(['request' => $payload], JSON_UNESCAPED_UNICODE),
        ];
        return $id;
    }

    protected function completeInbox(int $inboxId, array $payload, array $result): void
    {
        $key = $payload['site_id'] . ':' . $payload['event_id'];
        $this->inboxes[$key]['status'] = 'processed';
        $this->inboxes[$key]['payload_json'] = json_encode(['request' => $payload, 'result' => $result], JSON_UNESCAPED_UNICODE);
    }

    protected function withinTransaction(callable $callback): array
    {
        return $callback();
    }

    protected function createPendingFact(array $snapshot): array
    {
        $this->facts[] = $snapshot;
        $id = count($this->facts) + 100;
        $target = $snapshot['direction'] === 'income' ? 'receivable' : 'payable';
        return ['target_type' => $target, 'target_id' => $id, 'target_no' => ($target === 'receivable' ? 'AR' : 'AP') . $id];
    }

    protected function applyAssetCostEffect(array $snapshot, array $category): void
    {
        if ((int)($category['affects_asset_cost'] ?? 0) === 1) {
            $this->costEffects[] = [
                'asset_id' => (int)$snapshot['asset_id'],
                'amount' => (string)$snapshot['amount'],
                'event_id' => (string)$snapshot['event_id'],
            ];
        }
    }

    protected function recordBusinessLedger(array $snapshot, array $created): void
    {
        $this->businessLedgers[] = ['snapshot' => $snapshot, 'created' => $created];
    }

    protected function recordFailedInbox(array $payload, string $message): void
    {
        $key = $payload['site_id'] . ':' . $payload['event_id'];
        $existingId = (int)($this->inboxes[$key]['id'] ?? ++$this->nextInboxId);
        $this->inboxes[$key] = [
            'id' => $existingId,
            'status' => 'failed',
            'payload_json' => json_encode(['request' => $payload, 'error' => $message], JSON_UNESCAPED_UNICODE),
        ];
    }
}

$baseEvent = [
    'site_id' => 100005,
    'event_id' => 'hsx_repair:repair-line-9001',
    'source_plugin' => 'hsx_repair',
    'source_plugin_name' => '维修管理',
    'source_name' => '维修工单',
    'source_type' => 'repair_order',
    'order_no' => 'WX202607110001',
    'line_id' => 9001,
    'category_key' => 'hsx_repair.repair_labor',
    'party_id' => 21,
    'party_name' => '插件传入名称',
    'asset_id' => 88,
    'amount' => 128.50,
    'channel' => ['code' => 'repair_store', 'name' => '门店维修'],
    'occurred_at' => 1783735200,
    'remark' => '更换屏幕人工费',
];

$service = new FakeFinanceFactService();
$expense = $service->consume($baseEvent);
$assert(($expense['status'] ?? '') === 'processed', '首次财务事实请求必须返回processed');
$assert(($expense['target_type'] ?? '') === 'payable', 'expense分类必须生成应付');
$assert(count($service->facts) === 1, '首次请求只能生成一条财务事实');
$assert(($service->facts[0]['source_type'] ?? '') === 'hsx_repair.repair_order', '来源类型必须自动固化插件命名空间');
$assert(($service->facts[0]['party_name'] ?? '') === '测试维修商', '往来主体名称必须使用当前站点权威快照');
$assert(($service->facts[0]['biz_scene'] ?? '') === 'repair', '必须固化财务分类对应业务场景');
$assert(($service->facts[0]['category_key'] ?? '') === 'hsx_repair.repair_labor', '点号命名空间分类键不得被归一化破坏');
$assert(($service->facts[0]['category_name'] ?? '') === '维修人工费', '必须固化动态财务分类名称');
$assert(($service->facts[0]['channel_code'] ?? '') === 'repair_store', '必须固化渠道编码');
$assert(str_contains((string)($service->facts[0]['business_reason'] ?? ''), '更换屏幕人工费'), '必须生成人能看懂的业务原因快照');
$assert($service->assetChecks[0] === [100005, 88, true], '影响设备成本的分类必须校验当前站点设备');
$assert(($service->costEffects[0]['amount'] ?? '') === '128.50', '影响设备成本的支出必须在同一事务登记成本影响');
$assert(count($service->businessLedgers) === 1, '生成应付时必须同步写一条业务账目轨迹');

$duplicate = $service->consume($baseEvent);
$assert(($duplicate['status'] ?? '') === 'duplicate', '相同event_id重放必须返回duplicate');
$assert(($duplicate['target_id'] ?? 0) === ($expense['target_id'] ?? -1), '重复请求必须返回首次生成的财务事实ID');
$assert(count($service->facts) === 1, '重复请求不得再次生成应收应付');
$assert(count($service->costEffects) === 1, '重复请求不得再次增加设备成本');
$assert(count($service->businessLedgers) === 1, '重复请求不得再次写业务账目轨迹');

$collision = $baseEvent;
$collision['amount'] = '129.50';
$collisionRejected = false;
try {
    $service->consume($collision);
} catch (CommonException $e) {
    $collisionRejected = str_contains($e->getMessage(), 'event_id');
}
$assert($collisionRejected, '相同event_id承载不同金额时必须拒绝，不能静默当作重复请求');

$incomeEvent = $baseEvent;
$incomeEvent['event_id'] = 'hsx_repair:warranty-line-9002';
$incomeEvent['line_id'] = 'line-uuid-9002';
$incomeEvent['operator'] = ['id' => 7, 'name' => '维修员小王'];
$incomeEvent['category_key'] = 'repair_warranty_recovery';
$incomeEvent['amount'] = 80;
$incomeEvent['remark'] = '配件供应方质保赔付';
$income = $service->consume($incomeEvent);
$assert(($income['target_type'] ?? '') === 'receivable', 'income分类必须生成应收');
$assert(($income['direction'] ?? '') === 'income', '返回结果必须包含分类方向');
$assert(($service->facts[1]['line_id'] ?? '') === 'line-uuid-9002', '外部字符串明细ID必须完整保留，不能强制转int丢失');
$assert(($service->facts[1]['source_id'] ?? -1) === 0, '非数值外部明细ID不得伪造ERP数值关联ID');
$assert(str_contains((string)($service->facts[1]['business_reason'] ?? ''), '维修员小王'), '业务原因快照必须保留来源经办人');
$assert(count($service->businessLedgers) === 2, '生成应收时必须同步写业务账目轨迹');

$protectedEvent = $baseEvent;
$protectedEvent['event_id'] = 'hsx_repair:forbidden-purchase';
$protectedEvent['line_id'] = 9003;
$protectedEvent['category_key'] = 'inventory_purchase';
$protectedRejected = false;
try {
    $service->consume($protectedEvent);
} catch (CommonException $e) {
    $protectedRejected = str_contains($e->getMessage(), '领域服务');
}
$assert($protectedRejected, '外部插件不得使用采购核心分类绕过采购领域服务重复建应付');
$assert(($service->inboxes['100005:hsx_repair:forbidden-purchase']['status'] ?? '') === 'failed', '业务失败必须留下failed收件箱供排障');

$recoverEvent = $baseEvent;
$recoverEvent['event_id'] = 'hsx_repair:recover-after-config';
$recoverEvent['line_id'] = 9004;
$recoverEvent['category_key'] = 'hsx_repair.repair_misc';
$missingCategoryRejected = false;
try {
    $service->consume($recoverEvent);
} catch (CommonException $e) {
    $missingCategoryRejected = str_contains($e->getMessage(), '财务分类');
}
$assert($missingCategoryRejected, '未安装或停用的动态分类必须拒绝');
$assert(($service->inboxes['100005:hsx_repair:recover-after-config']['status'] ?? '') === 'failed', '动态分类缺失必须记录failed收件箱');
$service->categories['hsx_repair.repair_misc'] = [
    'key' => 'hsx_repair.repair_misc', 'name' => '维修其他费', 'direction' => 'expense',
    'scope' => 'repair', 'statement_group' => 'operating_expense',
    'affects_asset_cost' => 0, 'creates_finance' => 1, 'party_required' => 1,
    'source_plugin' => 'hsx_repair', 'source_key' => 'repair_misc',
];
$recovered = $service->consume($recoverEvent);
$assert(($recovered['status'] ?? '') === 'processed', '原payload在分类恢复后必须可从failed重试为processed');
$assert(($service->inboxes['100005:hsx_repair:recover-after-config']['status'] ?? '') === 'processed', '重试成功后收件箱必须推进为processed');

$wrongSite = $baseEvent;
$wrongSite['event_id'] = 'hsx_repair:wrong-site';
$wrongSite['site_id'] = 100006;
$rejected = false;
try {
    $service->consume($wrongSite);
} catch (CommonException $e) {
    $rejected = str_contains($e->getMessage(), '站点');
}
$assert($rejected, '事件站点与当前请求站点不一致时必须拒绝');

$eventConfig = require dirname(__DIR__) . '/app/event.php';
$listeners = (array)($eventConfig['listen']['ErpFinanceFactRequested'] ?? []);
$assert(in_array('addon\hsx_erp\app\listener\ErpFinanceFactRequested', $listeners, true), 'event.php必须注册财务事实监听器');

$serviceSource = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpFinanceFactService.php');
$assert(str_contains($serviceSource, 'Db::transaction'), '收件箱与应收应付必须在同一事务处理');
$assert(str_contains($serviceSource, "'settled_amount' => 0"), '插件事件不得伪造实际收付款');
$assert(str_contains($serviceSource, "'refurbish_cost' => \$afterRefurbishCost") && str_contains($serviceSource, "'total_cost' => \$afterCost"), '影响设备成本的分类必须更新设备成本');
$assert(str_contains($serviceSource, 'recordBusinessLedger') && str_contains($serviceSource, ')->account(['), '财务事实必须留下业务账目轨迹');
$assert(str_contains($serviceSource, 'recordFailedInbox') && str_contains($serviceSource, "'status' => 'failed'"), '业务失败必须留下可重试的failed收件箱');
$assert(!str_contains($serviceSource, 'ErpSettlement::create') && !str_contains($serviceSource, 'ErpMoneyLedger'), '财务事实监听器不得创建结算或资金流水');

$sql = (string)file_get_contents(dirname(__DIR__) . '/sql/install.sql');
$assert(str_contains($sql, "CREATE TABLE IF NOT EXISTS `{{prefix}}erp_inbox_event`"), '安装SQL必须保留幂等收件箱');
$receivableSql = '';
preg_match('/CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}erp_receivable` \((.*?)\) ENGINE=/s', $sql, $receivableMatch);
$receivableSql = (string)($receivableMatch[1] ?? '');
$assert(str_contains($receivableSql, "`asset_id` int NOT NULL DEFAULT 0"), '应收表必须保存设备级关联');
$assert(str_contains($receivableSql, "KEY `idx_asset` (`site_id`,`asset_id`)"), '应收表必须包含设备级索引');

echo "[PASS] ERP generic finance fact listener smoke test\n";
