<?php
declare(strict_types=1);

// 只加载依赖，使用 SQLite 内存库；不加载 .env、不写业务库、不调用打印机。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
}
namespace core\base {
    class BaseAdminService { public int $site_id = 100005; public function __construct() {} }
    class BaseModel extends \think\Model {}
}
namespace addon\hsx_recycle\app\model\order {
    class RecycleDevice extends \think\Model {
        protected $name = 'recycle_device';
        protected $json = ['info'];
        public function with($relations) { return $this; }
    }
    class RecycleOrder extends \think\Model { protected $name = 'recycle_order'; }
}
namespace {
    use think\facade\Db;
    use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrinterTemplateService;
    use addon\hsx_recycle\app\service\admin\printer\RecyclePrintSceneService;
    use addon\hsx_recycle\app\service\admin\printer\template\VariableReplaceService;
    use addon\hsx_recycle\app\service\admin\template\RecycleTemplateBindingService;

    function request() { return new class { public function domain() { return 'http://print-test.invalid'; } }; }
    Db::setConfig(['default' => 'isolated', 'auto_timestamp' => false, 'connections' => ['isolated' => [
        'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'ut_', 'fields_strict' => true,
    ]]]);
    foreach ([
        'recycle_check_template' => 'id INTEGER PRIMARY KEY, site_id INTEGER, schema_json TEXT, template_name TEXT DEFAULT "测试质检模板", status INTEGER DEFAULT 1',
        'recycle_check_field' => 'id INTEGER PRIMARY KEY, site_id INTEGER, template_id INTEGER, field_key TEXT',
        'recycle_check_option' => 'id INTEGER PRIMARY KEY, field_id INTEGER, option_value TEXT, option_label TEXT',
        'recycle_device' => 'id INTEGER PRIMARY KEY, site_id INTEGER, order_id INTEGER DEFAULT 0, category_id INTEGER DEFAULT 0, check_template_id INTEGER DEFAULT 0, capacity TEXT DEFAULT "", color TEXT DEFAULT "", info TEXT DEFAULT "{}", model TEXT DEFAULT "测试型号", imei TEXT DEFAULT "TEST-IMEI", initial_price DECIMAL DEFAULT 0, final_price DECIMAL DEFAULT 0, sell_price DECIMAL DEFAULT 0, final_status INTEGER DEFAULT 0, check_at INTEGER DEFAULT 0, price_at INTEGER DEFAULT 0, create_at INTEGER DEFAULT 0, update_at INTEGER DEFAULT 0',
        'recycle_order' => 'id INTEGER PRIMARY KEY, site_id INTEGER, order_no TEXT',
        'recycle_device_model_dict' => 'id INTEGER PRIMARY KEY, site_id INTEGER, pid INTEGER DEFAULT 0, node_name TEXT, model_full_name TEXT',
        'recycle_template_binding' => 'id INTEGER PRIMARY KEY, site_id INTEGER, target_type TEXT, target_id INTEGER, scene_key TEXT, check_template_id INTEGER DEFAULT 0, print_template_id INTEGER DEFAULT 0, status INTEGER DEFAULT 1, inherit_enabled INTEGER DEFAULT 1',
        'recycle_printer_template' => 'template_id INTEGER PRIMARY KEY, site_id INTEGER, template_name TEXT, delete_time INTEGER DEFAULT 0',
    ] as $table => $columns) Db::execute('CREATE TABLE ut_' . $table . ' (' . $columns . ')');

    $checks = 0;
    function same($expected, $actual, string $message): void {
        if ($expected !== $actual) throw new \RuntimeException('FAIL ' . $message . ': ' . json_encode(['expected' => $expected, 'actual' => $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
        echo 'PASS ' . $message . PHP_EOL;
    }
    function callPrivate(object $service, string $method, array $args = []) {
        $reflection = new \ReflectionMethod($service, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($service, $args);
    }
    function bare(string $class): object { return (new \ReflectionClass($class))->newInstanceWithoutConstructor(); }
    function seedTemplate(int $id, array $fields, int $siteId = 100005): void {
        Db::name('recycle_check_template')->insert(['id' => $id, 'site_id' => $siteId, 'schema_json' => json_encode(['groups' => [['fields' => $fields]]], JSON_UNESCAPED_UNICODE)]);
    }
    function seedDevice(int $id, int $templateId, string $capacity, string $color, array $info = []): void {
        Db::name('recycle_device')->insert(['id' => $id, 'site_id' => 100005, 'check_template_id' => $templateId, 'capacity' => $capacity, 'color' => $color, 'info' => json_encode($info, JSON_UNESCAPED_UNICODE)]);
    }
    function field(string $key, array $options): array { return ['field_key' => $key, 'field_name' => $key, 'component' => 'radio', 'options' => $options]; }

    try {
        seedTemplate(10, [
            field('capacity', [['value' => '1', 'label' => '128GB'], ['value' => '2', 'label' => '256GB']]),
            field('color', [['value' => '1', 'label' => '黑色'], ['value' => '2', 'label' => '银色']]),
            field('battery', [['value' => '1', 'label' => '电池区间选项，不是实测值']]),
        ]);
        seedDevice(3036, 10, '2', '2', ['check_meta' => ['battery' => 75]]);
        $printer = bare(RecyclePrinterTemplateService::class);
        $scene = bare(RecyclePrintSceneService::class);
        $serviceProperty = new \ReflectionProperty($scene, 'templateService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($scene, $printer);
        $getPlanFields = static function (int $deviceId) use ($scene): array {
            $biz = callPrivate($scene, 'resolveBizPrintData', ['device', ['device_id' => $deviceId, 'biz_id' => $deviceId]]);
            $config = callPrivate($scene, 'resolveLabelEditConfig', [
                ['biz_type' => 'device', 'button_config' => ['label_edit' => ['enabled' => 1]]],
                ['width' => 50, 'instruction_content' => '{{model}} {{capacity}} {{color}} {{battery}}'], $biz['print_data'],
            ]);
            return array_column($config['fields'], 'value', 'key');
        };
        $values = $getPlanFields(3036);
        same('256GB', $values['capacity'], 'print_scene 的 label_edit 容量输出文字');
        same('银色', $values['color'], '同为2的容量和颜色按各自字段解析');
        same('75', $values['battery'], '电池75是测量值，不按容量或颜色转换');
        same('256GB / 银色', (new VariableReplaceService())->replaceVariables('{{capacity}} / {{color}}', $printer->getDevicePrintData(3036)), '实际打印内容与弹窗预填相同');

        seedTemplate(11, [
            field('capacity', [['name' => '128GB'], ['name' => '512GB']]),
            field('color', [['name' => '黑色'], ['name' => '蓝色']]),
        ]);
        seedDevice(3037, 11, '2', '2');
        $values = $getPlanFields(3037);
        same('512GB', $values['capacity'], '导入模板未显式存value时，与表单序号值一致');
        same('蓝色', $values['color'], '导入模板name格式恢复颜色文字');
        $maps = DeviceSummaryHelper::buildOptionLabelMap([11], ['capacity'], 100005);
        same('512GB', DeviceSummaryHelper::resolveDisplayValue('-1001002', $maps[11]['capacity']), '与表单生成的临时选项ID一致');
        same(false, isset($maps[11]['color']), '指定字段查询不会混入其他字段');

        seedDevice(3038, 0, '2', '2', ['check_meta' => ['template_id' => 10]]);
        $values = $getPlanFields(3038);
        same('256GB', $values['capacity'], '设备列无模板ID时读取已保存质检元数据的模板');
        same('银色', $values['color'], '不依赖打印场景的模板绑定来翻译颜色');

        $snapshot = ['check_meta' => ['result_items' => [
            ['field_key' => 'capacity', 'values' => ['2'], 'labels' => ['1TB']],
            ['field_key' => 'color', 'option_items' => [['value' => '2', 'label' => '原色钛金属']]],
        ]]];
        seedDevice(3039, 0, '2', '2', $snapshot);
        $values = $getPlanFields(3039);
        same('1TB', $values['capacity'], '无模板时使用同设备已保存的选项标签');
        same('原色钛金属', $values['color'], '支持option_items，ORM对象info不丢失');
        seedDevice(3040, 10, '2', '2', $snapshot);
        same('256GB', $getPlanFields(3040)['capacity'], '现存有效模板优先，旧快照不覆盖当前映射');
        seedDevice(3041, 0, '9', '紫色', $snapshot);
        same('9', $getPlanFields(3041)['capacity'], '快照值不匹配时不猜测另一选项的标签');
        same('紫色', $getPlanFields(3041)['color'], '已存文字原样保留');

        seedTemplate(12, [field('color', [['value' => '2', 'label' => '其他站点颜色']])], 100024);
        seedDevice(3042, 12, '2', '2');
        same('2', $getPlanFields(3042)['color'], '不能跨站点取同编号选项');

        Db::name('recycle_check_template')->insert(['id' => 13, 'site_id' => 100005, 'schema_json' => '']);
        Db::name('recycle_check_field')->insert(['id' => 100, 'template_id' => 13, 'site_id' => 100005, 'field_key' => 'capacity']);
        Db::name('recycle_check_option')->insertAll([
            ['id' => 1, 'field_id' => 100, 'option_value' => '2', 'option_label' => '512GB'],
            ['id' => 2, 'field_id' => 100, 'option_value' => '3', 'option_label' => '1TB'],
        ]);
        seedDevice(3043, 13, '2', '银色');
        same('512GB', $getPlanFields(3043)['capacity'], 'option主键不可覆盖其他选项正式value');

        seedDevice(3044, 0, '1,2', '2', ['check_meta' => ['battery' => 75, 'result_items' => [
            ['field_key' => 'capacity', 'values' => ['1', '2'], 'labels' => ['256GB', '512GB']],
            ['field_key' => 'color', 'values' => ['1', '2'], 'labels' => ['不能错配']],
            ['field_key' => 'battery', 'values' => ['75'], 'labels' => ['75%']],
        ]]]);
        same('256GB、512GB', $getPlanFields(3044)['capacity'], '多值逐项按已保存标签还原');
        same('2', $getPlanFields(3044)['color'], '标签数量不匹配时不强行一一对应');
        same('75', $getPlanFields(3044)['battery'], '文案兜底仅作用容量颜色，不改变电池数值');

        seedDevice(3045, 0, '', '', ['capacity' => ['value' => '2', 'label' => '512GB'], 'color' => ['value' => '2', 'label' => '银色']]);
        same('512GB', $getPlanFields(3045)['capacity'], '设备info中自带value-label结构直接可读');
        same('银色', $getPlanFields(3045)['color'], '对象形式颜色不退化成JSON或编号');

        // 现场设备未保存模板 ID/选项快照，质检页按型号绑定解析，打印应使用同一明确绑定。
        foreach ([22 => 0, 24 => 22, 25 => 0, 27 => 0] as $id => $pid) {
            Db::name('recycle_device_model_dict')->insert(['id' => $id, 'site_id' => 100005, 'pid' => $pid, 'node_name' => '测试型号', 'model_full_name' => '手机/测试型号']);
        }
        Db::name('recycle_device_model_dict')->insert(['id' => 23, 'site_id' => 100024, 'node_name' => '其他站点型号']);
        Db::name('recycle_template_binding')->insertAll([
            ['id' => 1, 'site_id' => 100005, 'target_type' => 'model_dict', 'target_id' => 22, 'scene_key' => 'manual_device_label', 'check_template_id' => 10, 'print_template_id' => 0],
            ['id' => 2, 'site_id' => 100005, 'target_type' => 'global', 'target_id' => 0, 'scene_key' => 'manual_device_label', 'check_template_id' => 11, 'print_template_id' => 0],
            ['id' => 3, 'site_id' => 100024, 'target_type' => 'model_dict', 'target_id' => 23, 'scene_key' => 'manual_device_label', 'check_template_id' => 12, 'print_template_id' => 0],
            ['id' => 4, 'site_id' => 100005, 'target_type' => 'model_dict', 'target_id' => 25, 'scene_key' => 'manual_device_label', 'check_template_id' => 0, 'print_template_id' => 8],
            ['id' => 5, 'site_id' => 100005, 'target_type' => 'model_dict', 'target_id' => 27, 'scene_key' => 'custom_20260604123419_2218', 'check_template_id' => 11, 'print_template_id' => 0],
        ]);
        Db::name('recycle_printer_template')->insert(['template_id' => 8, 'site_id' => 100005, 'template_name' => '测试标签布局']);
        seedDevice(3050, 0, '2', '2', ['check_meta' => ['battery' => 75, 'result_items' => []]]);
        Db::name('recycle_device')->where('id', 3050)->update(['category_id' => 22]);
        same('256GB', $getPlanFields(3050)['capacity'], '无保存模板和标签时按本站型号的质检绑定解析容量');
        same('银色', $getPlanFields(3050)['color'], '型号绑定解析颜色，与打印场景模板是否绑定无关');
        same('75', $getPlanFields(3050)['battery'], '型号兜底不改电池测量值');
        Db::name('recycle_device')->where('id', 3050)->update(['info' => json_encode(['check_meta' => ['battery' => 1]])]);
        same('1', $getPlanFields(3050)['battery'], '实测电池数值恰好等于选项编号时也不能被型号兜底改写');
        Db::name('recycle_device')->where('id', 3050)->update(['check_template_id' => 11]);
        same('512GB', $getPlanFields(3050)['capacity'], '设备已保存模板优先于当前型号绑定');
        Db::name('recycle_device')->where('id', 3050)->update(['check_template_id' => 0, 'info' => json_encode($snapshot)]);
        same('1TB', $getPlanFields(3050)['capacity'], '同设备已保存标签优先于当前型号绑定');
        Db::name('recycle_device')->where('id', 3050)->update(['info' => json_encode(['check_meta' => ['result_items' => [$snapshot['check_meta']['result_items'][0]]]])]);
        same('银色', $getPlanFields(3050)['color'], '快照只有容量时仍可从型号绑定补齐缺失颜色');
        Db::name('recycle_device')->where('id', 3050)->update(['info' => json_encode(['check_meta' => ['template_id' => 11]])]);
        same('512GB', $getPlanFields(3050)['capacity'], '已保存meta模板优先于当前型号绑定');
        Db::name('recycle_device')->where('id', 3050)->update(['info' => '{}', 'category_id' => 24]);
        same('256GB', $getPlanFields(3050)['capacity'], '支持已开启继承的上级型号绑定');
        Db::name('recycle_template_binding')->where('id', 1)->update(['inherit_enabled' => 0]);
        same('2', $getPlanFields(3050)['capacity'], '上级未开启继承时不借用它的选项');
        Db::name('recycle_template_binding')->where('id', 1)->update(['inherit_enabled' => 1, 'status' => 0]);
        same('2', $getPlanFields(3050)['capacity'], '停用绑定不参与兜底');
        Db::name('recycle_template_binding')->where('id', 1)->update(['status' => 1]);
        Db::name('recycle_check_template')->where('id', 10)->update(['status' => 0]);
        same('2', $getPlanFields(3050)['capacity'], '停用模板不参与型号兜底');
        Db::name('recycle_check_template')->where('id', 10)->update(['status' => 1]);
        foreach ([0 => '无型号', 23 => '其他站点型号', 25 => '仅绑定打印布局', 27 => '仅绑定自定义打印场景', 999 => '不存在型号'] as $nodeId => $reason) {
            Db::name('recycle_device')->where('id', 3050)->update(['category_id' => $nodeId]);
            same('2', $getPlanFields(3050)['capacity'], $reason . '时不套用全局通用模板');
        }
        same(11, (new RecycleTemplateBindingService())->resolveByTarget('model_dict', 25)['check_template_id'], '质检表单原有的全局模板兜底规则不变');
        Db::name('recycle_device')->where('id', 3050)->update(['category_id' => 22]);
        Db::name('recycle_template_binding')->where('id', 1)->update(['check_template_id' => 12]);
        same('2', $getPlanFields(3050)['color'], '本站绑定错误指向外站模板时也不能跨站解析');
        Db::name('recycle_template_binding')->where('id', 1)->update(['check_template_id' => 999]);
        same('2', $getPlanFields(3050)['capacity'], '被删除的绑定模板不以全局模板代替');
        Db::name('recycle_template_binding')->where('id', 1)->update(['check_template_id' => 10]);

        // 可选：用现场提供的模板接口 JSON 跑同一条打印取值链，不加载应用配置。
        if (!empty($argv[1])) {
            $provided = json_decode((string)file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)['data'];
            $template = $provided['template'];
            $templateId = (int)$template['id'];
            $siteId = (int)$template['site_id'];
            same(100005, $siteId, '现场字典使用预期站点');
            Db::name('recycle_check_template')->insert(['id' => $templateId, 'site_id' => $siteId, 'schema_json' => '']);
            foreach ($provided['groups'] as $group) {
                foreach ($group['fields'] as $field) {
                    Db::name('recycle_check_field')->insert(['id' => $field['id'], 'site_id' => $siteId, 'template_id' => $templateId, 'field_key' => $field['field_key']]);
                    foreach ($field['options'] as $option) {
                        Db::name('recycle_check_option')->insert(['id' => $option['id'], 'field_id' => $field['id'], 'option_value' => $option['value'], 'option_label' => $option['label']]);
                    }
                }
            }
            seedDevice(3046, $templateId, '2', '2', ['check_meta' => ['battery' => 75]]);
            same('256G', $getPlanFields(3046)['capacity'], '现场13170模板capacity=2输出256G');
            same('银色', $getPlanFields(3046)['color'], '现场13170模板color=2输出银色');
            same('75', $getPlanFields(3046)['battery'], '现场字典有电池区间选项，75仍保留真实数值');
            Db::name('recycle_device')->where('id', 3046)->update(['check_template_id' => 0, 'info' => json_encode(['check_meta' => ['template_id' => $templateId, 'battery' => 75]])]);
            same('256G', $getPlanFields(3046)['capacity'], '现场字典通过质检meta模板ID同样可解析');

            if (!empty($argv[2])) {
                $device = json_decode((string)file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR)['data'];
                same(0, (int)$device['check_template_id'], '现场设备未保存模板ID');
                $info = is_array($device['info']) ? $device['info'] : json_decode($device['info'], true, 512, JSON_THROW_ON_ERROR);
                same(0, (int)($info['check_meta']['template_id'] ?? 0), '现场设备meta也未保存模板ID');
                same([], $info['check_meta']['result_items'], '现场设备没有标签快照');
                same(22, (int)$device['category_id'], '现场设备型号节点为22');
                // 用户提供的 schema.resolve 已明确该型号解析为此模板；只在隔离库构造相同绑定。
                Db::name('recycle_template_binding')->where('id', 1)->update(['check_template_id' => $templateId]);
                Db::name('recycle_device')->where('id', 3036)->update([
                    'check_template_id' => (int)$device['check_template_id'], 'category_id' => (int)$device['category_id'],
                    'capacity' => $device['capacity'], 'color' => $device['color'], 'info' => json_encode($info, JSON_UNESCAPED_UNICODE),
                ]);
                $values = $getPlanFields(3036);
                same('256G', $values['capacity'], '设备3036真实取值形态：容量2解析为256G');
                same('银色', $values['color'], '设备3036真实取值形态：颜色2解析为银色');
                same('75', $values['battery'], '设备3036真实取值形态：电池仍为75');
                same('256G / 银色', (new VariableReplaceService())->replaceVariables('{{capacity}} / {{color}}', $printer->getDevicePrintData(3036)), '现场数据打印变量与label_edit一致');
            }
        }

        $before = [];
        foreach (['recycle_device', 'recycle_check_template', 'recycle_template_binding'] as $table) {
            $before[$table] = Db::name($table)->select()->toArray();
        }
        foreach (range(3036, 3045) as $id) $getPlanFields($id);
        foreach ($before as $table => $rows) {
            same($rows, Db::name($table)->select()->toArray(), '读取打印计划不修改 ' . $table);
        }
        echo '完成：' . $checks . " 项检查；只用 SQLite 内存数据，无真实打印。\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        exit(1);
    }
}
