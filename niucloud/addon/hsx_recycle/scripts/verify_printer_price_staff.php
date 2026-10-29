<?php
declare(strict_types=1);

// 隔离回归：只加载 Composer，全部数据存 SQLite :memory:。
// 不启动应用、不加载 .env、不连接业务数据库、不调用打印或通知接口。
// PHP 8.0: php -n addon/hsx_recycle/scripts/verify_printer_price_staff.php
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
}
namespace core\base {
    class BaseAdminService {
        public int $site_id = 100005;
        public function __construct() {}
    }
    class BaseModel extends \think\Model {}
}
namespace hsx_recycle\printer_price_staff_fixture {
    class User extends \think\Model {
        protected $name = 'sys_user';
        protected $pk = 'uid';
    }
}
namespace addon\hsx_recycle\app\model\order {
    // 与生产模型相同的 JSON 字段和真实 belongsTo；不能把 with() 替换成空操作。
    class RecycleDevice extends \think\Model {
        protected $name = 'recycle_device';
        protected $json = ['info'];
        public function priceUser() {
            return $this->belongsTo(\hsx_recycle\printer_price_staff_fixture\User::class, 'price_uid', 'uid')
                ->field('uid,username,real_name');
        }
        public function checkUser() {
            return $this->belongsTo(\hsx_recycle\printer_price_staff_fixture\User::class, 'check_uid', 'uid')
                ->field('uid,username,real_name');
        }
    }
    class RecycleOrder extends \think\Model { protected $name = 'recycle_order'; }
}
namespace {
    use addon\hsx_recycle\app\model\order\RecycleDevice;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrinterTemplateService;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService;
    use addon\hsx_recycle\app\service\admin\printer\template\TemplatePreviewService;
    use addon\hsx_recycle\app\service\admin\printer\template\TemplateRenderService;
    use addon\hsx_recycle\app\service\admin\printer\template\VariableReplaceService;
    use core\exception\AdminException;
    use think\facade\Db;

    function request() {
        return new class { public function domain() { return 'https://printer-fixture.invalid'; } };
    }
    function same($expected, $actual, string $message): void {
        if ($expected !== $actual) {
            throw new \RuntimeException('FAIL ' . $message . ': ' . json_encode([
                'expected' => $expected, 'actual' => $actual,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $GLOBALS['checks']++;
        echo 'PASS ' . $message . PHP_EOL;
    }
    function callPrivate(object $service, string $method, array $args = []) {
        $reflection = new \ReflectionMethod($service, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($service, $args);
    }
    function bare(string $class): object { return (new \ReflectionClass($class))->newInstanceWithoutConstructor(); }
    function quote($uid, string $name, string $source = 'customer'): array {
        return ['uid' => $uid, 'name' => $name, 'source' => $source, 'price' => '4600.00', 'assign_to_quote_user' => 1];
    }
    function seedDevice(int $id, int $priceUid = 0, array $quote = [], int $siteId = 100005): void {
        Db::name('recycle_device')->insert([
            'id' => $id, 'site_id' => $siteId, 'price_uid' => $priceUid,
            'imei' => '35390000000' . str_pad((string)$id, 4, '0', STR_PAD_LEFT),
            'info' => json_encode($quote === [] ? [] : ['customer_quote' => $quote], JSON_UNESCAPED_UNICODE),
        ]);
    }
    function changeQuote(int $id, array $quote): void {
        Db::name('recycle_device')->where('id', $id)->update([
            'info' => json_encode($quote === [] ? [] : ['customer_quote' => $quote], JSON_UNESCAPED_UNICODE),
        ]);
    }
    function assertStaff(RecyclePrinterTemplateService $printer, int $id, string $name, string $message): array {
        $data = $printer->getDevicePrintData($id);
        same($name, $data['price_staff'], $message . ' / price_staff');
        same($name, $data['price_staff_name'], $message . ' / 两个变量别名一致');
        same('定:' . $name, (new VariableReplaceService())->replaceVariables('定:{{price_staff}}', $data), $message . ' / 实际标签替换');
        return $data;
    }

    $checks = 0;
    try {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            throw new \RuntimeException('需要 PDO SQLite 驱动，仅用于内存回归测试');
        }
        Db::setConfig(['default' => 'isolated', 'auto_timestamp' => false, 'connections' => ['isolated' => [
            'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'ut_', 'fields_strict' => true,
        ]]]);
        Db::execute('CREATE TABLE ut_sys_user (uid INTEGER PRIMARY KEY, username TEXT, real_name TEXT)');
        Db::execute('CREATE TABLE ut_recycle_device (' . implode(', ', [
            'id INTEGER PRIMARY KEY', 'site_id INTEGER', 'order_id INTEGER DEFAULT 0',
            'check_uid INTEGER DEFAULT 0', 'price_uid INTEGER DEFAULT 0', 'category_id INTEGER DEFAULT 0',
            'check_template_id INTEGER DEFAULT 0', 'capacity TEXT DEFAULT "256GB"', 'color TEXT DEFAULT "银色"',
            'info TEXT DEFAULT "{}"', 'model TEXT DEFAULT "苹果 iPhone Air"', 'imei TEXT DEFAULT ""',
            'initial_price DECIMAL DEFAULT 0', 'final_price DECIMAL DEFAULT 0', 'sell_price DECIMAL DEFAULT 0',
            'final_status INTEGER DEFAULT 0', 'check_at INTEGER DEFAULT 0', 'price_at INTEGER DEFAULT 0',
            'create_at INTEGER DEFAULT 0', 'update_at INTEGER DEFAULT 0',
        ]) . ')');
        Db::name('sys_user')->insertAll([
            ['uid' => 11, 'username' => 'staff_a', 'real_name' => '报价甲'],
            ['uid' => 12, 'username' => 'staff_b', 'real_name' => '报价乙'],
            ['uid' => 13, 'username' => 'price_account', 'real_name' => '正式定价员'],
            ['uid' => 14, 'username' => 'price_login', 'real_name' => ''],
            ['uid' => 15, 'username' => '', 'real_name' => ''],
        ]);
        $printer = bare(RecyclePrinterTemplateService::class);
        $scene = bare(RecyclePrintSceneService::class);
        $serviceProperty = new \ReflectionProperty($scene, 'templateService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($scene, $printer);

        seedDevice(1, 0, quote(11, '报价甲'));
        assertStaff($printer, 1, '报价甲', '客户选择报价员后、正式定价前可打印');
        changeQuote(1, quote(12, '管理员改选乙', 'admin'));
        $adminData = assertStaff($printer, 1, '管理员改选乙', '管理员改选使用当前快照、不残留原报价员');
        changeQuote(1, []);
        assertStaff($printer, 1, '', '管理员清空报价员后不沿用旧名字');

        seedDevice(2, 13, quote(11, '旧报价甲'));
        $ormData = (new RecycleDevice())->with(['checkUser', 'priceUser'])->find(2)->toArray();
        same('正式定价员', $ormData['priceUser']['real_name'], '真实 ThinkORM belongsTo 读到正式定价关联');
        assertStaff($printer, 2, '正式定价员', '正式定价实际人员优先于客户预选');
        seedDevice(3, 14, quote(11, '旧报价甲'));
        assertStaff($printer, 3, 'price_login', '正式员工未填写姓名时回退登录名');
        seedDevice(4, 99, quote(11, '不同UID不应串人'));
        assertStaff($printer, 4, '', '正式人员关联丢失时不打印不同UID的报价员');
        seedDevice(5, 99, quote(99, '同UID保留姓名', 'admin'));
        assertStaff($printer, 5, '同UID保留姓名', '正式人员关联丢失时可用同UID可信快照');
        seedDevice(6, 15, quote(15, '同UID历史姓名'));
        assertStaff($printer, 6, '同UID历史姓名', '正式关联姓名与账号均空时使用同UID可信快照');
        seedDevice(7, 99, quote(99, '伪造来源', 'summary'));
        assertStaff($printer, 7, '', '正式人员回退也必须校验快照来源');

        foreach ([
            '伪造source' => quote(11, '不可信报价员', 'system'),
            'UID为零' => quote(0, '不可信报价员'),
            'UID为负数' => quote(-11, '不可信报价员'),
            'UID非数字' => quote('not-a-uid', '不可信报价员'),
            'UID尾部混入字符' => quote('11x', '不可信报价员'),
            'UID有小数' => quote(11.5, '不可信报价员'),
            '快照姓名为空' => quote(11, '   '),
        ] as $case => $snapshot) {
            changeQuote(1, $snapshot);
            assertStaff($printer, 1, '', $case . '不生成打印姓名');
        }
        changeQuote(1, quote('11', '报价甲'));
        assertStaff($printer, 1, '报价甲', '正整数字符串UID与JSON存储兼容');
        seedDevice(8, 0, quote(12, '报价乙', 'admin'));
        assertStaff($printer, 8, '报价乙', '另一台设备使用自己的报价员');
        assertStaff($printer, 1, '报价甲', '循环打印后不会被上一台设备污染');
        seedDevice(9, 0, quote(11, '其他站点报价员'), 100006);
        try {
            $printer->getDevicePrintData(9);
            throw new \RuntimeException('FAIL 不应读取其他站点设备');
        } catch (AdminException $exception) {
            same('设备不存在', $exception->getMessage(), '按设备ID查询仍限制当前站点');
        }

        $biz = callPrivate($scene, 'resolveBizPrintData', ['device', ['device_id' => 8]]);
        same('报价乙', $biz['print_data']['price_staff'], '自动/手动打印计划取值使用同一设备数据服务');
        $labelEdit = callPrivate($scene, 'resolveLabelEditConfig', [
            ['biz_type' => 'device', 'button_config' => ['label_edit' => ['enabled' => 1]]],
            ['width' => 50, 'instruction_content' => '定:{{price_staff}} / {{price_staff_name}}'],
            $biz['print_data'],
        ]);
        $fieldValues = array_column($labelEdit['fields'], 'value', 'key');
        same('报价乙', $fieldValues['price_staff'], '打印弹窗预填 price_staff 姓名');
        same('报价乙', $fieldValues['price_staff_name'], '打印弹窗预填 price_staff_name 姓名');
        $override = callPrivate($scene, 'applyPrintDataOverride', [$biz, ['price_staff' => '本次临时打印名']]);
        same('定:本次临时打印名', (new VariableReplaceService())->replaceVariables('定:{{price_staff}}', $override['print_data']), '本次打印编辑覆盖仍然生效');
        assertStaff($printer, 8, '报价乙', '打印编辑覆盖不回写设备或员工信息');

        $template = ['width' => 50, 'height' => 30, 'elements' => [
            ['type' => 'text', 'content' => '定:{{price_staff}}', 'x' => 0, 'y' => 0],
        ]];
        $preview = new TemplatePreviewService();
        $previewData = $preview->generatePreviewVariables(['price_staff', 'price_staff_name']);
        same('张三', $previewData['price_staff'], '模板编辑器默认预览支持 price_staff');
        same($previewData['price_staff'], $previewData['price_staff_name'], '默认预览两个定价员别名一致');
        $render = new TemplateRenderService();
        $renderData = $render->generatePreviewData();
        same('张三', $renderData['price_staff'], '渲染器默认数据也支持 price_staff');
        same($renderData['price_staff'], $renderData['price_staff_name'], '渲染器默认别名一致');
        $html = $preview->generatePreview($template, $adminData)['html'];
        same(true, strpos($html, '定:管理员改选乙') !== false, '实际 HTML 预览显示当前报价员');

        echo '全部通过：' . $checks . ' 项；仅 SQLite 内存库，无业务数据改动、无真实打印。' . PHP_EOL;
    } catch (\Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
        exit(1);
    }
}
