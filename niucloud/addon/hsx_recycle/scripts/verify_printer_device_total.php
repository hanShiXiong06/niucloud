<?php
declare(strict_types=1);

// 真实打印取值/变量替换/场景覆盖逻辑；仅使用 SQLite :memory:。
// 不启动应用、不读取 .env、不连接业务库、不调用打印机或外部服务。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
}
namespace core\base {
    class BaseAdminService { public int $site_id = 100005; public function __construct() {} }
    class BaseModel extends \think\Model {}
}
namespace hsx_recycle\printer_total_fixture {
    class User extends \think\Model { protected $name = 'sys_user'; protected $pk = 'uid'; }
}
namespace addon\hsx_recycle\app\model\order {
    class RecycleDevice extends \think\Model {
        protected $name = 'recycle_device';
        protected $json = ['info'];
        public function priceUser() { return $this->belongsTo(\hsx_recycle\printer_total_fixture\User::class, 'price_uid', 'uid')->field('uid,username,real_name'); }
        public function checkUser() { return $this->belongsTo(\hsx_recycle\printer_total_fixture\User::class, 'check_uid', 'uid')->field('uid,username,real_name'); }
    }
    class RecycleOrder extends \think\Model { protected $name = 'recycle_order'; }
}
namespace {
    use addon\hsx_recycle\app\service\admin\order\RecycleOrderDeviceService;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrinterTemplateService;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService;
    use addon\hsx_recycle\app\service\admin\printer\template\VariableReplaceService;
    use think\facade\Db;

    function request() { return new class { public function domain() { return 'https://printer-total.invalid'; } }; }
    function same($expected, $actual, string $message): void {
        if ($expected !== $actual) throw new \RuntimeException($message . ': expected=' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', actual=' . json_encode($actual, JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
    }
    function callPrivate(object $object, string $method, array $arguments = []) {
        $reflection = new \ReflectionMethod($object, $method); $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $arguments);
    }
    function bare(string $class): object { return (new \ReflectionClass($class))->newInstanceWithoutConstructor(); }
    function seedDevice(int $id, int $orderId, int $siteId = 100005): void {
        Db::name('recycle_device')->insert(['id' => $id, 'site_id' => $siteId, 'order_id' => $orderId, 'imei' => '35390000000' . str_pad((string)$id, 4, '0', STR_PAD_LEFT)]);
    }
    function snapshot(): array {
        return [Db::name('recycle_order')->order('id')->select()->toArray(), Db::name('recycle_device')->order('id')->select()->toArray()];
    }
    function position(RecyclePrinterTemplateService $printer, int $id, int $index, int $total, string $message): array {
        $before = snapshot();
        $data = $printer->getDevicePrintData($id);
        same((string)$index, $data['device_index'], $message . '序号');
        same((string)$total, $data['device_total'], $message . '总台数');
        same($index . '/' . $total, $data['device_number'], $message . '组合编号');
        $replace = new VariableReplaceService();
        same($index . '/' . $total, $replace->replaceVariables('{{device_index}}/{{device_total}}', $data), $message . '真实模板分项变量');
        same($index . '/' . $total, $replace->replaceVariables('{{device_number}}', $data), $message . '真实模板完整变量');
        same($before, snapshot(), $message . '读取无业务写入');
        return $data;
    }

    $checks = 0;
    try {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) throw new \RuntimeException('需要 PDO SQLite 驱动，仅用于隔离回归');
        Db::setConfig(['default' => 'isolated', 'auto_timestamp' => false, 'connections' => ['isolated' => [
            'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'ut_', 'fields_strict' => true,
            'params' => [\PDO::ATTR_STRINGIFY_FETCHES => true],
        ]]]);
        Db::execute('CREATE TABLE ut_sys_user (uid INTEGER PRIMARY KEY, username TEXT, real_name TEXT)');
        Db::execute('CREATE TABLE ut_recycle_order (id INTEGER PRIMARY KEY, site_id INTEGER, count INTEGER DEFAULT 0, device_count INTEGER DEFAULT 0, member_id INTEGER DEFAULT 0, status INTEGER DEFAULT 1, order_no TEXT DEFAULT "FIXTURE", update_at INTEGER DEFAULT 0)');
        Db::execute('CREATE TABLE ut_recycle_device (' . implode(', ', [
            'id INTEGER PRIMARY KEY', 'site_id INTEGER', 'order_id INTEGER DEFAULT 0',
            'check_uid INTEGER DEFAULT 0', 'price_uid INTEGER DEFAULT 0', 'category_id INTEGER DEFAULT 0',
            'check_template_id INTEGER DEFAULT 0', 'capacity TEXT DEFAULT "256GB"', 'color TEXT DEFAULT "银色"',
            'info TEXT DEFAULT "{}"', 'model TEXT DEFAULT "苹果 iPhone Air"', 'imei TEXT DEFAULT ""',
            'initial_price DECIMAL DEFAULT 0', 'final_price DECIMAL DEFAULT 0', 'sell_price DECIMAL DEFAULT 0',
            'final_status INTEGER DEFAULT 0', 'check_at INTEGER DEFAULT 0', 'price_at INTEGER DEFAULT 0',
            'create_at INTEGER DEFAULT 0', 'update_at INTEGER DEFAULT 0',
        ]) . ')');
        Db::name('recycle_order')->insertAll([
            ['id' => 100, 'site_id' => 100005, 'count' => 10],
            ['id' => 200, 'site_id' => 100005, 'count' => 2],
            ['id' => 300, 'site_id' => 100005, 'count' => 0],
            ['id' => 400, 'site_id' => 100006, 'count' => 77],
        ]);
        $printer = bare(RecyclePrinterTemplateService::class);
        $orderDevice = bare(RecycleOrderDeviceService::class);
        $scene = bare(RecyclePrintSceneService::class);
        $property = new \ReflectionProperty($scene, 'templateService'); $property->setAccessible(true); $property->setValue($scene, $printer);

        foreach ([10, 20, 30, 40, 50, 60, 70, 80, 90, 100] as $offset => $id) {
            seedDevice($id, 100);
            if ($offset === 0) same(['10'], Db::name('recycle_device')->where('order_id', 100)->column('id'), '数据库驱动返回字符串ID时也使用真实序号');
            callPrivate($orderDevice, 'syncOrderDeviceCount', [100]);
            same(10, (int)Db::name('recycle_order')->where('id', 100)->value('count'), '逐台保存第' . ($offset + 1) . '台不覆盖已确认总数');
            same($offset + 1, (int)Db::name('recycle_order')->where('id', 100)->value('device_count'), '实际录入进度独立增加');
            if (in_array($offset + 1, [1, 2, 5, 10], true)) position($printer, $id, $offset + 1, 10, '已录' . ($offset + 1) . '台时');
        }
        position($printer, 10, 1, 10, '整单录完后首台');
        position($printer, 50, 5, 10, '整单录完后中间台');
        position($printer, 100, 10, 10, '整单录完后末台');

        seedDevice(11, 100, 100006); // 同 order_id 的外站设备不能改变本站总数、序号。
        seedDevice(15, 200);
        position($printer, 20, 2, 10, '外站/其他订单插入穿插ID后');
        $outerBefore = Db::name('recycle_order')->where('id', 400)->find();
        callPrivate($orderDevice, 'syncOrderDeviceCount', [100]);
        same($outerBefore, Db::name('recycle_order')->where('id', 400)->find(), '同步数量不更新其他站点订单');
        try { $printer->getDevicePrintData(11); throw new \RuntimeException('外站设备未拒绝'); }
        catch (\core\exception\AdminException $error) { same('设备不存在', $error->getMessage(), '不允许打印外站设备'); }

        seedDevice(201, 200); seedDevice(202, 200);
        position($printer, 202, 3, 3, '实际超出申报总台数时');
        callPrivate($orderDevice, 'syncOrderDeviceCount', [200]);
        same(3, (int)Db::name('recycle_order')->where('id', 200)->value('count'), '实际数量超出旧申报时总数向上补足');
        same(3, (int)Db::name('recycle_order')->where('id', 200)->value('device_count'), '实际数量超出旧申报时进度仍是实际值');
        seedDevice(301, 300); seedDevice(302, 300);
        position($printer, 302, 2, 2, '未提供总数的既有草稿');
        seedDevice(501, 0);
        position($printer, 501, 1, 1, '无订单独立设备');

        $biz = callPrivate($scene, 'resolveBizPrintData', ['device', ['device_id' => 50]]);
        same('5/10', $biz['device']['device_number'], '场景计划设备摘要与模板一致');
        same('10', $biz['print_data']['device_total'], '场景计划总台数正确');
        $overridden = callPrivate($scene, 'applyPrintDataOverride', [$biz, ['device_index' => '999', 'device_total' => '999', 'device_number' => '999/999', 'color' => '蓝色']]);
        same('5', $overridden['print_data']['device_index'], '临时编辑不能覆盖设备序号');
        same('10', $overridden['print_data']['device_total'], '临时编辑不能覆盖订单总台数');
        same('5/10', $overridden['print_data']['device_number'], '临时编辑不能覆盖完整设备编号');
        same('蓝色', $overridden['print_data']['color'], '合法临时文字仍能修改');
        $labelEdit = callPrivate($scene, 'resolveLabelEditConfig', [
            ['biz_type' => 'device', 'button_config' => ['label_edit' => ['enabled' => 1]]],
            ['width' => 50, 'instruction_content' => '{{device_index}}/{{device_total}} {{device_number}} {{color}}'], $biz['print_data'],
        ]);
        $editableKeys = array_column($labelEdit['fields'], 'key');
        foreach (['device_index', 'device_total', 'device_number'] as $key) same(false, in_array($key, $editableKeys, true), '计算字段不出现在临时编辑表单：' . $key);
        same(true, in_array('color', $editableKeys, true), '颜色仍可临时编辑');
        same('5/10', (new VariableReplaceService())->replaceVariables('{{device_number}}', $overridden['print_data']), '最终指令仍保持正确编号');

        // 删除录入明细不抹掉客户已申报的总台数；不修改历史打印任务。
        Db::name('recycle_device')->where('id', 100)->delete();
        callPrivate($orderDevice, 'syncOrderDeviceCount', [100]);
        same(10, (int)Db::name('recycle_order')->where('id', 100)->value('count'), '移除设备不覆盖申报总数');
        same(9, (int)Db::name('recycle_order')->where('id', 100)->value('device_count'), '移除后实际进度更新');
        position($printer, 90, 9, 10, '移除后重新发起打印');
        echo '[通过] 打印设备总台数 ' . $checks . ' 项；真实 ThinkORM + SQLite 内存数据，无业务库/外网/真实打印。' . PHP_EOL;
    } catch (\Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL); exit(1);
    }
}
