<?php
declare(strict_types=1);

// 隔离回归：真实控制器/保存/签收处理器/自动打印触发/任务方法，模型、场景和流程外围用内存替身。
// 不加载应用或 .env，不连接业务数据库，不访问云打印，不生成实际纸张。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
}

namespace SignPrintFixture {
    final class Store {
        public static array $tables = [], $trace = [], $prints = [], $skips = [], $logs = [], $snapshots = [];
        public static int $depth = 0;
        public static bool $saveFails = false, $auto = true, $lockFails = false;
        public static string $scope = 'none';
        public static array $throwPlanFor = [], $failPrintFor = [];
        public static function reset(): void {
            self::$tables = self::$trace = self::$prints = self::$skips = self::$logs = self::$snapshots = [];
            self::$depth = 0; self::$saveFails = false; self::$auto = true; self::$lockFails = false; self::$scope = 'none';
            self::$throwPlanFor = self::$failPrintFor = [];
        }
        public static function seed(string $class, array $row): void {
            $pk = (new $class())->fixturePk(); self::$tables[$class][$row[$pk]] = $row;
        }
    }
    class Model {
        protected $pk = 'id';
        private array $saved = [];
        public function fixturePk(): string { return $this->pk; }
        public function __get($key) { return $this->saved[$key] ?? null; }
        public function save(array $values): bool {
            $row = (new Query(static::class))->create($values);
            $this->saved = $row->toArray(); return true;
        }
        public static function __callStatic($method, $args) { return (new Query(static::class))->$method(...$args); }
        public function __call($method, $args) { return (new Query(static::class))->$method(...$args); }
    }
    final class Row implements \ArrayAccess {
        private string $class;
        private array $row;
        public function __construct(string $class, array $row = []) { $this->class = $class; $this->row = $row; }
        public function __get($key) { return $this->row[$key] ?? null; }
        public function __set($key, $value): void { $this->row[$key] = $value; }
        public function isEmpty(): bool { return $this->row === []; }
        public function toArray(): array { return $this->row; }
        public function append($unused): self { return $this; }
        public function save(array $data = []): bool {
            if (Store::$saveFails) throw new \RuntimeException('fixture-save-failed');
            $pk = (new $this->class())->fixturePk();
            $this->row = array_replace($this->row, $data);
            if (!isset($this->row[$pk])) $this->row[$pk] = max(array_merge([0], array_keys(Store::$tables[$this->class] ?? []))) + 1;
            Store::$tables[$this->class][$this->row[$pk]] = $this->row;
            Store::$trace[] = ['save', $this->class, $this->row[$pk], Store::$depth];
            return true;
        }
        public function offsetExists($key): bool { return isset($this->row[$key]); }
        public function offsetGet($key) { return $this->row[$key] ?? null; }
        public function offsetSet($key, $value): void { $this->row[$key] = $value; }
        public function offsetUnset($key): void { unset($this->row[$key]); }
    }
    final class Rows {
        private array $rows;
        public function __construct(array $rows) { $this->rows = $rows; }
        public function toArray(): array { return array_values($this->rows); }
    }
    final class Query {
        private string $class;
        private array $conditions = [], $increments = [];
        private bool $locked = false;
        public function __construct(string $class) { $this->class = $class; }
        public function where($field, $operator = null, $value = null): self {
            if (is_array($field)) {
                foreach ($field as $key => $item) {
                    $this->conditions[] = is_array($item) ? $item : [$key, '=', $item];
                }
            } else $this->conditions[] = func_num_args() === 2 ? [$field, '=', $operator] : [$field, $operator, $value];
            return $this;
        }
        public function whereIn(string $field, array $values): self { return $this->where($field, 'in', $values); }
        public function lock($enabled): self { $this->locked = (bool)$enabled; return $this; }
        public function field($unused): self { return $this; }
        public function order($unused): self { return $this; }
        public function limit($unused): self { return $this; }
        public function inc(string $field, $amount = 1): self { $this->increments[$field] = $amount; return $this; }
        private function rows(): array {
            return array_filter(Store::$tables[$this->class] ?? [], function ($row) {
                foreach ($this->conditions as [$field, $operator, $value]) {
                    $actual = $row[$field] ?? null;
                    if ($operator === '=' && $actual != $value) return false;
                    if ($operator === '<>' && $actual == $value) return false;
                    if (strtolower((string)$operator) === 'in' && !in_array($actual, $value, false)) return false;
                    if ($operator === '>' && !($actual > $value)) return false;
                }
                return true;
            });
        }
        public function findOrEmpty($id = null): Row {
            if ($id !== null) $this->where((new $this->class())->fixturePk(), '=', $id);
            $row = array_values($this->rows())[0] ?? [];
            if ($this->locked) {
                Store::$trace[] = ['lock', $this->class, (int)($row['id'] ?? 0), Store::$depth];
                if (Store::$lockFails) throw new \RuntimeException('fixture-lock-failed');
            }
            return new Row($this->class, $row);
        }
        public function find($id = null): ?Row { $row = $this->findOrEmpty($id); return $row->isEmpty() ? null : $row; }
        public function select(): Rows { return new Rows($this->rows()); }
        public function column(string $field, string $key = ''): array {
            $out = [];
            foreach ($this->rows() as $row) {
                $value = $field === '*' ? $row : ($row[$field] ?? null);
                if ($key === '') $out[] = $value; else $out[$row[$key]] = $value;
            }
            return $out;
        }
        public function value(string $field) { return array_values($this->rows())[0][$field] ?? null; }
        public function count(): int { return count($this->rows()); }
        public function update(array $values): int {
            $rows = $this->rows();
            foreach ($rows as $id => $row) {
                foreach ($this->increments as $field => $amount) $row[$field] = ($row[$field] ?? 0) + $amount;
                Store::$tables[$this->class][$id] = array_replace($row, $values);
            }
            return count($rows);
        }
        public function create(array $values): Row { $row = new Row($this->class); $row->save($values); return $row; }
        public function save(array $values): bool { $this->create($values); return true; }
    }
    final class Request {
        public array $data;
        public function __construct(array $data) { $this->data = $data; }
        public function param(): array { return $this->data; }
    }
    final class Validate {
        public function scene($unused): self { return $this; }
        public function check($unused): bool { return true; }
        public function failException(): self { return $this; }
    }
}

namespace core\base {
    class BaseModel extends \SignPrintFixture\Model {}
    class BaseAdminService {
        public int $site_id = 100005, $uid = 7;
        public function __construct() {}
    }
    class BaseAdminController { public $request; }
}
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace think\facade {
    class Db {
        public static function startTrans(): void {
            \SignPrintFixture\Store::$snapshots[] = \SignPrintFixture\Store::$tables;
            \SignPrintFixture\Store::$depth++;
            \SignPrintFixture\Store::$trace[] = ['begin', \SignPrintFixture\Store::$depth];
        }
        public static function commit(): void {
            array_pop(\SignPrintFixture\Store::$snapshots); \SignPrintFixture\Store::$depth--;
            \SignPrintFixture\Store::$trace[] = ['commit', \SignPrintFixture\Store::$depth,
                array_column(\SignPrintFixture\Store::$tables['addon\\hsx_recycle\\app\\model\\printer\\RecyclePrintTask'] ?? [], 'status')];
        }
        public static function rollback(): void {
            if (\SignPrintFixture\Store::$snapshots !== []) \SignPrintFixture\Store::$tables = array_pop(\SignPrintFixture\Store::$snapshots);
            \SignPrintFixture\Store::$depth = max(0, \SignPrintFixture\Store::$depth - 1);
            \SignPrintFixture\Store::$trace[] = ['rollback', \SignPrintFixture\Store::$depth];
        }
        public static function transaction(callable $work) {
            self::startTrans();
            try { $result = $work(); self::commit(); return $result; }
            catch (\Throwable $exception) { self::rollback(); throw $exception; }
        }
    }
    class Log { public static function __callStatic($method, $args): void { \SignPrintFixture\Store::$logs[] = [$method, $args]; } }
}
namespace addon\hsx_recycle\app\model\order {
    class RecycleDevice extends \SignPrintFixture\Model {}
    class RecycleOrder extends \SignPrintFixture\Model {}
}
namespace addon\hsx_recycle\app\service\core\recycle_order {
    class CoreRecycleOrderNotifyService {}
    class DeviceSummaryHelper {
        public static function normalizeCategoryPath($path, int $id): array { return is_array($path) ? $path : [$id]; }
        public static function normalizeSummary($data): array { return is_array($data) ? $data : []; }
        public static function reservedColumns(array $summary, array $device): array {
            return array_intersect_key($device + ['color' => '', 'capacity' => '', 'system_version' => '', 'warranty_info' => ''], array_flip(['color', 'capacity', 'system_version', 'warranty_info']));
        }
        public static function buildInfo($old, $path, $summary, $device, $site): array { return ['sign_summary' => $summary]; }
    }
    class CoreRecycleOrderFlowService {
        const FLOW_TYPE_ADMIN = 'admin';
        public static bool $fail = false;
        public static ?array $forcedIds = null;
        public function execute(int $id, string $action, array $data, string $type, array $context): array {
            if (self::$fail) throw new \RuntimeException('fixture-flow-failed');
            \think\facade\Db::startTrans();
            try {
                $order = \addon\hsx_recycle\app\model\order\RecycleOrder::where('id', $id)->findOrEmpty()->toArray();
                $result = (new handler\SignHandler())->handle($order, $data, $context);
                if (self::$forcedIds !== null) $result['data']['device_ids'] = self::$forcedIds;
                \addon\hsx_recycle\app\model\order\RecycleOrder::where('id', $id)->update(['status' => 2]);
                \think\facade\Db::commit();
                return ['success' => true, 'data' => $result];
            } catch (\Throwable $exception) { \think\facade\Db::rollback(); throw $exception; }
        }
    }
}
namespace addon\hsx_recycle\app\service\core\recycle_device { class CoreRecycleDeviceLogService {} }
namespace addon\hsx_recycle\app\service\admin\device {
    class RecycleDeviceModelDictService {
        public function incrementSelectCount($unused): void {}
        public function ensureFromModelName($name, $site): void {}
    }
}
namespace addon\hsx_recycle\app\service\admin\stat {
    class TaskService { public function assignPreferredOrDefault($id, $stage, $uid): void {} }
}
namespace addon\hsx_recycle\app\service\admin\printer {
    class RecyclePrintSceneService {
        public function resolvePlansByTrigger(string $trigger, array $payload, bool $requireAuto): array {
            $id = (int)($payload['device_id'] ?? 0);
            \SignPrintFixture\Store::$trace[] = ['resolve', $id, \SignPrintFixture\Store::$depth];
            if (in_array($id, \SignPrintFixture\Store::$throwPlanFor, true)) throw new \RuntimeException('fixture-plan-failed');
            $device = \addon\hsx_recycle\app\model\order\RecycleDevice::where('id', $id)->where('site_id', 100005)->findOrEmpty()->toArray();
            return [[
                'can_print' => \SignPrintFixture\Store::$auto,
                'message' => \SignPrintFixture\Store::$auto ? 'ready' : '自动打印未开启',
                'scene' => ['scene_key' => 'fixture_sign_label', 'scene_name' => '签收标签', 'trigger_key' => $trigger, 'biz_type' => 'device', 'idempotency_scope' => \SignPrintFixture\Store::$scope, 'max_attempts' => 3],
                'biz' => ['biz_type' => 'device', 'biz_id' => $id, 'order_id' => (int)($device['order_id'] ?? 0)],
                'device' => ['device_id' => $id, 'order_id' => (int)($device['order_id'] ?? 0)],
                'template' => ['template_id' => 1, 'template_name' => 'fixture'],
                'printer' => ['printer_id' => 1, 'printer_name' => '内存打印机'],
                'print_data' => $device,
            ]];
        }
        public function recordSkippedPlan(array $plan, string $message): void { \SignPrintFixture\Store::$skips[] = [$plan, $message]; }
        public function executePrintPlan(array $plan): array {
            $id = (int)$plan['device']['device_id'];
            if (\SignPrintFixture\Store::$depth !== 0) throw new \RuntimeException('printing-before-commit');
            \SignPrintFixture\Store::$prints[] = $plan;
            \SignPrintFixture\Store::$trace[] = ['print', $id, \SignPrintFixture\Store::$depth];
            return ['success' => !in_array($id, \SignPrintFixture\Store::$failPrintFor, true), 'message' => 'fixture-print-result'];
        }
    }
}

namespace {
    use SignPrintFixture\Store;
    use addon\hsx_recycle\app\model\order\RecycleDevice;
    use addon\hsx_recycle\app\model\order\RecycleOrder;
    use addon\hsx_recycle\app\model\printer\RecyclePrintTask;
    use addon\hsx_recycle\app\service\admin\order\RecycleDeviceService;
    use addon\hsx_recycle\app\service\admin\order\RecycleOrderService;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrintTriggerService;
    use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderFlowService;

    function success($data): array { return ['code' => 1, 'data' => $data]; }
    function same($expected, $actual, string $message): void {
        if ($expected !== $actual) throw new \RuntimeException('FAIL ' . $message . ': ' . json_encode(['expected' => $expected, 'actual' => $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++; echo 'PASS ' . $message . PHP_EOL;
    }
    function denied(callable $call, string $message): void {
        try { $call(); } catch (\Throwable $exception) { same(true, $exception->getMessage() !== '', $message); return; }
        throw new \RuntimeException('FAIL 未拦截 ' . $message);
    }
    function bare(string $class): object { return (new \ReflectionClass($class))->newInstanceWithoutConstructor(); }
    function property(object $object, string $name, $value): void {
        $property = new \ReflectionProperty($object, $name); $property->setAccessible(true); $property->setValue($object, $value);
    }
    function fixture(): RecycleDeviceService {
        Store::reset(); CoreRecycleOrderFlowService::$fail = false; CoreRecycleOrderFlowService::$forcedIds = null;
        Store::seed(RecycleOrder::class, ['id' => 90, 'site_id' => 100005, 'status' => 1, 'member_id' => 75, 'delete_at' => 0]);
        Store::seed(RecycleOrder::class, ['id' => 91, 'site_id' => 100006, 'status' => 1, 'member_id' => 76, 'delete_at' => 0]);
        foreach ([[101,90,100005], [102,90,100005], [201,91,100006], [202,91,100005]] as [$id,$order,$site]) {
            Store::seed(RecycleDevice::class, ['id' => $id, 'site_id' => $site, 'order_id' => $order, 'status' => 1, 'category_id' => 8,
                'imei' => '35390000000' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), 'model' => '机型' . $id, 'final_price' => 0, 'info' => []]);
        }
        return new RecycleDeviceService();
    }
    function signService(): RecycleOrderService {
        $service = bare(RecycleOrderService::class);
        property($service, 'flowService', new CoreRecycleOrderFlowService());
        return $service;
    }
    function deviceInput(int $id = 101): array {
        return ['id' => $id, 'imei' => '35390000000' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), 'model' => '已核对机型' . $id, 'category_id' => 8, 'summary' => ['battery' => 99]];
    }
    function tasks(): array { return array_values(Store::$tables[RecyclePrintTask::class] ?? []); }

    $base = dirname(__DIR__) . '/app/';
    foreach (['dict/order/RecycleOrderDict.php', 'dict/stat/RecycleStageDict.php', 'model/printer/RecyclePrintTask.php',
        'service/core/recycle_order/DeviceEntryImei.php', 'service/core/recycle_order/DeviceReadingArchive.php', 'service/core/recycle_order/handler/BaseFlowHandler.php',
        'service/core/recycle_order/handler/SignHandler.php', 'service/admin/order/RecycleDeviceService.php',
        'service/admin/order/RecycleOrderService.php', 'service/admin/printer/RecyclePrintTaskService.php',
        'service/admin/printer/RecyclePrintTriggerService.php', 'adminapi/controller/order/RecycleDevice.php'] as $file) require $base . $file;

    $checks = 0;
    try {
        set_error_handler(static function ($severity, $message, $file, $line): void { throw new \ErrorException($message, 0, $severity, $file, $line); });
        $service = fixture();
        $service->update(101, ['model' => '普通编辑']);
        same([], Store::$prints, '普通设备编辑不自动打印');
        $service->update(101, ['model' => '签收录入新型号'], true);
        same(1, count(Store::$prints), '客户已有设备显式签收保存触发标签');
        same('签收录入新型号', Store::$prints[0]['print_data']['model'], '打印读取保存后的数据，不是旧快照');
        same(0, Store::$depth, '设备保存与打印后不残留事务');
        same(false, array_key_exists('action', Store::$tables[RecycleDevice::class][101]), '场景标识不落设备业务表');
        same(['device.sign.saved'], array_column(array_column(Store::$prints, 'scene'), 'trigger_key'), '沿用既有签收标签场景');

        $controller = bare(\addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice::class);
        $controller->request = new \SignPrintFixture\Request(['action' => 'sign_save', 'model' => '控制器签收保存']);
        property($controller, 'service', $service); property($controller, 'validate', new \SignPrintFixture\Validate());
        $response = $controller->update(102);
        same(1, $response['code'], '控制器签收保存成功');
        same(2, count(Store::$prints), '控制器sign_save传到真实服务打印');
        same(false, array_key_exists('action', Store::$tables[RecycleDevice::class][102]), '控制器先移除action，不生成不存在字段');
        $controller->request = new \SignPrintFixture\Request(['data' => ['action' => 'sign_save', 'imei' => 'SN0000000000102', 'model' => '旧包裹签收保存', 'summary' => ['battery' => 98]]]);
        same(1, $controller->update(102)['code'], '原data包裹形态也可签收保存');
        same('SN0000000000102', Store::$prints[2]['print_data']['imei'], '包裹形态串号先保存再进入打印快照');
        same(['battery' => 98], Store::$prints[2]['print_data']['info']['sign_summary'], '签收摘要保存完成后才触发打印解析');
        $controller->request = new \SignPrintFixture\Request(['model' => '无动作普通编辑']);
        same(1, $controller->update(102)['code'], '原普通编辑仍成功');
        same(3, count(Store::$prints), '未声明sign_save的控制器编辑不额外打印');
        $service->update(101, ['model' => '仍属原订单', 'order_id' => 91, 'site_id' => 100006, 'member_id' => 999], true);
        same([90, 100005], [Store::$tables[RecycleDevice::class][101]['order_id'], Store::$tables[RecycleDevice::class][101]['site_id']], '签收保存不能迁移设备订单或站点');
        same(false, array_key_exists('member_id', Store::$tables[RecycleDevice::class][101]), '签收保存不能借输入改客户归属');

        $service = fixture(); Store::$saveFails = true;
        denied(fn() => $service->update(101, ['model' => '不能保存'], true), '数据库保存失败向上报告');
        same([], Store::$prints, '保存失败不打印');
        same('机型101', Store::$tables[RecycleDevice::class][101]['model'], '保存失败不改变设备资料');
        same(0, Store::$depth, '保存失败事务回滚');

        $service = fixture();
        denied(fn() => $service->update(201, ['model' => '跨站'], true), '跨站设备拒绝签收保存');
        denied(fn() => $service->update(202, ['model' => '跨站订单'], true), '设备与所属订单站点不匹配拒绝');
        Store::$tables[RecycleOrder::class][90]['status'] = 2;
        denied(fn() => $service->update(101, ['model' => '越状态'], true), '已签收订单不再伪装签收录入');
        same([], Store::$prints, '跨站及越状态请求没有打印副作用');

        $service = fixture(); Store::$auto = false;
        same(true, $service->update(101, ['model' => '已保存'], true), '关闭自动打印仍允许保存');
        same([], Store::$prints, '场景自动打印关闭不出纸');
        same(true, count(Store::$skips) > 0, '未开启场景记入既有跳过记录');

        $service = fixture(); Store::$throwPlanFor = [101];
        same(true, $service->update(101, ['model' => '打印服务失败仍保存'], true), '打印异常不改变保存成功结果');
        same('打印服务失败仍保存', Store::$tables[RecycleDevice::class][101]['model'], '打印异常不回滚已提交设备');
        same(0, count(array_filter(Store::$trace, fn($item) => $item[0] === 'rollback')), '打印异常不进行提交后的伪回滚');

        fixture();
        $inputs = [deviceInput(101), ['imei' => 'SN0000000000999', 'model' => '全新签收设备', 'category_id' => 8, 'summary' => []]];
        same(true, signService()->sign(90, ['devices' => $inputs]), '最终签收同时覆盖客户已有设备与新录设备');
        same(2, count(Store::$prints), '最终签收仅本次两台各打印一份');
        same(['353900000000101', 'SN0000000000999'], array_column(array_column(Store::$prints, 'print_data'), 'imei'), '多设备标签逐台读取自己的串号');
        same(['已核对机型101', '全新签收设备'], array_column(array_column(Store::$prints, 'print_data'), 'model'), '多设备标签型号不串机');
        same(false, in_array(102, array_column(array_column(Store::$prints, 'device'), 'device_id'), true), '未参与本次签收的订单设备不误打');
        same(0, Store::$depth, '最终签收打印发生在流程提交之后');

        $service = fixture(); $service->update(101, ['model' => '逐台已打印'], true);
        same(1, count(Store::$prints), 'scope=none逐台保存先产生一份标签');
        same(true, signService()->sign(90, ['devices' => [deviceInput(101), deviceInput(102)]]), '保存后仍可完成整单签收');
        same(2, count(Store::$prints), 'scope=none签收兜底复用已有自动任务，不重复第一台');
        same([101, 102], array_column(array_column(Store::$prints, 'device'), 'device_id'), '兜底只补未打印的设备');

        fixture(); $trigger = new RecyclePrintTriggerService();
        $payload = ['device_id' => 101, 'order_id' => 90, 'biz_id' => 101];
        $trigger->auto('device.sign.saved', $payload);
        $trigger->auto('device.sign.saved', $payload);
        same(2, count(Store::$prints), '显式保存沿用scope=none原规则，允许再次打印');
        foreach ([RecyclePrintTask::STATUS_PENDING, RecyclePrintTask::STATUS_RUNNING, RecyclePrintTask::STATUS_SUCCESS] as $status) {
            fixture(); $trigger = new RecyclePrintTriggerService(); $trigger->auto('device.sign.saved', $payload);
            $taskId = tasks()[0]['task_id']; Store::$tables[RecyclePrintTask::class][$taskId]['status'] = $status;
            $result = $trigger->auto('device.sign.saved', $payload, true);
            same(1, count(Store::$prints), '签收兜底复用自动任务状态' . $status);
            same(true, !empty($result['results'][0]['skipped']), '复用任务返回明确跳过状态' . $status);
        }
        foreach ([RecyclePrintTask::STATUS_FAIL, RecyclePrintTask::STATUS_CANCELLED] as $status) {
            fixture(); Store::$scope = 'site_scene_device'; $trigger = new RecyclePrintTriggerService(); $trigger->auto('device.sign.saved', $payload);
            $taskId = tasks()[0]['task_id']; Store::$tables[RecyclePrintTask::class][$taskId]['status'] = $status;
            $trigger->auto('device.sign.saved', $payload, true);
            same(2, count(Store::$prints), '失败或取消任务允许签收重试' . $status);
            same(1, count(tasks()), '稳定幂等场景重试沿用既有任务' . $status);
        }
        fixture(); $trigger = new RecyclePrintTriggerService(); $trigger->auto('device.sign.saved', $payload);
        $taskId = tasks()[0]['task_id']; Store::$tables[RecyclePrintTask::class][$taskId]['site_id'] = 100006;
        $trigger->auto('device.sign.saved', $payload, true);
        same(2, count(Store::$prints), '其他站点自动任务不误拦本站打印');
        fixture(); $trigger = new RecyclePrintTriggerService(); $trigger->auto('device.sign.saved', $payload);
        $taskId = tasks()[0]['task_id']; Store::$tables[RecyclePrintTask::class][$taskId]['mode'] = RecyclePrintTask::MODE_MANUAL;
        $trigger->auto('device.sign.saved', $payload, true);
        same(2, count(Store::$prints), '历史手动打印不误当签收自动任务');
        foreach (['scene_key' => 'other_scene', 'trigger_key' => 'device.check.saved', 'biz_type' => 'order', 'biz_id' => 102, 'device_id' => 102] as $field => $value) {
            fixture(); $trigger = new RecyclePrintTriggerService(); $trigger->auto('device.sign.saved', $payload);
            $taskId = tasks()[0]['task_id']; Store::$tables[RecyclePrintTask::class][$taskId][$field] = $value;
            $trigger->auto('device.sign.saved', $payload, true);
            same(2, count(Store::$prints), '兜底复用不误命中不同任务维度' . $field);
        }
        fixture(); Store::$failPrintFor = [101]; $trigger = new RecyclePrintTriggerService(); $trigger->auto('device.sign.saved', $payload);
        Store::$failPrintFor = []; $trigger->auto('device.sign.saved', $payload, true);
        same(2, count(Store::$prints), 'scope=none失败打印仍可兜底重试');
        same([RecyclePrintTask::STATUS_FAIL, RecyclePrintTask::STATUS_SUCCESS], array_column(tasks(), 'status'), 'scope=none保留失败记录并记录本次成功');

        fixture(); Store::$throwPlanFor = [101];
        same(true, signService()->sign(90, ['devices' => [deviceInput(101), deviceInput(102)]]), '单台打印异常不阻断整单签收');
        same([102], array_column(array_column(Store::$prints, 'device'), 'device_id'), '第一台打印失败不阻止后续设备打印');
        same(2, Store::$tables[RecycleOrder::class][90]['status'], '打印失败后订单签收状态已提交');
        fixture(); CoreRecycleOrderFlowService::$fail = true;
        denied(fn() => signService()->sign(90, ['devices' => [deviceInput(101)]]), '签收流程失败向上报告');
        same([], Store::$prints, '签收流程失败不触发兜底打印');
        fixture();
        denied(fn() => signService()->sign(91, ['devices' => [deviceInput(201)]]), '独立签收入口也拒绝其他站点订单');
        same([], Store::$prints, '跨站订单拒绝发生在任何打印前');
        fixture(); Store::$auto = false;
        same(true, signService()->sign(90, ['devices' => [deviceInput(101)]]), '最终签收不受自动打印关闭影响');
        same([], Store::$prints, '最终签收仍遵循场景自动打印开关');

        fixture(); CoreRecycleOrderFlowService::$forcedIds = [101, 201, 202, 101, 0];
        same(true, signService()->sign(90, ['devices' => [deviceInput(101)]]), '签收成功后过滤异常设备集合');
        same([101], array_column(array_column(Store::$prints, 'device'), 'device_id'), '最终兜底只打印同站同订单有效ID且去重');

        fixture(); $trigger = new RecyclePrintTriggerService(); $trigger->auto('device.sign.saved', $payload);
        $lockIndex = $createIndex = $commitIndex = $printIndex = null;
        foreach (Store::$trace as $index => $entry) {
            if ($entry[0] === 'lock' && $entry[1] === RecycleDevice::class) $lockIndex = $index;
            if ($entry[0] === 'save' && $entry[1] === RecyclePrintTask::class) $createIndex = $index;
            if ($entry[0] === 'commit' && in_array(RecyclePrintTask::STATUS_PENDING, $entry[2] ?? [], true)) $commitIndex = $index;
            if ($entry[0] === 'print') $printIndex = $index;
        }
        same(true, $lockIndex !== null && $createIndex !== null && $lockIndex < $createIndex, '签收自动任务先锁本站设备再创建任务');
        same(true, $createIndex !== null && $commitIndex !== null && $createIndex < $commitIndex, 'pending任务在设备锁事务内落库');
        same(true, $commitIndex !== null && $printIndex !== null && $commitIndex < $printIndex, 'pending提交释放锁后才调用打印提供商');
        same(0, Store::$depth, '提供商调用完成不残留设备锁事务');

        fixture(); Store::$lockFails = true;
        denied(fn() => (new RecyclePrintTriggerService())->auto('device.sign.saved', $payload), '设备锁获取失败阻止自动打印');
        same([], Store::$prints, '设备锁失败不调用打印提供商');
        same([], tasks(), '设备锁失败不遗留半创建任务');
        same(0, Store::$depth, '设备锁失败释放事务');
        fixture();
        denied(fn() => (new RecyclePrintTriggerService())->auto('device.sign.saved', ['device_id' => 201, 'order_id' => 91, 'biz_id' => 201]), '自动任务创建锁定时拒绝非本站设备');
        same([], Store::$prints, '非本站设备不调用打印提供商');

        fixture();
        $plan = (new \addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService())->resolvePlansByTrigger('device.sign.saved', $payload, false)[0];
        (new \addon\hsx_recycle\app\service\admin\printer\RecyclePrintTaskService())->createFromPlan($plan, $payload, RecyclePrintTask::MODE_MANUAL, true);
        same([], array_values(array_filter(Store::$trace, fn($entry) => $entry[0] === 'lock')), '普通手动任务不强加签收自动打印设备锁');
        same(0, Store::$depth, '手动任务原创建过程不附加事务');
        fixture(); (new RecyclePrintTriggerService())->auto('device.check.saved', $payload);
        same([], array_values(array_filter(Store::$trace, fn($entry) => $entry[0] === 'lock')), '非签收自动事件沿用原任务创建方式');
        echo 'PASS ' . $checks . ' sign-save/autoprint checks; isolated in-memory, no live DB/network/printing' . PHP_EOL;
    } catch (\Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL); exit(1);
    }
}
