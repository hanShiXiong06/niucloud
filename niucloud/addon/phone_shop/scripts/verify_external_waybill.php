<?php
declare(strict_types=1);
// Offline contract tests only: no framework bootstrap, database, or courier request.
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace core\base { class BaseCoreService {} }
namespace addon\phone_shop\app\dict\order { class OrderDict { const WAIT_DELIVERY = 2; const WAIT_TAKE = 3; } }
namespace addon\phone_shop\app\dict\order { class OrderDeliveryDict { const EXPRESS = 'express'; const DELIVERY_FINISH = 2; } }
namespace WaybillTest {
    class Row { public function __construct(private array $data) {} public function toArray(): array { return $this->data; } }
    class Model {
        public static array $rows = [];
        private array $conditions = [];
        public function where(array $conditions): self { $this->conditions = $conditions; return $this; }
        public function with(array $relations): self { return $this; }
        public function findOrEmpty(): Row {
            foreach (static::$rows as $row) {
                foreach ($this->conditions as [$key, $operator, $value]) if (($row[$key] ?? null) != $value) continue 2;
                return new Row($row);
            }
            return new Row([]);
        }
        public function whereIn($field, $values): self { return $this; }
        public function column($field, $key = null): array { return []; }
    }
    class Provider {
        public static array $calls = [];
        public static array $task = [];
        public static string $carrier = 'shunfeng';
        public static string $waybill = 'SF123';
        public static array $mapping = [];
        public static $onLock = null;
        public static array $blockedManualNumbers = [];
        public static function assertDeliveryAllowed(int $site, array $payload): void {
            if (in_array($payload['express_number'] ?? '', self::$blockedManualNumbers[$site] ?? [], true)) throw new \core\exception\CommonException('已知测试单号禁止手工发货');
        }
        public static function withBusinessLock(int $site, string $type, int $orderId, callable $operation) {
            self::$calls[] = [$site, 'lock', [$type, $orderId]];
            if (self::$onLock) { $hook = self::$onLock; self::$onLock = null; $hook(); }
            return $operation();
        }
        public static function execute(int $site, string $operation, array $payload): array {
            self::$calls[] = [$site, $operation, $payload];
            return $operation === 'query' ? self::$task : ['success' => true, 'task_id' => 7, 'state' => 'ready', 'waybill_no' => self::$waybill, 'carrier_code' => self::$carrier];
        }
    }
    class OtherProvider {
        public static bool $allowed = false;
        public static array $calls = [];
        public static function execute(int $site, string $op, array $payload): array {
            if (!self::$allowed) throw new \RuntimeException('Wrong provider called');
            self::$calls[] = [$site, $op, $payload];
            return Provider::$task;
        }
    }
}
namespace addon\phone_shop\app\model\order {
    class Order extends \WaybillTest\Model { public static array $rows = []; }
    class OrderDelivery extends \WaybillTest\Model { public function count(): int { return 0; } }
}
namespace addon\phone_shop\app\model\shop_address { class ShopAddress extends \WaybillTest\Model { public static array $rows = []; } }
namespace addon\phone_shop\app\model\delivery {
    class Company extends \WaybillTest\Model {
        public static array $ids = [3];
        public static array $lookups = [];
        public function where(array $conditions): self { self::$lookups[] = $conditions; parent::where($conditions); return $this; }
        public function column($field, $key = null): array { return self::$ids; }
    }
}
namespace app\model\sys { class SysArea extends \WaybillTest\Model {} }
namespace addon\phone_shop\app\service\core\delivery {
    class CoreElectronicSheetService { public function getElectronicSheetConfig(int $site): array { return ['interface_type' => 'test']; } }
}
namespace {
    function event($name, $data): array { return [['providers' => [
        ['key' => 'test', 'label' => 'Test', 'handler' => \WaybillTest\Provider::class, 'carrier_code' => \WaybillTest\Provider::$carrier, 'carrier_mapping' => \WaybillTest\Provider::$mapping],
        ['key' => 'other', 'label' => 'Other', 'handler' => \WaybillTest\OtherProvider::class],
    ]]]; }
    require __DIR__ . '/../app/service/core/delivery/electronic_sheet/ElectronicSheetProviderRegistry.php';
    require __DIR__ . '/../app/service/core/delivery/CoreExternalElectronicSheetService.php';
    require __DIR__ . '/../app/service/core/order/CoreOrderDeliveryService.php';
    require __DIR__ . '/../../hsx_express/app/integration/PhoneShopTaskGuard.php';
    use addon\phone_shop\app\service\core\delivery\CoreExternalElectronicSheetService as Service;
    use addon\phone_shop\app\service\core\delivery\electronic_sheet\ElectronicSheetProviderRegistry as Registry;
    use addon\phone_shop\app\model\order\Order;
    use addon\phone_shop\app\model\shop_address\ShopAddress;
    use WaybillTest\Provider;
    $passed = 0;
    function check($condition, string $message): void { global $passed; if (!$condition) throw new \RuntimeException($message); $passed++; }
    function rejects(callable $fn, string $contains): void { try { $fn(); } catch (\core\exception\CommonException $e) { check(str_contains($e->getMessage(), $contains), $e->getMessage()); return; } throw new \RuntimeException('Expected refusal: ' . $contains); }
    $order = ['site_id' => 100005, 'order_id' => 12, 'order_no' => 'ORDER12', 'status' => 2, 'delivery_type' => 'express', 'taker_name' => 'test', 'taker_mobile' => '13000000000', 'taker_province' => 1, 'taker_city' => 2, 'taker_district' => 3, 'taker_address' => 'test address', 'order_goods' => [
        ['order_goods_id' => 8, 'status' => 1, 'delivery_id' => 0, 'goods_name' => 'phone'],
        ['order_goods_id' => 9, 'status' => 1, 'delivery_id' => 0, 'goods_name' => 'phone'],
    ]];
    Order::$rows = [$order];
    ShopAddress::$rows = [['site_id' => 100005, 'is_delivery_address' => 1, 'is_default_delivery' => 1, 'contact_name' => 'test', 'mobile' => '13000000000', 'province_id' => 1, 'city_id' => 2, 'district_id' => 3, 'address' => 'sender']];
    $service = new Service();
    $params = ['operation' => 'create', 'order_id' => 12, 'order_goods_ids' => [9, 8], 'provider_key' => 'test', 'weight' => 1];
    check(Service::packageKey(12, [8, 9]) === Service::packageKey(12, [9, 8, 8]), 'Package identity must not depend on selection order');
    rejects(fn() => Service::packageKey(12, []), '请选择');
    check(count((new Registry())->options(100005)) === 3, 'Original provider must remain available');
    check(!isset((new Registry())->options(100005)[1]['handler']), 'Never expose handler classes');
    rejects(fn() => (new Registry())->execute(100005, 'missing', 'create', []), '不会自动');
    check((new Registry())->withBusinessLock(100005, 'test', 12, fn() => 'delivered') === 'delivered', 'Delivery holds selected provider mutex');
    check(Provider::$calls[0] === [100005, 'lock', ['phone_shop', 12]], 'Delivery and provider cancel share site plus order identity');
    rejects(fn() => (new Registry())->withBusinessLock(100005, 'other', 12, fn() => true), '安全交件');
    Provider::$calls = [];
    check((new Registry())->withOrderLocks(100005, 12, fn() => 'manual_shipped') === 'manual_shipped', 'Manual delivery without provider key participates in optional locks');
    check(Provider::$calls === [[100005, 'lock', ['phone_shop', 12]]], 'Manual delivery locks same site and business order');
    rejects(fn() => (new Registry())->withOrderLocks(100005, 12, fn() => true, 'removed'), '原物流服务不可用');
    Provider::$calls = [];
    $task = $service->execute(100005, $params);
    check($task['waybill_no'] === 'SF123', 'Create returns original provider result');
    check(array_column(Provider::$calls, 1) === ['lock', 'query', 'create'], 'Exactly one selected provider, locked reread and query before create');
    check(Provider::$calls[2][2]['order_goods_ids'] === [9, 8], 'Task preserves goods ownership references');
    check(Provider::$calls[2][0] === 100005, 'Site isolation passes through handler');
    check(Provider::$calls[2][2]['freight_payment'] === 'receiver', 'Omitted freight payment defaults to receiver');
    Provider::$calls = []; Provider::$task = [];
    $service->execute(100005, $params + ['freight_payment' => 'sender']);
    check(Provider::$calls[2][2]['freight_payment'] === 'sender', 'Per-package payment reaches provider without client account credentials');
    Provider::$task = [];
    $empty = $service->execute(100005, array_replace($params, ['operation' => 'query']));
    check($empty['express_company_id'] === 3 && empty($empty['task_id']), 'Mapped courier preselected without creating a task');
    rejects(fn() => $service->execute(100024, $params), '不属于当前站点');
    rejects(fn() => $service->execute(100005, array_replace($params, ['order_goods_ids' => [99]])), '不属于当前订单');
    rejects(fn() => $service->execute(100005, array_replace($params, ['provider_key' => 'other'])), '设置已变化');
    Provider::$calls = []; Provider::$task = ['task_id' => 1, 'state' => 'unknown'];
    $service->execute(100005, $params);
    check(array_column(Provider::$calls, 1) === ['lock', 'query'], 'Unknown results must never auto-reorder');
    Provider::$calls = []; Provider::$task = ['task_id' => 1, 'state' => 'cancelled'];
    $service->execute(100005, $params);
    check(array_column(Provider::$calls, 1) === ['lock', 'query', 'create'], 'Confirmed cancelled task may create a new attempt');
    Provider::$task = [];
    \addon\phone_shop\app\model\delivery\Company::$ids = [];
    rejects(fn() => $service->execute(100005, $params), '未申请运单');
    \addon\phone_shop\app\model\delivery\Company::$ids = [3, 4];
    rejects(fn() => $service->execute(100005, $params), '未申请运单');
    \addon\phone_shop\app\model\delivery\Company::$ids = [3];
    rejects(fn() => $service->execute(100005, array_replace($params, ['weight' => 101])), '100');
    Provider::$onLock = static function () { Order::$rows[0]['order_goods'][0]['delivery_id'] = 22; };
    Provider::$calls = [];
    rejects(fn() => $service->execute(100005, $params), '已发货');
    check(!in_array('create', array_column(Provider::$calls, 1), true), 'Goods shipped while waiting for lock cannot create a waybill');
    Order::$rows = [$order];
    Provider::$onLock = static function () { Order::$rows[0]['status'] = -1; };
    rejects(fn() => $service->execute(100005, $params), '待发货');
    Order::$rows = [$order];
    Order::$rows[0]['order_goods'][0]['extend'] = json_encode(['erp_return' => ['time' => 123]]);
    Provider::$calls = [];
    rejects(fn() => $service->execute(100005, $params), '退回 ERP');
    check(!in_array('create', array_column(Provider::$calls, 1), true), 'ERP returned goods cannot allocate a new waybill');
    Order::$rows = [$order];
    Order::$rows[0]['order_goods'][0]['delivery_id'] = 2;
    rejects(fn() => $service->execute(100005, $params), '已发货');
    Provider::$task = ['task_id' => 1, 'state' => 'ready', 'waybill_no' => 'SF123', 'carrier_code' => 'shunfeng'];
    Provider::$calls = [];
    $service->execute(100005, array_replace($params, ['operation' => 'reprint', 'confirm' => 1]));
    check(Provider::$calls[1][2]['confirm'] === 1, 'Only explicit user confirmation is forwarded for reprint');
    Provider::$calls = [];
    $service->execute(100005, array_replace($params, ['operation' => 'reprint']));
    check(Provider::$calls[1][2]['confirm'] === 0, 'Server must not silently confirm a reprint');
    rejects(fn() => $service->execute(100005, array_replace($params, ['operation' => 'cancel'])), '已经确认发货');
    $guard = new \addon\hsx_express\app\integration\PhoneShopTaskGuard();
    $guardData = ['site_id' => 100005, 'operation' => 'cancel', 'task' => ['business_type' => 'phone_shop', 'business_refs' => ['order_id' => 12, 'order_goods_ids' => [8, 9]]]];
    rejects(fn() => $guard->handle($guardData), '已确认该包裹发货');
    Order::$rows = [$order];
    $guard->handle($guardData); check(true, 'Undelivered package can request cancellation');
    $recoverData = $guardData; $recoverData['operation'] = 'recover';
    check($guard->handle($recoverData) === true, 'Recover requires explicit business approval');
    Order::$rows[0]['order_goods'][0]['extend'] = ['erp_return' => ['time' => 123]];
    rejects(fn() => $guard->handle($recoverData), '退回');
    check($guard->handle($guardData) === true, 'Cancelling the courier task remains available after ERP return, without changing inventory');
    Order::$rows = [$order];
    Order::$rows[0]['status'] = -1;
    rejects(fn() => $guard->handle($recoverData), '已关闭');
    Order::$rows = [$order]; Order::$rows[0]['order_goods'][0]['status'] = 2;
    rejects(fn() => $guard->handle($recoverData), '退款');
    Order::$rows = [$order]; Order::$rows[0]['order_goods'][0]['delivery_id'] = 22;
    rejects(fn() => $guard->handle($recoverData), '已确认该包裹发货');
    Order::$rows = [$order];
    $missingRefs = $guardData; $missingRefs['task']['business_refs']['order_goods_ids'][] = 999;
    rejects(fn() => $guard->handle($missingRefs), '部分商品不存在');
    $delivery = ['waybill_provider_key' => 'test', 'order_id' => 12, 'order_goods_ids' => [8, 9], 'express_number' => 'SF123', 'express_company_id' => 3];
    $service->assertReadyForDelivery(100005, $delivery); check(true, 'Valid original waybill accepted');
    rejects(fn() => $service->assertReadyForDelivery(100005, array_replace($delivery, ['express_number' => 'SF999'])), '运单号');
    rejects(fn() => $service->assertReadyForDelivery(100005, array_replace($delivery, ['express_company_id' => 8])), '承运商');
    Provider::$task['state'] = 'cancelled';
    rejects(fn() => $service->assertReadyForDelivery(100005, $delivery), '已取消');
    check(Order::$rows === [$order], 'Waybill operations never mutate shipment, inventory or finances');
    // 必须执行真实实物发货方法，不能只测 assertReadyForDelivery 帮助方法而漏掉调用位置。
    $dispatcher = new class extends \addon\phone_shop\app\service\core\order\CoreOrderDeliveryService {
        public array $packages = [];
        public function __construct() {}
        public function package($data) { $this->packages[] = $data; return count($this->packages); }
    };
    $goods = new class { public array $updates = []; public function update($data) { $this->updates[] = $data; } };
    $dispatchData = ['order_data' => ['order_id' => 12, 'site_id' => 100005, 'delivery_type' => 'express'], 'order_goods_data' => $goods,
        'param' => $delivery + ['delivery_type' => 'express', 'delivery_way' => 'manual_write', 'electronic_sheet_id' => 0, 'remark' => '', 'waybill_order_goods_ids' => [8, 9]]];
    foreach (['cancelled', 'unknown', 'cancel_unknown'] as $state) {
        Provider::$task['state'] = $state;
        rejects(fn() => $dispatcher->express($dispatchData), '已取消/状态不明');
        check(!$dispatcher->packages && !$goods->updates, 'Invalid waybill blocked before real shipment branch creates package or updates goods: ' . $state);
    }
    Provider::$task['state'] = 'ready';
    Provider::$task['environment'] = 'sandbox';
    Provider::$task['can_confirm_delivery'] = false;
    rejects(fn() => $dispatcher->express($dispatchData), '仅用于测试');
    check(!$dispatcher->packages && !$goods->updates, 'Sandbox is blocked in actual shipment method before any order writes');
    Provider::$task['environment'] = 'production';
    rejects(fn() => $dispatcher->express($dispatchData), '仅用于测试');
    Provider::$task['can_confirm_delivery'] = true;
    $wrongNumber = $dispatchData; $wrongNumber['param']['express_number'] = 'WRONG';
    rejects(fn() => $dispatcher->express($wrongNumber), '运单号');
    $wrongCompany = $dispatchData; $wrongCompany['param']['express_company_id'] = 999;
    rejects(fn() => $dispatcher->express($wrongCompany), '承运商');
    check(!$dispatcher->packages && !$goods->updates, 'Mismatches cannot persist a package');
    $dispatcher->express($dispatchData);
    check(count($dispatcher->packages) === 1 && count($goods->updates) === 1, 'Actual express method allows valid matching original waybill');
    $manual = $dispatchData; unset($manual['param']['waybill_provider_key']); $manual['param']['express_number'] = 'EXTERNAL-MANUAL';
    Provider::$calls = [];
    Provider::$blockedManualNumbers[100005] = ['SANDBOX-KNOWN'];
    $sandboxManual = $manual; $sandboxManual['param']['express_number'] = 'SANDBOX-KNOWN';
    rejects(fn() => $dispatcher->express($sandboxManual), '禁止手工发货');
    check(count($dispatcher->packages) === 1 && count($goods->updates) === 1, 'Optional known-waybill guard blocks manual bypass before any write');
    $dispatcher->express($manual);
    check(count($dispatcher->packages) === 2 && Provider::$calls === [], 'Original manual shipping remains usable without logistics extension');
    $wrongMode = $dispatchData; $wrongMode['param']['delivery_way'] = 'electronic_sheet';
    rejects(fn() => $dispatcher->express($wrongMode), '先取号');
    $reship = $dispatchData; $reship['param']['delivery_ids'] = [7];
    rejects(fn() => $dispatcher->express($reship), '先取号');
    rejects(fn() => $dispatcher->delivery(['waybill_provider_key' => 'test', 'delivery_type' => 'virtual']), '只能用于物流配送');
    // 中通必须从注册能力、取号、公司匹配一直传到实际交件，不能仅配置页面支持。
    Provider::$carrier = 'zhongtong'; Provider::$waybill = 'ZTO123'; Provider::$task = [];
    \addon\phone_shop\app\model\delivery\Company::$lookups = [];
    $zto = $service->execute(100005, $params);
    check($zto['carrier_code'] === 'zhongtong' && $zto['waybill_no'] === 'ZTO123' && $zto['express_company_id'] === 3, 'Selected ZTO provider returns ZTO waybill and local company mapping');
    $lookups = \addon\phone_shop\app\model\delivery\Company::$lookups;
    check(count($lookups) === 2, 'ZTO mapping checked before allocation and decorated after result');
    foreach ($lookups as $where) check($where === [['site_id', '=', 100005], ['kd100_express_no_electronic_sheet', '=', 'zhongtong']], 'Carrier mapping uses current tenant and ZTO code, not SF or KdBird code');
    Provider::$task = $zto;
    $ztoDispatch = $dispatchData; $ztoDispatch['param']['express_number'] = 'ZTO123';
    $dispatcher->express($ztoDispatch);
    check(count($dispatcher->packages) === 3 && $dispatcher->packages[2]['express_number'] === 'ZTO123', 'Actual express method accepts matching ZTO task');
    // 后续改成顺丰，查询和处理原中通包裹仍按原单承运商匹配。
    Provider::$carrier = 'shunfeng';
    \addon\phone_shop\app\model\delivery\Company::$lookups = [];
    $original = $service->execute(100005, array_replace($params, ['operation' => 'query']));
    check($original['carrier_code'] === 'zhongtong' && $original['express_company_id'] === 3, 'Original ZTO task does not turn into SF when current config changes');
    check(\addon\phone_shop\app\model\delivery\Company::$lookups === [[['site_id', '=', 100005], ['kd100_express_no_electronic_sheet', '=', 'zhongtong']]], 'Historical task maps original carrier after config switch');
    // A provider can explicitly use a normal carrier code without depending on Kuaidi100 columns.
    Provider::$task = []; Provider::$mapping = ['field' => 'express_no', 'code' => 'SF']; Provider::$waybill = 'SF-DIRECT';
    \addon\phone_shop\app\model\delivery\Company::$lookups = [];
    $direct = $service->execute(100005, $params);
    check($direct['express_company_id'] === 3, 'Generic provider mapping resolves an SF company');
    foreach (\addon\phone_shop\app\model\delivery\Company::$lookups as $where) check($where === [['site_id', '=', 100005], ['express_no', '=', 'SF']], 'Generic mapping does not couple SF to Kuaidi100 codes');
    Provider::$task = $direct + ['provider_key' => 'other', 'carrier_mapping' => ['field' => 'express_no', 'code' => 'SF']];
    Provider::$task['provider_key'] = 'other';
    Provider::$calls = [];
    $original = $service->execute(100005, array_replace($params, ['operation' => 'query']));
    check($original['provider_key'] === 'other' && array_column(Provider::$calls, 1) === ['query'], 'Local query preserves original task provider and has no remote operation');
    \WaybillTest\OtherProvider::$allowed = true;
    $service->execute(100005, array_replace($params, ['operation' => 'refresh']));
    check(\WaybillTest\OtherProvider::$calls[0][1] === 'refresh' && \WaybillTest\OtherProvider::$calls[0][2]['task_id'] === 7, 'Explicit refresh dispatches to original saved provider, not current selection');
    Provider::$mapping = [];
    echo "PASS: {$passed} checks; no network or database used.\n";
}
