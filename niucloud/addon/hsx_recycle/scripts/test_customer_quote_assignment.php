<?php
declare(strict_types=1);
// 内存回归：运行真实报价服务和任务分配算法，不连接数据库，不发送通知。
namespace core\exception { class CommonException extends \RuntimeException {} class AdminException extends \RuntimeException {} }
namespace core\base { class BaseAdminService { protected $site_id = 100005; protected $uid = 1; protected $username = '测试'; } }
namespace quote_test {
    class Query {
        private array $rows;
        public function __construct(array $rows) { $this->rows = array_values(array_filter($rows, fn($r) => empty($r['delete_time']))); }
        public function where($field, $op = null, $value = null) {
            foreach (is_array($field) ? $field : [[$field, $op, $value]] as [$key, $operator, $wanted]) {
                $this->rows = array_values(array_filter($this->rows, fn($r) => $operator === 'in' ? in_array($r[$key] ?? null, $wanted) : ($r[$key] ?? null) == $wanted));
            }
            return $this;
        }
        public function whereIn($field, $values) { return $this->where($field, 'in', $values); }
        public function field($fields) { return $this; }
        public function order($order) { return $this; }
        public function select() { return $this; }
        public function toArray() { return $this->rows; }
        public function column($field) { return array_column($this->rows, $field); }
        public function findOrEmpty() { return new Record($this->rows[0] ?? []); }
    }
    class Record {
        private array $row;
        public function __construct(array $row) { $this->row = $row; }
        public function __get($name) { return $this->row[$name] ?? null; }
        public function __isset($name) { return isset($this->row[$name]); }
        public function isEmpty() { return !$this->row; }
    }
    class Model {
        public static array $rows = [];
        public static function where(...$args) { return (new Query(static::$rows))->where(...$args); }
        public static function whereIn(...$args) { return (new Query(static::$rows))->whereIn(...$args); }
    }
}
namespace app\model\sys {
    class SysUser extends \quote_test\Model { public static array $rows = []; }
    class SysUserRole extends \quote_test\Model { public static array $rows = []; }
    class SysRole extends \quote_test\Model { public static array $rows = []; }
}
namespace app\service\core\sys { class CoreConfigService { public function getConfigValue($site, $key) { return ['price' => 12]; } } }
namespace addon\hsx_recycle\app\service\core\order {
    class OrderSubmitConfigService { public static array $config = []; public function getConfig($site) { return self::$config; } }
}
namespace addon\hsx_recycle\app\model\order { class RecycleDevice extends \quote_test\Model { public static array $rows = []; } }
namespace addon\hsx_recycle\app\model\stat { class RecycleTaskClaim extends \quote_test\Model { public static array $rows = []; } }
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    $root = dirname(__DIR__) . '/app/';
    require $root . 'dict/order/RecycleOrderDict.php';
    require $root . 'dict/stat/RecycleStageDict.php';
    require $root . 'service/admin/stat/TaskService.php';
    require $root . 'service/core/order/CustomerQuoteService.php';
    use addon\hsx_recycle\app\service\core\order\CustomerQuoteService as Quote;
    use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService as Config;
    use addon\hsx_recycle\app\service\admin\stat\TaskService;
    use addon\hsx_recycle\app\model\order\RecycleDevice;
    use addon\hsx_recycle\app\dict\order\RecycleOrderDict as Status;
    use app\model\sys\SysUser;
    use app\model\sys\SysUserRole;
    use app\model\sys\SysRole;
    $count = 0;
    function check($condition, $name) { global $count; if (!$condition) throw new \RuntimeException($name); $count++; }
    function reject(callable $fn, $name) { try { $fn(); } catch (\core\exception\CommonException | \core\exception\AdminException $e) { check(true, $name); return; } throw new \RuntimeException('未拦截：' . $name); }
    $controller = file_get_contents($root . 'adminapi/controller/order/RecycleDevice.php');
    check(strpos($controller, "\$payload['action'], (int)(\$payload['next_assignee_uid'] ?? 0)") !== false, '通用设备更新接口传递下一定价负责人');
    SysRole::$rows = [['site_id' => 100005, 'status' => 1, 'role_id' => 8, 'rules' => ['recycle_device_confirm_price']]];
    foreach ([11, 12, 13, 14, 15, 16] as $uid) {
        SysUser::$rows[] = ['uid' => $uid, 'status' => $uid === 14 ? 0 : 1, 'real_name' => '员工' . $uid, 'username' => 'u' . $uid, 'delete_time' => $uid === 16 ? 1 : 0];
        SysUserRole::$rows[] = ['uid' => $uid, 'site_id' => $uid === 15 ? 999 : 100005, 'status' => 1, 'is_admin' => 0, 'role_ids' => $uid === 13 ? [] : [8]];
    }
    SysUser::$rows[] = ['uid' => 1, 'status' => 1, 'real_name' => '站点管理员', 'username' => 'admin'];
    SysUserRole::$rows[] = ['uid' => 1, 'site_id' => 100005, 'status' => 1, 'is_admin' => 1, 'role_ids' => []];
    Config::$config = ['device_add_enabled' => 1, 'device_pricing' => ['enabled' => 1, 'assign_to_quote_user' => 1, 'quote_user_ids' => [11, 12, 1]]];
    $service = new Quote();
    $tasks = TaskService::forSite(100005);
    check(array_column($tasks->getAssignableUsers('price'), 'uid') === [11, 12], '原自动分配候选不扩大');
    check(array_column($service->getQuoteUsers(100005, [11, 12, 13, 14, 15, 16, 1]), 'uid') === [11, 12, 1], '筛除跨站、停用、删除、无权限；可明确选择本站管理员');
    $service->validateConfig(100005, Config::$config);
    $badConfig = Config::$config; $badConfig['device_pricing']['quote_user_ids'] = [13];
    reject(fn() => $service->validateConfig(100005, $badConfig), '配置不自动给员工授权');
    foreach (['', '0', '6000.25'] as $price) {
        $row = $service->prepareDevices(100005, [['initial_price' => $price, 'initial_price_uid' => 11, 'initial_price_name' => '伪造']], Config::$config)[0];
        check($price === '' ? $row['customer_quote'] === [] : $row['customer_quote']['name'] === '员工11', '空价无需人，有价员工姓名可信');
    }
    foreach (['-1', 'abc', '1.234', '100000000', [], null] as $price) {
        if ($price === null) continue; // null 与未填写一致。
        reject(fn() => $service->prepareDevices(100005, [['initial_price' => $price]], Config::$config), '非法价格');
    }
    foreach ([0, 13, 14, 15, 16, '11x'] as $uid) reject(fn() => $service->prepareDevices(100005, [['initial_price' => '6000', 'initial_price_uid' => $uid]], Config::$config), '非法人员或未选人');
    $rows = $service->prepareDevices(100005, [['initial_price' => '6000', 'initial_price_uid' => 11], ['initial_price' => '5000', 'initial_price_uid' => 12]], Config::$config);
    check(array_column(array_column($rows, 'customer_quote'), 'uid') === [11, 12], '每台独立快照不串人');
    $info = ['customer_quote' => $rows[0]['customer_quote'], 'sign_summary' => ['battery' => 98]];
    check($tasks->resolvePricingAssignee($info, 12) === 11, '开启后报价员优先于表单下一负责人');
    check($tasks->resolvePricingAssignee([], 12) === 0, '未选人沿用原分配');
    Config::$config['device_pricing']['assign_to_quote_user'] = 0;
    check($tasks->resolvePricingAssignee($info, 12) === 0, '关闭分支沿用原分配');
    $adminInfo = $service->applyAdminQuote(100005, $info, ['initial_price_uid' => 13, 'initial_price' => 6100]);
    check($adminInfo['customer_quote']['uid'] === 13 && $adminInfo['customer_quote']['assign_to_quote_user'] === 0, '关闭时后台可记录普通员工报价来源');
    check($service->applyAdminQuote(100005, $info, ['initial_price_uid' => 0])['customer_quote'] === [], '管理员可留空或清除');
    check($service->applyAdminQuote(100005, $info, []) === $info, '未提交人员字段不清空客户选择');
    $disabled = $service->prepareDevices(100005, [['initial_price' => '6000', 'initial_price_uid' => 11]], Config::$config)[0];
    check($disabled['customer_quote']['uid'] === 0, '关闭不接收客户端指定人');
    Config::$config['device_pricing']['assign_to_quote_user'] = 1;
    reject(fn() => $service->applyAdminQuote(100005, [], ['initial_price_uid' => 13]), '开启后台也不能指定无定价权限人员');
    reject(fn() => $service->applyAdminQuote(100005, [], ['initial_price_uid' => 15]), '后台不能选其他站点人员');
    SysUser::$rows[0]['status'] = 0;
    check($service->pricingAssignment(100005, $info)['mode'] === 'unavailable', '停用明确返回不可交接');
    reject(fn() => $tasks->resolvePricingAssignee($info), '不静默转给默认人');
    check($tasks->resolvePricingAssignee($info, 12) === 12, '管理员明确指定有效替代人后可继续');
    SysUser::$rows[0]['status'] = 1;
    class SpyTasks extends TaskService {
        public array $writes = [];
        protected function upsertAssignment(int $id, string $stage, int $uid, string $name, string $mode, bool $transfer): bool {
            $this->writes[] = [$id, $stage, $uid, $name]; return true;
        }
    }
    RecycleDevice::$rows = [
        ['id' => 101, 'site_id' => 100005, 'status' => Status::DEVICE_STATUS_CHECKED, 'info' => $info],
        ['id' => 102, 'site_id' => 100005, 'status' => Status::DEVICE_STATUS_CHECKED, 'info' => ['customer_quote' => $rows[1]['customer_quote']]],
    ];
    $spy = new SpyTasks();
    $spy->assignPreferredOrDefault(101, 'price', 12); $spy->assignPreferredOrDefault(102, 'price', 11);
    check(array_column($spy->writes, 2) === [11, 12], '真实自动分配入口按设备交接');
    Config::$config['device_pricing']['assign_to_quote_user'] = 0;
    $spy->assignPreferredOrDefault(101, 'price', 12);
    check($spy->writes[2][2] === 12, '关闭后真实入口尊重业务员指定');
    Config::$config['device_add_enabled'] = 0;
    reject(fn() => $service->prepareDevices(100005, [['initial_price' => 1]], Config::$config), '关闭添加设备不接受旧页面载荷');
    Config::$config['device_add_enabled'] = 1; Config::$config['device_pricing']['enabled'] = 0;
    $row = $service->prepareDevices(100005, [['initial_price' => 999, 'initial_price_uid' => 11]], Config::$config)[0];
    check($row['initial_price'] === '0.00' && $row['customer_quote'] === [], '关闭预估价不接受绕过前端的数据');
    echo "PASS {$count} quote/assignment checks; no database, network or notifications\n";
}
