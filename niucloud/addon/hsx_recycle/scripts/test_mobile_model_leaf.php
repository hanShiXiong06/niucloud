<?php
declare(strict_types=1);

// 执行真实管理控制器和签收处理器；数据库、请求和型号服务使用内存替身。
// 不加载 .env、不连接数据库、不调用打印或其他外部服务。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
}

namespace MobileModelLeafFixture {
    final class Store {
        public static array $nodes = [], $children = [], $options = [], $devices = [], $added = [], $updated = [], $selected = [];
        public static int $nextId = 100;
    }
    final class Request {
        public string $channel;
        public array $input;
        public function __construct(string $channel, array $input = []) { $this->channel = $channel; $this->input = $input; }
        public function getChannel(): string { return $this->channel; }
        public function params(array $defaults): array {
            $result = [];
            foreach ($defaults as [$key, $default]) $result[$key] = $this->input[$key] ?? $default;
            return $result;
        }
    }
    final class DeviceQuery {
        private array $conditions;
        public function __construct(array $conditions) { $this->conditions = $conditions; }
        public function column(string $field, string $key): array {
            $result = [];
            foreach (Store::$devices as $row) {
                foreach ($this->conditions as [$name, $operator, $expected]) {
                    if ($operator !== '=' || ($row[$name] ?? null) != $expected) continue 2;
                }
                $result[$row[$key]] = $field === '*' ? $row : $row[$field];
            }
            return $result;
        }
    }
}

namespace core\base { class BaseAdminController { public $request; } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace addon\hsx_recycle\app\dict\order {
    final class RecycleOrderDict { public const DEVICE_STATUS_PENDING_CHECK = 1; }
}
namespace addon\hsx_recycle\app\model\order {
    final class RecycleDevice {
        public function where(array $conditions): \MobileModelLeafFixture\DeviceQuery {
            return new \MobileModelLeafFixture\DeviceQuery($conditions);
        }
    }
}
namespace addon\hsx_recycle\app\service\admin\device {
    final class RecycleDeviceModelDictService {
        public function options(array $where = []): array { return \MobileModelLeafFixture\Store::$options; }
        public function children(array $where = []): array { return \MobileModelLeafFixture\Store::$children; }
        public function selectableLeaf(int $id): array {
            \MobileModelLeafFixture\Store::$selected[] = $id;
            $node = \MobileModelLeafFixture\Store::$nodes[$id] ?? [];
            if ($node === [] || !empty($node['has_children'])) throw new \core\exception\CommonException('请选择有效的具体型号');
            return $node;
        }
        public function ensureFromModelName(string $name, int $siteId = 0): void {}
    }
}
namespace addon\hsx_recycle\app\service\admin\order {
    final class RecycleDeviceService {
        public function add(array $data): int {
            $id = ++\MobileModelLeafFixture\Store::$nextId;
            \MobileModelLeafFixture\Store::$added[$id] = $data;
            \MobileModelLeafFixture\Store::$devices[$id] = array_replace($data, ['id' => $id]);
            return $id;
        }
        public function signUpdate(int $id, array $data): bool {
            \MobileModelLeafFixture\Store::$updated[$id] = $data;
            \MobileModelLeafFixture\Store::$devices[$id] = array_replace(\MobileModelLeafFixture\Store::$devices[$id], $data);
            return true;
        }
    }
}

namespace {
    use MobileModelLeafFixture\Store;
    use MobileModelLeafFixture\Request;
    use addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict;
    use addon\hsx_recycle\app\service\core\recycle_order\handler\SignHandler;

    function success($data = null): array { return ['code' => 1, 'data' => $data]; }
    $addonRoot = dirname(__DIR__);
    spl_autoload_register(static function (string $class) use ($addonRoot): void {
        $prefix = 'addon\\hsx_recycle\\';
        if (strpos($class, $prefix) !== 0) return;
        $file = $addonRoot . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    });
    $count = 0;
    $check = static function (bool $condition, string $message) use (&$count): void {
        if (!$condition) throw new \RuntimeException('断言失败：' . $message);
        $count++;
    };
    $equal = static function ($actual, $expected, string $message) use ($check): void {
        $check($actual === $expected, $message . '；实际=' . json_encode($actual, JSON_UNESCAPED_UNICODE));
    };
    $leaf = ['id' => 4, 'pid' => 3, 'node_name' => '苹果 iPhone 14 Pro', 'model_full_name' => '手机/苹果/iPhone 14系列/苹果 iPhone 14 Pro',
        'category_path' => [1, 2, 3, 4], 'leaf' => true, 'has_children' => 0, 'is_leaf' => 1, 'child_list' => []];
    $other = ['id' => 8, 'pid' => 7, 'node_name' => '小米 17 Pro', 'model_full_name' => '手机/小米/小米17系列/小米 17 Pro',
        'category_path' => [1, 6, 7, 8], 'leaf' => true, 'has_children' => 0, 'is_leaf' => 1, 'child_list' => []];
    $parent = ['id' => 3, 'pid' => 2, 'node_name' => 'iPhone 14系列', 'model_full_name' => '手机/苹果/iPhone 14系列',
        'category_path' => [1, 2, 3], 'leaf' => false, 'has_children' => 1, 'is_leaf' => 0, 'child_list' => []];
    Store::$nodes = [3 => $parent, 4 => $leaf, 8 => $other];
    Store::$children = [$parent, $leaf, $other];
    Store::$options = [$leaf, $other];
    $originalChildren = Store::$children;
    $originalOptions = Store::$options;
    $controllerReflection = new \ReflectionClass(RecycleDeviceModelDict::class);
    $controller = $controllerReflection->newInstanceWithoutConstructor();
    $serviceProperty = $controllerReflection->getProperty('service');
    $serviceProperty->setAccessible(true);
    $serviceProperty->setValue($controller, new \addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService());

    foreach (['children', 'options'] as $method) {
        $controller->request = new Request('weapp', ['pid' => 3, 'keyword' => 'iPhone']);
        $mobile = $controller->$method()['data'];
        $byId = array_column($mobile, null, 'id');
        $equal($byId[4]['model_full_name'], $leaf['node_name'], $method . '：旧小程序选择后即得到末级名称');
        $equal($byId[8]['model_full_name'], $other['node_name'], $method . '：不同型号分别取自己的末级名称');
        $equal($byId[4]['model_path'], $leaf['model_full_name'], $method . '：保留完整原路径');
        $equal($byId[4]['id'], 4, $method . '：分类ID不变');
        $equal($byId[4]['category_path'], [1, 2, 3, 4], $method . '：分类ID路径不变');
        $equal($byId[4]['node_name'], $leaf['node_name'], $method . '：字典末级节点不改写');
        if ($method === 'children') {
            $equal($byId[3]['model_full_name'], $parent['model_full_name'], '父节点完整路径保持不变');
            $equal($byId[3]['has_children'], 1, '父节点仍可继续进入');
        }
        foreach (['h5', 'wechat', ''] as $channel) {
            $controller->request = new Request($channel);
            $equal($controller->$method()['data'], $method === 'children' ? $originalChildren : $originalOptions, $method . '：非管理小程序结果不变 ' . $channel);
        }
        $equal(Store::$children, $originalChildren, '缓存外适配不污染children原结果');
        $equal(Store::$options, $originalOptions, '缓存外适配不污染options原结果');
    }

    Store::$options = [
        ['id' => 30, 'model_full_name' => ' 手机 ／ 苹果 ／ 苹果 iPhone 14 Pro ／ ', 'node_name' => ''],
        ['id' => 31, 'model_full_name' => '', 'node_name' => ''],
    ];
    $controller->request = new Request('weapp');
    $fallback = $controller->options()['data'];
    $equal($fallback[0]['model_full_name'], '苹果 iPhone 14 Pro', '缺节点名称时兼容全角斜杠和尾分隔符');
    $equal($fallback[0]['model_path'], Store::$options[0]['model_full_name'], '兜底提取也保留完整原路径');
    $equal($fallback[1], Store::$options[1], '空名称不合成错误占位值');
    Store::$children = [['id' => 32, 'model_full_name' => '手机/未知层级', 'node_name' => '未知层级']];
    $equal($controller->children()['data'], Store::$children, '未明确是叶子时不猜测改名');
    Store::$options = $originalOptions;
    Store::$children = $originalChildren;

    Store::$devices = [
        21 => ['id' => 21, 'order_id' => 12, 'site_id' => 100005, 'member_id' => 66, 'model' => $leaf['model_full_name'],
            'category_id' => 4, 'check_template_id' => 501, 'info' => ['operator_note' => '保留人工信息', 'sign_summary' => ['old' => '旧值']]],
        22 => ['id' => 22, 'order_id' => 99, 'site_id' => 100006, 'member_id' => 77, 'model' => '其他站设备'],
    ];
    $devices = [
        ['id' => 21, 'imei' => '353938205192374', 'imei2' => '353938205192375', 'serial_number' => 'ABC12345',
            'model' => $leaf['model_full_name'], 'category_id' => 4, 'category_path' => [1, 2, 3, 4], 'check_template_id' => 501,
            'initial_price' => '2100.00', 'summary' => ['capacity' => '256GB', 'color' => '紫色', 'battery' => 95]],
        ['imei' => '863963080113124', 'imei2' => '', 'serial_number' => 'XM123456',
            'model' => '客户端错误型号', 'category_id' => 8, 'category_path' => [1, 6, 7, 8], 'check_template_id' => 502,
            'initial_price' => '2900.00', 'summary' => ['capacity' => '12G+256G', 'color' => '黑色', 'battery' => 99]],
    ];
    $originalInput = $devices;
    $result = (new SignHandler())->handle(['id' => 12, 'member_id' => 66], ['devices' => $devices, 'remark' => '签收备注'], ['site_id' => 100005, 'operator_id' => 7]);
    $equal($result['success'], true, '实际签收处理器执行成功');
    $equal($result['data']['device_ids'], [21, 101], '更新和新建的设备ID正确返回');
    $equal($result['data']['sign_info']['device_count'], 2, '多台签收数量不变');
    $equal($result['data']['sign_info']['remark'], '签收备注', '签收备注不变');
    $equal(Store::$selected, [4, 8], '每台设备独立读取可信叶子型号');
    $saved = [Store::$updated[21], Store::$added[101]];
    foreach ($saved as $index => $row) {
        $input = $originalInput[$index];
        $expectedNode = $index === 0 ? $leaf : $other;
        $equal($row['model'], $expectedNode['node_name'], '设备' . $index . '由可信分类决定短型号');
        foreach (['imei', 'imei2', 'category_id', 'initial_price', 'check_template_id'] as $key) {
            $equal($row[$key], $input[$key], '设备' . $index . '保留' . $key);
        }
        $equal($row['sn'], $input['serial_number'], '设备' . $index . '保留SN');
        $equal($row['site_id'], 100005, '设备' . $index . '站点不变');
        $equal($row['info']['goods_category'], array_map('strval', $input['category_path']), '设备' . $index . '保留分类ID路径');
        $equal($row['info']['sign_summary'], $input['summary'], '设备' . $index . '保留摘要选项');
        $equal($row['info']['check_meta']['battery'], $input['summary']['battery'], '设备' . $index . '保留电池');
        $equal($row['capacity'], $input['summary']['capacity'], '设备' . $index . '保留容量');
        $equal($row['color'], $input['summary']['color'], '设备' . $index . '保留颜色');
    }
    $equal(Store::$devices[21]['info']['operator_note'], '保留人工信息', '更新不删除原有人工信息');
    $equal(Store::$devices[22]['model'], '其他站设备', '不修改其他订单/站点设备');
    $equal(Store::$added[101]['member_id'], 66, '新设备客户归属不变');
    $equal(Store::$added[101]['order_id'], 12, '新设备订单归属不变');
    $equal($devices, $originalInput, '原始请求数组不被副作用改写');

    $manual = ['imei' => 'SNABCDE1', 'model' => '手工型号 A/B', 'category_id' => 0, 'initial_price' => 0];
    (new SignHandler())->handle(['id' => 12, 'member_id' => 66], ['devices' => [$manual]], ['site_id' => 100005]);
    $equal(Store::$added[102]['model'], '手工型号 A/B', '无分类的人工型号不按斜杠错误截断');
    $equal(Store::$selected, [4, 8], '手工型号不查询或创建字典叶子');
    $beforeInvalid = Store::$devices;
    try {
        (new SignHandler())->handle(['id' => 12, 'member_id' => 66], ['devices' => [array_replace($manual, ['category_id' => 3])]], ['site_id' => 100005]);
        $check(false, '父分类不能保存为设备型号');
    } catch (\core\exception\CommonException $exception) {
        $check(true, '可信型号服务拒绝父分类的异常不被吞掉');
    }
    $equal(Store::$devices, $beforeInvalid, '无效型号不写入新设备');
    echo '[通过] ' . $count . " 项移动型号末级名称隔离回归；不连接数据库、不打印、不修改历史数据。\n";
}
