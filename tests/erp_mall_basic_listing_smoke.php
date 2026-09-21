<?php
declare(strict_types=1);

// 执行真实货源入库、上架监听器、资料保存及 ERP 库存核对。
// 数据库、事务、商品基础建品及平台配置用内存替身；不连接真实站点，不发送通知或付款。
namespace IntakeTest {
    final class Store { public static array $tables = []; public static array $tx = []; public static array $events = []; public static array $handlers = []; public static bool $guard = true; }
    class Row implements \ArrayAccess {
        public function __construct(public string $table, public array $data = []) {}
        public function __get($key) { $value = $this->data[$key] ?? null; if (in_array($key, ['images', 'qc_info', 'raw_payload'], true) && is_string($value)) return json_decode($value, true) ?? []; return $value; }
        public function __isset($key): bool { return isset($this->data[$key]); }
        public function offsetExists($key): bool { return isset($this->data[$key]); }
        public function offsetGet($key): mixed { return $this->__get($key); }
        public function offsetSet($key, $value): void { $this->data[$key] = $value; }
        public function offsetUnset($key): void { unset($this->data[$key]); }
        public function isEmpty(): bool { return $this->data === []; }
        public function toArray(): array { $result = []; foreach ($this->data as $key => $value) $result[$key] = $this->__get($key); return $result; }
        public function save(array $values): bool { $this->data = array_replace($this->data, $values); Store::$tables[$this->table][$this->data[$this->table::PK]] = $this->data; return true; }
    }
    class Rows { public function __construct(private array $rows) {} public function toArray(): array { return $this->rows; } }
    class Query {
        private array $filters = [];
        public function __construct(private string $model) {}
        public function where(...$args): self {
            if (is_array($args[0])) { foreach ($args[0] as $filter) $this->where(...$filter); return $this; }
            [$key, $op, $value] = count($args) === 2 ? [$args[0], '=', $args[1]] : $args;
            $this->filters[] = static fn($row) => match ($op) { '=' => ($row[$key] ?? null) == $value, 'in' => in_array($row[$key] ?? null, $value), default => throw new \RuntimeException('Unexpected query ' . $op) };
            return $this;
        }
        public function whereIn($key, $values): self { return $this->where($key, 'in', $values); }
        public function lock($lock): self { if ($lock && Store::$tx === []) throw new \RuntimeException('Row lock outside transaction'); return $this; }
        public function field($fields): self { return $this; }
        public function order($order): self { return $this; }
        public function append($fields): self { return $this; }
        private function rows(): array { return array_values(array_filter(Store::$tables[$this->model] ?? [], function ($row) { foreach ($this->filters as $test) if (!$test($row)) return false; return true; })); }
        public function findOrEmpty(): Row { return new $this->model($this->model, $this->rows()[0] ?? []); }
        public function select(): Rows { return new Rows(array_map(fn($row) => (new $this->model($this->model, $row))->toArray(), $this->rows())); }
        public function count(): int { return count($this->rows()); }
        public function value($key) { return $this->rows()[0][$key] ?? null; }
        public function column($key, $index = null): array { return array_column($this->rows(), $key, $index); }
        public function update($values): int { $rows = $this->rows(); foreach ($rows as $row) (new $this->model($this->model, $row))->save($values); return count($rows); }
    }
    class Model extends Row {
        public const PK = 'id';
        public function __construct(?string $table = null, array $data = []) { parent::__construct($table ?? static::class, $data); }
        public static function where(...$args): Query { return (new Query(static::class))->where(...$args); }
        public static function whereIn($key, $values): Query { return (new Query(static::class))->whereIn($key, $values); }
        public static function create(array $values): static { $id = $values[static::PK] ?? (max(array_keys(Store::$tables[static::class] ?? [0 => []])) + 1); $row = new static(static::class, $values + [static::PK => $id]); $row->save([]); return $row; }
    }
}
namespace core\base {
    class BaseCoreService { protected $model; public function __construct() {} }
    class BaseAdminService extends BaseCoreService { protected $site_id = 100005; protected $uid = 21; protected $username = '资料运营'; }
}
namespace core\exception { class AdminException extends \RuntimeException {} class CommonException extends \RuntimeException {} }
namespace think\facade {
    class Db {
        public static function transaction($fn) { \IntakeTest\Store::$tx[] = \IntakeTest\Store::$tables; try { $result = $fn(); array_pop(\IntakeTest\Store::$tx); return $result; } catch (\Throwable $e) { \IntakeTest\Store::$tables = array_pop(\IntakeTest\Store::$tx); throw $e; } }
        public static function name(string $table): \IntakeTest\Query {
            if ($table !== 'phone_shop_goods_category') throw new \RuntimeException('Unexpected table ' . $table);
            return new \IntakeTest\Query(\addon\phone_shop\app\model\goods\Category::class);
        }
        public static function connect(): self { return new self(); } public function getPdo(): self { return $this; } public function inTransaction(): bool { return \IntakeTest\Store::$tx !== []; }
    }
    class Log { public static function write(...$args): void {} public static function warning(...$args): void {} }
}
namespace app\service\core\sys {
    class CoreConfigService { public static array $data = []; public function getConfigValue($site, $key) { return self::$data[$site][$key] ?? []; } public function setConfig($site, $key, $data): void { self::$data[$site][$key] = $data; } }
}
namespace app\model\member { class MemberLevel extends \IntakeTest\Model {} }
namespace addon\phone_shop\app\service\admin { class MemberLevelNoService { public static function levelsWithNo($site): array { return []; } } }
namespace addon\phone_shop\app\model\intake { class DeviceIntake extends \IntakeTest\Model { public const PK = 'intake_id'; public const STATUS_PENDING = 0; public const STATUS_BUILT = 1; public const STATUS_IGNORED = 2; } }
namespace addon\phone_shop\app\model\goods {
    class Goods extends \IntakeTest\Model { public const PK = 'goods_id'; }
    class GoodsSku extends \IntakeTest\Model { public const PK = 'sku_id'; }
    class Category extends \IntakeTest\Model { public const PK = 'category_id'; }
    class Service extends \IntakeTest\Model { public const PK = 'service_id'; }
    class Attr extends \IntakeTest\Model { public const PK = 'attr_id'; }
}
namespace addon\phone_shop\app\service\admin\goods {
    class SpecService {
        public static array $catalogs = [];
        public function __construct(private int $site = 0) {}
        public static function forSite(int $site): self { return new self($site); }
        public function optionsForCategory(int $category, array $path): array { return self::$catalogs[$this->site] ?? ['spec_groups' => [], 'grades' => []]; }
    }
    class GoodsService {
        public function addForSite(array $values, int $siteId): int {
            $goods = \addon\phone_shop\app\model\goods\Goods::create($values + ['site_id' => $siteId, 'sale_status' => 'available']);
            \addon\phone_shop\app\model\goods\GoodsSku::create(['site_id' => $siteId, 'goods_id' => $goods->goods_id, 'price' => $values['price'], 'stock' => 1]);
            return (int)$goods->goods_id;
        }
    }
}
namespace addon\phone_shop\app\service\core\order { class CoreOrderInventoryService { public function guardErpSale($site, $asset): void {} } }
namespace addon\phone_shop\app\service\core\agent {
    class AgentConfigService { public static bool $master = false; public function isMasterSite($site): bool { return self::$master; } }
    class RefDataSyncService { public function resolveAgentRefId($type, $master, $agent, $id): int { return $id + 1000; } }
}
namespace addon\phone_shop\app\model\agent { class PhoneShopAgent extends \IntakeTest\Model {} }
namespace addon\hsx_erp\app\model { class ErpAsset extends \IntakeTest\Model {} class ErpWarehouse extends \IntakeTest\Model {} }
namespace addon\hsx_erp\app\service\admin {
    class ErpWarehousePolicyService { public static bool $allowed = true; public static array $extra = []; public static function forSite($site): self { return new self(); } public function evaluate($asset, $warehouse): array { return ['can_prepare_mall' => $warehouse !== null && self::$allowed ? 1 : 0] + self::$extra; } }
}
namespace {
    use IntakeTest\Store;
    use addon\phone_shop\app\model\goods\{Goods, GoodsSku, Category};
    use addon\phone_shop\app\model\intake\DeviceIntake;
    use addon\phone_shop\app\service\admin\intake\DeviceIntakeService;
    use addon\phone_shop\app\service\core\intake\CoreDeviceIntakeService;
    use addon\phone_shop\app\support\IntakeMaterialTask;
    use addon\phone_shop\app\listener\erp\ErpPublishListing;
    use addon\hsx_erp\app\model\{ErpAsset, ErpWarehouse};
    use addon\hsx_erp\app\listener\marketplace\BasicListingEligibility;
    use app\service\core\sys\CoreConfigService;
    $base = dirname(__DIR__) . '/niucloud/addon/';
    require $base . 'phone_shop/app/service/core/goods/CoreTierPricingService.php';
    require $base . 'phone_shop/app/support/IntakeMaterialAttributes.php';
    foreach (['hsx_erp/app/support/ErpListingFormContract.php', 'hsx_erp/app/service/admin/ErpConfigService.php', 'hsx_erp/app/listener/marketplace/BasicListingEligibility.php', 'phone_shop/app/support/IntakeMaterialTask.php', 'phone_shop/app/service/core/goods/CoreDeviceAttributeService.php', 'phone_shop/app/service/core/goods/CoreGoodsDescriptionService.php', 'phone_shop/app/service/core/intake/CoreListingMappingService.php', 'phone_shop/app/service/core/intake/CoreDeviceIntakeService.php', 'phone_shop/app/service/admin/intake/DeviceIntakeService.php', 'phone_shop/app/listener/erp/ErpPublishListing.php'] as $path) require $base . $path;
    set_error_handler(static function ($severity, $message, $file, $line): void { throw new \ErrorException($message, 0, $severity, $file, $line); });
    function event($name, $payload): array {
        Store::$events[] = [$name, $payload];
        if (isset(Store::$handlers[$name])) return Store::$handlers[$name]($payload);
        if ($name === 'HsxErpBasicListingEligibility') return Store::$guard ? [(new BasicListingEligibility())->handle($payload)] : [];
        if (in_array($name, ['PhoneShopListingMaterialCompleted', 'HsxErpChannelMappingResolve'], true)) return [];
        throw new \RuntimeException('Unexpected event ' . $name);
    }
    $checks = 0;
    function get_file_url(string $path): string { return 'https://local.test/' . ltrim($path, '/'); }
    function check(bool $pass, string $message): void { if (!$pass) throw new \RuntimeException('FAIL: ' . $message); $GLOBALS['checks']++; }
    function denied(callable $fn, string $message): void { try { $fn(); } catch (\Throwable $e) { check(str_contains($e->getMessage(), $message), '拒绝原因准确: ' . $message . ' / ' . $e->getMessage()); return; } throw new \RuntimeException('未拒绝: ' . $message); }
    function fixture(): array {
        Store::$tables = []; Store::$events = []; Store::$guard = true;
        \addon\phone_shop\app\service\admin\goods\SpecService::$catalogs = [100005 => [
            'spec_groups' => [['label' => '内存', 'items' => [['item_value' => '256G']]], ['label' => '颜色', 'items' => [['item_value' => '蓝色'], ['item_value' => '红色']]]],
            'grades' => [['grade_name' => '9成新', 'status' => 1]],
        ]];
        \addon\phone_shop\app\service\core\agent\AgentConfigService::$master = false;
        \addon\hsx_erp\app\service\admin\ErpWarehousePolicyService::$allowed = true;
        CoreConfigService::$data = [];
        CoreConfigService::$data[100005]['HSX_ERP_RULES'] = ['marketplace' => ['channels' => ['phone_shop' => ['enabled' => 1, 'publish_mode' => 'basic_first']]]];
        Category::create(['category_id' => 1, 'site_id' => 100005, 'pid' => 0, 'is_show' => 1]);
        Category::create(['category_id' => 2, 'site_id' => 100005, 'pid' => 1, 'is_show' => 1]);
        Category::create(['category_id' => 3, 'site_id' => 100006, 'pid' => 0, 'is_show' => 1]);
        ErpAsset::create(['id' => 666, 'site_id' => 100005, 'imei' => '990000000000666', 'status' => 'in_stock', 'sale_target' => 'mall', 'warehouse_id' => 10, 'retail_price' => 5000]);
        ErpWarehouse::create(['id' => 10, 'site_id' => 100005]);
        return ['site_id' => 100005, 'payload' => ['erp_asset_id' => 666, 'model_name' => '测试型号', 'imei' => '990000000000666', 'images' => ['upload/test/only-test.jpg'], 'sale_price' => 5000, 'cost_price' => 4500, 'peer_price' => 0, 'basic_first' => 1, 'goods_category' => [1, 2], 'qc_info' => ['report' => ['result_items' => [['field_name' => '屏幕', 'value' => '有划痕', 'severity' => 'general']]]]]];
    }
    $event = fixture();
    $listener = new ErpPublishListing();
    $result = $listener->handle($event);
    check($result['status'] === 'published', '分类、照片、价格具备即可上架: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
    $id = $result['intake_id']; $goodsId = $result['goods_id'];
    $service = DeviceIntakeService::forSite(100005);
    $goods = Goods::where('goods_id', $goodsId)->findOrEmpty()->toArray();
    check($goods['status'] === 1 && $goods['stock'] === 1 && $goods['is_online_sellable'] === 1 && $goods['sale_status'] === 'available', '新商品具有实际可售字段');
    check($goods['goods_category'] === [1, 2] && $goods['price'] === 5000.0, '分类与定价正确进入商城');
    check($goods['battery_health'] === -1 && $goods['warranty_expire_time'] === 0, '未知电池与保修不伪造');
    check(str_contains($goods['qc_report'], '有划痕'), '结构化质检同步保留异常事实');
    check(GoodsSku::where('goods_id', $goodsId)->value('erp_asset_id') === 666, '单 SKU 精确关联 ERP 资产');
    $info = $service->materialInfo($id);
    check($info['material_task']['status'] === 'pending' && $info['listing_state_name'] === '已上架可售', '待完善与上架可售同时存在');
    $result = $listener->handle($event);
    check($result['status'] === 'duplicate' && count(Store::$tables[Goods::class]) === 1, '重复交接不重复建品');
    $goodsBefore = Goods::where('goods_id', $goodsId)->findOrEmpty()->toArray();
    $skuBefore = GoodsSku::where('goods_id', $goodsId)->findOrEmpty()->toArray();
    Store::$events = [];
    $save = $service->saveMaterial($id, ['revision' => 0, 'action' => 'save', 'device_color' => '蓝色', 'battery_health' => 92, 'price' => 1, 'stock' => 99, 'status' => 0, 'erp_asset_id' => 100, 'goods_id' => 99, 'cost_price' => 1]);
    $goodsAfter = Goods::where('goods_id', $goodsId)->findOrEmpty()->toArray();
    check($save['material_task']['status'] === 'processing' && $save['material_task']['revision'] === 1, '保存进度记录处理人与版本');
    foreach (['price', 'stock', 'status', 'sale_status', 'cost_price', 'goods_id', 'goods_category', 'qc_report'] as $field) check($goodsAfter[$field] === $goodsBefore[$field], '补资料不改 ' . $field);
    check(GoodsSku::where('goods_id', $goodsId)->findOrEmpty()->toArray() === $skuBefore && Store::$events === [], '不改 SKU 库存与关联，不触发上架或财务事件');
    check($service->getInfo($id)['color'] === '蓝色', '列表详情展示已更新资料而非原交接快照');
    $before = Store::$tables;
    denied(fn() => $service->saveMaterial($id, ['revision' => 0, 'device_color' => '红色']), '资料已被其他人员更新');
    check(Store::$tables === $before, '过期提交完整回滚');
    denied(fn() => $service->saveMaterial($id, ['revision' => 1, 'battery_health' => 150]), '必须在 0-100');
    denied(fn() => DeviceIntakeService::forSite(100006)->saveMaterial($id, ['revision' => 1]), '请先完成分类');
    denied(fn() => $service->setStatus($id, 0), '商品已经建立');
    denied(fn() => $service->build(['intake_id' => $id]), '请勿重复');
    Goods::where('goods_id', $goodsId)->update(['status' => 0, 'stock' => 0, 'sale_status' => 'sold']);
    $done = $service->saveMaterial($id, ['revision' => 1, 'action' => 'complete', 'memory_group' => '256G']);
    check($done['material_task']['status'] === 'completed' && $done['material_task']['operator_name'] === '资料运营', '核对完成及处理人留痕');
    check($done['listing_state_name'] === '已售' && Goods::where('goods_id', $goodsId)->value('stock') === 0, '已售商品补完资料不能重新上架');
    check($listener->handle($event)['status'] === 'failed', '已售商品重复交接不能谎报上架成功');
    (new CoreDeviceIntakeService())->saveFromEvent($event);
    check($service->materialInfo($id)['material_task']['status'] === 'completed', 'ERP 再次交接不重置已完成待办');
    check($service->materialInfo($id)['form']['memory_group'] === '256G', 'ERP 再次交接不覆盖商城运营修改');
    \addon\phone_shop\app\service\core\agent\AgentConfigService::$master = true;
    \addon\phone_shop\app\model\agent\PhoneShopAgent::create(['master_site_id' => 100005, 'agent_site_id' => 100024, 'status' => 1]);
    foreach ([101 => 100024, 102 => 100025] as $proxyId => $site) {
        Goods::create(['goods_id' => $proxyId, 'site_id' => $site, 'source' => '100005', 'source_goods_id' => $goodsId, 'is_proxy' => 1, 'status' => 0, 'stock' => 0, 'sale_status' => 'sold', 'price' => 9999, 'condition_grade' => '原成色']);
        GoodsSku::create(['goods_id' => $proxyId, 'site_id' => $site, 'stock' => 0, 'price' => 9999, 'condition_grade' => '原成色']);
    }
    $saved = $service->saveMaterial($id, ['revision' => 2, 'action' => 'complete', 'condition_grade' => '9成新']);
    check($saved['warning'] === '', '资料副本同步无异常');
    $proxy = Goods::where('goods_id', 101)->findOrEmpty()->toArray();
    check($proxy['condition_grade'] === '9成新', '已关联且启用的从站更新展示资料');
    foreach (['status' => 0, 'stock' => 0, 'sale_status' => 'sold', 'price' => 9999] as $key => $value) check($proxy[$key] === $value, '补资料不重写从站 ' . $key);
    check(GoodsSku::where('goods_id', 101)->value('stock') === 0 && GoodsSku::where('goods_id', 101)->value('price') === 9999, '从站 SKU 价格库存不变');
    check(Goods::where('goods_id', 102)->value('condition_grade') === '原成色', '没有启用跟随关系的站点不受影响');

    foreach (['missing_category', 'other_site_category', 'hidden_category', 'wrong_path', 'zero_price', 'no_images', 'sold_asset', 'price_changed', 'warehouse_block', 'channel_closed', 'missing_guard'] as $case) {
        $event = fixture();
        switch ($case) {
            case 'missing_category': $event['payload']['goods_category'] = []; break;
            case 'other_site_category': $event['payload']['goods_category'] = [3]; break;
            case 'hidden_category': Category::where('category_id', 2)->update(['is_show' => 0]); break;
            case 'wrong_path': $event['payload']['goods_category'] = [2, 1]; break;
            case 'zero_price': $event['payload']['sale_price'] = 0; break;
            case 'no_images': $event['payload']['images'] = []; break;
            case 'sold_asset': ErpAsset::where('id', 666)->update(['status' => 'sold']); break;
            case 'price_changed': ErpAsset::where('id', 666)->update(['retail_price' => 6000]); break;
            case 'warehouse_block': \addon\hsx_erp\app\service\admin\ErpWarehousePolicyService::$allowed = false; break;
            case 'channel_closed': CoreConfigService::$data[100005]['HSX_ERP_RULES']['marketplace']['channels']['phone_shop']['enabled'] = 0; break;
            case 'missing_guard': Store::$guard = false; break;
        }
        $result = $listener->handle($event);
        check(in_array($result['status'], ['pending', 'failed'], true) && !empty($result['message']), $case . ' 有明确失败或待办原因');
        check(empty(Store::$tables[Goods::class]) && count(Store::$tables[DeviceIntake::class]) === 1, $case . ' 不建品但保留货源');
        if ($result['status'] === 'failed') {
            $raw = DeviceIntake::where('erp_asset_id', 666)->value('raw_payload');
            check(IntakeMaterialTask::payload($raw)['basic_block_reason'] === $result['message'], $case . ' 运营可见实际失败原因');
        }
    }
    $event = fixture();
    $event['site_id'] = 100007;
    CoreConfigService::$data[100007] = CoreConfigService::$data[100005];
    foreach ([Category::class, ErpAsset::class, ErpWarehouse::class] as $table) {
        foreach (Store::$tables[$table] as &$row) if ($row['site_id'] === 100005) $row['site_id'] = 100007;
        unset($row);
    }
    $result = $listener->handle($event);
    check($result['status'] === 'published', '非当前请求站点也能按明确事件站点建品');
    check(Goods::where('goods_id', $result['goods_id'])->value('site_id') === 100007
        && GoodsSku::where('goods_id', $result['goods_id'])->value('site_id') === 100007, '商品和 SKU 均属于事件站点，不串入当前后台站点');
    $raw = IntakeMaterialTask::mergeSnapshot([], ['basic_first' => 1, '_material_task' => ['status' => 'completed']], 100);
    check(IntakeMaterialTask::read($raw)['status'] === 'pending', '上游不得伪造已完成资料状态');
    $raw = IntakeMaterialTask::transition($raw, 'complete', 21, '测试', 101);
    $raw = IntakeMaterialTask::mergeSnapshot($raw, ['basic_first' => 0], 102);
    check(IntakeMaterialTask::read($raw)['status'] === 'completed', '切换配置不清除既有处理记录');
    check(!in_array('电池健康度', IntakeMaterialTask::unknownFields(['battery_health' => 0]), true), '实测 0% 不等同未知');
    check(Store::$tx === [], '事务均已结束');
    restore_error_handler();
    echo "[PASS] ERP mall basic listing: {$checks} assertions; in-memory only, no live data changed\n";
}
