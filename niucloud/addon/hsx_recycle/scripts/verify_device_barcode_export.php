<?php
declare(strict_types=1);

// SQLite 内存库 + 临时目录。运行真实导出查询/任务/写入器，不读 .env，不连接业务数据库。
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    function root_path() { return dirname(__DIR__, 3) . '/'; }
    function public_path() { return $GLOBALS['export_test_dir'] . '/'; }
    function get_lang($key, $params = []) { return $key; }
}
namespace core\base {
    class BaseCoreService { public function __construct() {} }
    class BaseAdminService { public int $site_id = 100005; public function __construct() {} }
    class BaseJob {
        public static bool $failDispatch = false;
        public static function dispatch($data) {
            if (self::$failDispatch) throw new \RuntimeException('模拟队列不可用');
            return (new static())->doJob(...$data);
        }
    }
}
namespace {
    use addon\hsx_recycle\app\job\device\DeviceBarcodeExportJob;
    use addon\hsx_recycle\app\listener\export\RecycleDeviceExportListener;
    use addon\hsx_recycle\app\service\admin\device_export\DeviceBarcodeWorkbook;
    use addon\hsx_recycle\app\service\admin\device_export\DeviceExportService;
    use PhpOffice\PhpSpreadsheet\IOFactory;
    use think\facade\Db;

    $GLOBALS['export_test_dir'] = $argv[1] ?? sys_get_temp_dir() . '/hsx_barcode_test_' . bin2hex(random_bytes(6));
    if (!is_dir($GLOBALS['export_test_dir'])) mkdir($GLOBALS['export_test_dir'], 0700, true);
    Db::setConfig(['default' => 'isolated', 'auto_timestamp' => false, 'connections' => ['isolated' => [
        'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'ut_', 'fields_strict' => true,
    ]]]);
    foreach ([
        'recycle_device' => 'id INTEGER PRIMARY KEY, site_id INTEGER, imei TEXT, imei2 TEXT DEFAULT "", sn TEXT DEFAULT "", member_id INTEGER DEFAULT 0, model TEXT DEFAULT "测试手机", check_result TEXT DEFAULT "", info TEXT DEFAULT "{}", category_id INTEGER DEFAULT 0, color TEXT DEFAULT "银色", package_type TEXT DEFAULT "", capacity TEXT DEFAULT "256GB", warranty_info TEXT DEFAULT "", system_version TEXT DEFAULT "", check_template_id INTEGER DEFAULT 0, status INTEGER DEFAULT 5, final_price TEXT DEFAULT "2000.00", sell_price TEXT DEFAULT "2200.00", update_at INTEGER DEFAULT 100, order_id INTEGER DEFAULT 0, price_uid INTEGER DEFAULT 0, dispose_type TEXT DEFAULT "recycle", dispose_status INTEGER DEFAULT 1, settlement_mode TEXT DEFAULT "", consignment_order_id INTEGER DEFAULT 0, export_time INTEGER DEFAULT 0',
        'recycle_order' => 'id INTEGER PRIMARY KEY, order_no TEXT',
        'recycle_consignment_order' => 'id INTEGER PRIMARY KEY, consignment_no TEXT, source_device_id INTEGER, status INTEGER, listing_price TEXT, sold_price TEXT, settlement_amount TEXT',
        'sys_user' => 'uid INTEGER PRIMARY KEY, username TEXT, real_name TEXT, delete_time INTEGER DEFAULT 0',
        'phone_shop_goods_category' => 'category_id INTEGER PRIMARY KEY, category_name TEXT',
        'sys_export' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER, export_key TEXT, export_num INTEGER DEFAULT 0, file_path TEXT DEFAULT "", file_size TEXT DEFAULT "", export_status INTEGER DEFAULT 0, fail_reason TEXT DEFAULT "", create_time INTEGER DEFAULT 0',
        'recycle_check_template' => 'id INTEGER PRIMARY KEY, site_id INTEGER, schema_json TEXT DEFAULT "", status INTEGER DEFAULT 1',
        'recycle_check_field' => 'id INTEGER PRIMARY KEY, site_id INTEGER, template_id INTEGER, field_key TEXT',
        'recycle_check_option' => 'id INTEGER PRIMARY KEY, field_id INTEGER, option_value TEXT, option_label TEXT',
        'recycle_device_model_dict' => 'id INTEGER PRIMARY KEY, site_id INTEGER, pid INTEGER DEFAULT 0',
        'recycle_template_binding' => 'id INTEGER PRIMARY KEY, site_id INTEGER, target_type TEXT DEFAULT "model_dict", target_id INTEGER, scene_key TEXT DEFAULT "manual_device_label", check_template_id INTEGER, inherit_enabled INTEGER DEFAULT 1, status INTEGER DEFAULT 1',
    ] as $table => $columns) Db::execute('CREATE TABLE ut_' . $table . ' (' . $columns . ')');
    foreach ([1 => '353618265971368', 2 => '012345678901234', 3 => '', 4 => '971368', 5 => '353618265971368', 6 => '123456789012345', 7 => '223456789012345', 90 => '999999999999999'] as $id => $imei) {
        Db::name('recycle_device')->insert(['id' => $id, 'site_id' => $id === 90 ? 100024 : 100005, 'imei' => $imei, 'update_at' => 100 + $id]);
    }
    Db::name('recycle_device')->where('id', 2)->update(['model' => '=1+1', 'imei2' => '012345678901235', 'sn' => '0000SN']);
    Db::name('recycle_device')->where('id', 7)->update(['status' => 9, 'dispose_type' => 'consign']);
    $checks = 0;
    function same($expected, $actual, string $label): void {
        if ($expected !== $actual) throw new \RuntimeException('FAIL ' . $label . ': ' . json_encode([$expected, $actual], JSON_UNESCAPED_UNICODE));
        $GLOBALS['checks']++;
        echo 'PASS ' . $label . PHP_EOL;
    }
    function throws(callable $fn, string $needle, string $label): void {
        try { $fn(); } catch (\Throwable $e) { same(true, str_contains($e->getMessage(), $needle), $label); return; }
        throw new \RuntimeException('FAIL 未拦截：' . $label);
    }
    function readRows(array $where = []): array {
        return (new RecycleDeviceExportListener())->handle(['site_id' => 100005, 'type' => 'recycle_device', 'where' => $where, 'mark_exported' => false, 'keep_device_id' => true]);
    }
    try {
        same(7, count(readRows()), '只读取当前站点的回收/代卖设备');
        same([7], array_column(readRows(['warehouse_type' => 'consign']), '_device_id'), '代卖仓筛选保留');
        same(6, count(readRows(['warehouse_type' => 'owned'])), '回收仓筛选保留');
        same([2, 1], array_column(readRows(['device_ids' => [1, 2, 90]]), '_device_id'), '勾选导出不越站、不扩成全部');
        same([5, 1], array_column(readRows(['imei' => '353618265971368']), '_device_id'), 'IMEI 条件筛选保留');
        same(0, (int)Db::name('recycle_device')->sum('export_time'), '读取导出数据没有提前标记已导出');

        $service = new DeviceExportService();
        same(true, $service->export(['device_ids' => [1, 2, 3, 4, 5, 1], 'include_barcode' => 1]), '勾选条形码后导出选中五台并去重');
        $record = Db::name('sys_export')->order('id desc')->find();
        same(2, (int)$record['export_status'], '完整文件生成后任务成功');
        same(5, (int)$record['export_num'], '导出记录台数准确');
        same(5, (int)Db::name('recycle_device')->where('export_time', '>', 0)->count(), '只标记实际导出的五台');
        same(0, (int)Db::name('recycle_device')->where('id', 90)->value('export_time'), '其他站点未被修改');
        $path = public_path() . $record['file_path'];
        same(true, is_file($path) && filesize($path) > 0, '下载文件存在且不为空');
        copy($path, public_path() . 'sample.xlsx');
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        same('IMEI 条形码', $sheet->getCell('C1')->getValue(), '条形码位于 IMEI 旁');
        same(6, $sheet->getHighestRow(), '每台设备保留一行');
        same('012345678901234', $sheet->getCell('B5')->getValue(), 'IMEI 前导零完整保留');
        same('s', $sheet->getCell('B5')->getDataType(), 'IMEI 明确保存为文本');
        same('012345678901235', $sheet->getCell('D5')->getValue(), 'IMEI2 文本未受影响');
        same('0000SN', $sheet->getCell('E5')->getValue(), 'SN 文本未受影响');
        same('=1+1', $sheet->getCell('F5')->getValue(), '商品原文未改写');
        same('s', $sheet->getCell('F5')->getDataType(), '用户内容不能变为 Excel 公式');
        same('未填写 IMEI', $sheet->getCell('C4')->getValue(), '空串号明确提示');
        same('非15位数字 IMEI，未生成条形码', $sheet->getCell('C3')->getValue(), '短串号不冒充完整 IMEI');
        same(3, $sheet->getDrawingCollection()->count(), '正常及重复串号逐行有图，异常行不伪造图');
        $expected = ['C2' => '353618265971368', 'C5' => '012345678901234', 'C6' => '353618265971368'];
        $manifest = [];
        foreach ($sheet->getDrawingCollection() as $drawing) {
            same($expected[$drawing->getCoordinates()], $drawing->getDescription(), '图片与所在行串号一致 ' . $drawing->getCoordinates());
            $bytes = file_get_contents($drawing->getPath());
            $name = public_path() . $drawing->getCoordinates() . '.png';
            file_put_contents($name, $bytes);
            $manifest[] = ['image' => $name, 'imei' => $drawing->getDescription()];
            $size = getimagesizefromstring($bytes);
            same(true, $size[0] <= 323 && $size[1] + 5 <= 76 / 0.75, '条码图片不超出列宽和行高');
        }
        file_put_contents(public_path() . 'barcode-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $book->disconnectWorksheets();
        same(true, (new DeviceBarcodeExportJob())->doJob(100005, (int)$record['id'], ['device_ids' => [1, 2, 3, 4, 5]]), '已成功任务重复执行不重新导出');
        same($record['file_path'], Db::name('sys_export')->where('id', $record['id'])->value('file_path'), '成功任务重试保留原文件');
        throws(fn() => $service->export(['device_ids' => [6, 90]]), '不在可导出范围', '混入越站设备整体失败，不悄悄漏行');
        same(0, (int)Db::name('recycle_device')->where('id', 6)->value('export_time'), '失败导出不标记本站设备');
        same(-1, (int)Db::name('sys_export')->order('id desc')->value('export_status'), '失败任务可在导出记录识别');
        same(true, $service->export(['export_status' => 'unexported']), '筛选全量导出而非仅当前页');
        same(2, (int)Db::name('sys_export')->order('id desc')->value('export_num'), '未导出范围包含剩余两台');
        throws(fn() => $service->export(['export_status' => 'unexported']), '没有符合条件', '空结果明确失败');
        throws(fn() => $service->export(['device_ids' => '1,2']), '参数不正确', '拒绝错误类型，不意外导出全量');
        throws(fn() => $service->export(['device_ids' => [0]]), '参数不正确', '拒绝无效 ID');
        $invalidSiteService = new DeviceExportService();
        $invalidSiteService->site_id = 0;
        throws(fn() => $invalidSiteService->export(['device_ids' => [1]]), '站点信息无效', '无有效站点不能创建导出');
        foreach ([[], ['include_barcode' => 0], ['include_barcode' => 'false']] as $options) {
            same(true, $service->export(['device_ids' => [2]] + $options), '默认/关闭条形码仍可导出');
            $plain = Db::name('sys_export')->order('id desc')->find();
            $plainBook = IOFactory::load(public_path() . $plain['file_path']);
            $plainSheet = $plainBook->getActiveSheet();
            same(0, $plainSheet->getDrawingCollection()->count(), '不勾选时没有任何条码图片');
            same('imei2', $plainSheet->getCell('C1')->getValue(), '不勾选时没有额外 IMEI 条形码列');
            same('012345678901234', $plainSheet->getCell('B2')->getValue(), '普通导出保留完整串号和前导零');
            same('s', $plainSheet->getCell('B2')->getDataType(), '普通导出串号仍为文本');
            $plainBook->disconnectWorksheets();
        }
        foreach (['1', true] as $option) {
            same(true, $service->export(['device_ids' => [1], 'include_barcode' => $option]), '显式开启选项透传队列');
            $enabled = Db::name('sys_export')->order('id desc')->find();
            $enabledBook = IOFactory::load(public_path() . $enabled['file_path']);
            same(1, $enabledBook->getActiveSheet()->getDrawingCollection()->count(), '显式开启后确实生成条码图片');
            $enabledBook->disconnectWorksheets();
        }
        // 公共库转换后，普通/条形码导出必须按每台设备自己的模板解析，不能导出选项 key。
        $seedTemplate = static function (int $id, int $site, string $capacity, string $color): void {
            $fields = [];
            foreach (['capacity' => $capacity, 'color' => $color, 'package_type' => '原盒全套', 'warranty_info' => '保修期内'] as $key => $label) {
                $fields[] = ['field_key' => $key, 'component' => 'radio', 'options' => [['value' => '2', 'label' => $label]]];
            }
            Db::name('recycle_check_template')->insert(['id' => $id, 'site_id' => $site, 'schema_json' => json_encode(['groups' => [['fields' => $fields]]], JSON_UNESCAPED_UNICODE)]);
        };
        $seedTemplate(10, 0, '256GB', '银色');
        $seedTemplate(11, 100005, '512GB', '蓝色');
        $seedTemplate(12, 100024, '其他站点容量', '其他站点颜色');
        $seedTemplate(13, 0, '1TB', '黑色');
        $seedTemplate(14, 0, '已停用容量', '已停用颜色');
        Db::name('recycle_check_template')->where('id', 14)->update(['status' => 0]);
        Db::name('recycle_check_template')->insert(['id' => 15, 'site_id' => 0]);
        Db::name('recycle_check_field')->insert(['id' => 100, 'site_id' => 0, 'template_id' => 15, 'field_key' => 'capacity']);
        Db::name('recycle_check_option')->insertAll([
            ['id' => 1, 'field_id' => 100, 'option_value' => '2', 'option_label' => '128GB'],
            ['id' => 2, 'field_id' => 100, 'option_value' => '3', 'option_label' => '256GB'],
        ]);
        foreach ([20 => [0, 0], 21 => [0, 20], 22 => [0, 0], 23 => [100024, 0], 24 => [100005, 0], 25 => [0, 0], 26 => [0, 0], 27 => [0, 0], 28 => [0, 0], 29 => [0, 28]] as $id => [$owner, $parent]) {
            Db::name('recycle_device_model_dict')->insert(['id' => $id, 'site_id' => $owner, 'pid' => $parent]);
        }
        foreach ([20 => [0, 10], 23 => [100024, 12], 24 => [100005, 11], 25 => [0, 14], 26 => [0, 12], 27 => [0, 13], 28 => [0, 10]] as $id => [$owner, $templateId]) {
            Db::name('recycle_template_binding')->insert(['id' => $id, 'site_id' => $owner, 'target_id' => $id, 'check_template_id' => $templateId]);
        }
        Db::name('recycle_template_binding')->where('id', 27)->update(['scene_key' => 'custom_print']);
        Db::name('recycle_template_binding')->where('id', 28)->update(['inherit_enabled' => 0]);
        Db::name('recycle_template_binding')->insert(['id' => 30, 'site_id' => 0, 'target_type' => 'global', 'target_id' => 0, 'check_template_id' => 13]);
        // 公共型号即使有原站旧绑定，也应使用公共型号归属的绑定。
        Db::name('recycle_template_binding')->insert(['id' => 31, 'site_id' => 100005, 'target_id' => 20, 'check_template_id' => 11]);
        $snapshot = ['check_meta' => ['result_items' => [
            ['field_key' => 'capacity', 'values' => ['2'], 'labels' => ['原机1TB']],
            ['field_key' => 'color', 'option_items' => [['value' => '2', 'label' => '原机钛色']]],
        ]]];
        $fixtures = [
            101 => ['check_template_id' => 10],
            102 => ['info' => ['check_meta' => ['template_id' => 10]]],
            103 => ['category_id' => 20],
            104 => ['category_id' => 21],
            105 => ['category_id' => 24],
            106 => ['category_id' => 20, 'info' => $snapshot],
            107 => ['category_id' => 20, 'check_template_id' => 11, 'info' => $snapshot],
            108 => ['category_id' => 22],
            109 => ['category_id' => 23],
            110 => ['category_id' => 25],
            111 => ['category_id' => 26],
            112 => ['category_id' => 27],
            113 => ['category_id' => 29],
            114 => ['check_template_id' => 15],
            115 => ['capacity' => '', 'color' => '', 'info' => ['check_meta' => ['template_id' => 10], 'sign_summary' => ['capacity' => '2', 'color' => '2']]],
            116 => ['category_id' => 20, 'check_template_id' => 999],
            117 => ['category_id' => 20, 'check_template_id' => 12],
            118 => ['capacity' => '9', 'info' => $snapshot],
            119 => ['check_template_id' => 15, 'capacity' => '["2","3"]'],
            120 => ['capacity' => ['value' => '2', 'label' => '2TB'], 'color' => '紫色', 'warranty_info' => '2027-09-17'],
            121 => ['category_id' => 20, 'info' => ['check_meta' => ['template_id' => 11]]],
        ];
        foreach ($fixtures as $id => $values) {
            $values += ['site_id' => 100005, 'imei' => '123456789000' . $id, 'capacity' => '2', 'color' => '2', 'package_type' => '2', 'warranty_info' => '2', 'system_version' => '26.6'];
            foreach (['info', 'capacity'] as $jsonKey) if (isset($values[$jsonKey]) && is_array($values[$jsonKey])) $values[$jsonKey] = json_encode($values[$jsonKey], JSON_UNESCAPED_UNICODE);
            Db::name('recycle_device')->insert(['id' => $id] + $values);
        }
        $before = [];
        foreach (['recycle_device', 'recycle_check_template', 'recycle_check_field', 'recycle_check_option', 'recycle_template_binding'] as $table) $before[$table] = Db::name($table)->select()->toArray();
        $resolved = array_column(readRows(['device_ids' => array_keys($fixtures)]), null, '_device_id');
        foreach ([101, 102, 103, 104, 115] as $id) {
            same('256GB', $resolved[$id]['capacity'], '公共模板/元数据/型号/继承/签收摘要解析容量 #' . $id);
            same('银色', $resolved[$id]['color'], '按本设备字段解析颜色 #' . $id);
        }
        same('原盒全套', $resolved[103]['package_type'], '包装通过型号绑定还原，不仅处理容量颜色');
        same('保修期内', $resolved[103]['warranty_info'], '保修选项通过型号绑定还原');
        $rawResolved = (new \addon\hsx_recycle\app\service\admin\device_export\DeviceExportValueResolver())->resolve([Db::name('recycle_device')->where('id', 103)->find()], 100005);
        same('26.6', $rawResolved[0]['system_version'], '自由输入版本号保持原值');
        same('512GB', $resolved[105]['capacity'], '同批次私有模板与公共模板互不串用');
        same('蓝色', $resolved[105]['color'], '不同模板同一key输出各自颜色');
        same('原机1TB', $resolved[106]['capacity'], '同设备标签快照优先于当前型号绑定');
        same('原机钛色', $resolved[106]['color'], 'option_items标签快照解析');
        same('512GB', $resolved[107]['capacity'], '已保存模板优先于快照和当前型号');
        same('512GB', $resolved[121]['capacity'], 'meta保存模板优先于当前型号');
        foreach ([108, 109, 110, 111, 112, 113, 116, 117] as $id) same('2', $resolved[$id]['capacity'], '无可靠关联时不借用外站/全局/停用/自定义场景模板 #' . $id);
        same('128GB', $resolved[114]['capacity'], '公共拆表模板正式option_value优先于主键别名');
        same('128GB,256GB', $resolved[119]['capacity'], '多值逐项回译');
        same('9', $resolved[118]['capacity'], '快照值不一致不能套另一选项标签');
        same('2TB', $resolved[120]['capacity'], '值自带label时显示文字');
        same('紫色', $resolved[120]['color'], '已存文字不更改');
        same('2027-09-17', $resolved[120]['warranty_info'], '保修日期不更改');
        foreach ($before as $table => $rows) same($rows, Db::name($table)->select()->toArray(), '解析只读，不回写 ' . $table);
        foreach ([false, true] as $barcode) {
            same(true, $service->export(['device_ids' => [101, 102, 103, 104, 105, 114], 'include_barcode' => $barcode]), '实际导出公共/私有/型号绑定设备，条形码=' . (int)$barcode);
            $result = Db::name('sys_export')->order('id desc')->find();
            $workbook = IOFactory::load(public_path() . $result['file_path']);
            $cells = $workbook->getActiveSheet()->toArray();
            $headers = array_flip(array_shift($cells));
            $byImei = [];
            foreach ($cells as $row) $byImei[$row[$headers['imei']]] = $row;
            foreach ([101 => '256GB', 102 => '256GB', 103 => '256GB', 104 => '256GB', 105 => '512GB', 114 => '128GB'] as $id => $label) {
                same($label, $byImei['123456789000' . $id][$headers['内存']], '重新打开Excel单元格确认不是key #' . $id);
            }
            same('银色', $byImei['123456789000103'][$headers['颜色']], 'Excel颜色为银色而不是2');
            same('原盒全套', $byImei['123456789000103'][$headers['包装']], 'Excel包装为文字');
            $workbook->disconnectWorksheets();
        }
        \core\base\BaseJob::$failDispatch = true;
        throws(fn() => $service->export(['device_ids' => [6]]), '队列不可用', '提交队列失败可重试');
        same(-1, (int)Db::name('sys_export')->order('id desc')->value('export_status'), '队列提交失败不会永远显示导出中');
        echo "PASS {$checks} checks\nArtifacts: " . public_path() . PHP_EOL;
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->__toString() . PHP_EOL);
        exit(1);
    }
}
