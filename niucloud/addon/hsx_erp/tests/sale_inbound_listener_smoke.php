<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\listener\ErpSaleCreatedRequested;
use core\exception\CommonException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

class FakeSaleInboundListener extends ErpSaleCreatedRequested
{
    public int $siteId = 100005;
    public array $inboxes = [];
    public array $created = [];
    public bool $allowSource = true;
    public bool $allowChannel = true;
    private int $nextInboxId = 10;

    protected function currentSiteId(): int { return $this->siteId; }
    protected function resolveBusinessSource(string $key): ?array
    {
        return $this->allowSource ? ['key' => $key, 'name' => '小程序销售', 'plugin_name' => '手机商城', 'direction' => 'income', 'scene' => 'sale', 'enabled' => 1] : null;
    }
    protected function resolveSaleChannel(string $code, string $name): ?array
    {
        return $this->allowChannel ? ['key' => $code, 'name' => $name, 'source_plugin' => 'phone_shop', 'source_key' => 'mini_program', 'enabled' => 1] : null;
    }
    protected function createSale(array $data): int { $this->created[] = $data; return 701; }
    protected function saleResult(int $saleId): array { return ['id' => $saleId, 'sale_no' => 'SO-ERP-701']; }
    protected function findInbox(int $siteId, string $eventId): ?array { return $this->inboxes[$siteId . ':' . $eventId] ?? null; }
    protected function createInbox(array $payload): int
    {
        $id = ++$this->nextInboxId;
        $this->inboxes[$payload['site_id'] . ':' . $payload['event_id']] = [
            'id' => $id, 'status' => 'processing',
            'payload_json' => json_encode(['request' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        return $id;
    }
    protected function retryInbox(int $id, array $payload): int { return $id; }
    protected function completeInbox(int $id, array $payload, array $result): void
    {
        $key = $payload['site_id'] . ':' . $payload['event_id'];
        $this->inboxes[$key] = [
            'id' => $id, 'status' => 'processed',
            'payload_json' => json_encode(['request' => $payload, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
    protected function recordFailed(array $payload, string $message): void
    {
        $key = $payload['site_id'] . ':' . $payload['event_id'];
        $this->inboxes[$key] = [
            'id' => (int)($this->inboxes[$key]['id'] ?? ++$this->nextInboxId), 'status' => 'failed',
            'payload_json' => json_encode(['request' => $payload, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}

$event = [
    'event_name' => 'erp.sale.created_requested',
    'event_version' => 1,
    'event_id' => 'phone_shop:order:8899:paid:v1',
    'site_id' => 100005,
    'targets' => ['self_erp'],
    'source' => [
        'plugin' => 'phone_shop', 'plugin_name' => '手机商城',
        'type' => 'mini_program_sale', 'name' => '小程序销售',
        'id' => '8899', 'order_no' => 'PS20260711008899',
    ],
    'channel' => ['code' => 'phone_shop.mini_program', 'name' => '小程序商城'],
    'counterparty' => ['party_id' => 21, 'name' => '小程序客户'],
    'operator' => ['id' => 7, 'name' => '商城系统'],
    'occurred_at' => 1783746000,
    'items' => [
        ['asset_id' => 88, 'source_line_id' => 'sku-line-a', 'sale_price' => 1288],
        ['asset_id' => 89, 'source_line_id' => 'sku-line-b', 'sale_price' => 999],
    ],
    'remark' => '小程序订单支付完成后请求ERP出库',
];

$listener = new FakeSaleInboundListener();
$result = $listener->handle($event);
$assert(($result['status'] ?? '') === 'processed', '首次外部销售必须返回processed');
$assert(($result['sale_order_id'] ?? 0) === 701 && ($result['sale_no'] ?? '') === 'SO-ERP-701', '必须返回统一ERP销售单结果');
$assert(count($listener->created) === 1, '首次事件只能调用一次统一销售服务');
$sale = $listener->created[0];
$assert(($sale['request_id'] ?? '') === $event['event_id'], '销售单必须以外部event_id作为幂等键');
$assert(($sale['origin_plugin'] ?? '') === 'phone_shop', '销售单必须保留来源插件');
$assert(($sale['origin_type'] ?? '') === 'phone_shop.mini_program_sale', '来源类型必须自动命名空间化');
$assert(($sale['origin_no'] ?? '') === 'PS20260711008899', '销售单必须保留小程序原单号');
$assert(($sale['sale_channel_key'] ?? '') === 'phone_shop.mini_program', '业务渠道必须与来源分字段保存');
$assert(($sale['settle_method'] ?? '') === '挂账', '外部支付信息没有ERP账户时只能生成应收，不能伪造收款');
$assert(count($sale['items'] ?? []) === 2, '所有外部设备必须交给统一销售核心一次处理');

$duplicate = $listener->handle($event);
$assert(($duplicate['status'] ?? '') === 'duplicate', '相同销售event_id重放必须返回duplicate');
$assert(count($listener->created) === 1, '重复销售事件不得再次扣库存或生成应收');

$collision = $event;
$collision['items'][0]['sale_price'] = 1388;
$collisionRejected = false;
try { $listener->handle($collision); }
catch (CommonException $e) { $collisionRejected = str_contains($e->getMessage(), 'event_id'); }
$assert($collisionRejected, '同event_id更换价格必须拒绝');

$badChannel = new FakeSaleInboundListener();
$badChannel->allowChannel = false;
$channelRejected = false;
try { $badChannel->handle(array_merge($event, ['event_id' => 'phone_shop:order:no-channel'])); }
catch (CommonException $e) { $channelRejected = str_contains($e->getMessage(), '销售渠道'); }
$assert($channelRejected, '未通过当前站点Hook注册的渠道不得入站销售');

$eventConfig = require dirname(__DIR__) . '/app/event.php';
$listeners = (array)($eventConfig['listen']['ErpSaleCreatedRequested'] ?? []);
$assert(in_array('addon\hsx_erp\app\listener\ErpSaleCreatedRequested', $listeners, true), 'event.php必须注册标准销售入站监听器');

$sql = (string)file_get_contents(dirname(__DIR__) . '/sql/install.sql');
$assert((bool)preg_match('/erp_sale_order.*?`request_id` varchar\(80\).*?uk_site_request/s', $sql), '销售单必须有站点级request_id唯一索引');
$service = (string)file_get_contents(dirname(__DIR__) . '/app/service/admin/ErpSaleService.php');
$assert(str_contains($service, 'existingSaleRequest') && str_contains($service, 'assertSameSaleRequest'), '销售核心必须校验幂等重放与载荷碰撞');

$phoneShopEvent = require dirname(__DIR__, 2) . '/phone_shop/app/event.php';
$phoneShopPayListeners = (array)($phoneShopEvent['listen']['PhoneShopOrderPay'] ?? []);
$assert(in_array('addon\phone_shop\app\listener\erp\PhoneShopOrderPaidToErp', $phoneShopPayListeners, true), '小程序插件安装后必须把真实付款订单送入ERP标准销售Hook');
$publisher = (string)file_get_contents(dirname(__DIR__, 2) . '/phone_shop/app/listener/erp/PhoneShopOrderPaidToErp.php');
$assert(str_contains($publisher, "event('ErpSaleCreatedRequested'") && str_contains($publisher, "'erp_asset_id', '>', 0"), '小程序发布器只能同步已关联ERP一物一码资产的订单明细');

echo "[PASS] ERP external sale inbound listener smoke test\n";
