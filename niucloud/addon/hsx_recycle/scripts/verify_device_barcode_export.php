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
        same(true, $service->export(['device_ids' => [1, 2, 3, 4, 5, 1]]), '导出选中五台并去重');
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
        \core\base\BaseJob::$failDispatch = true;
        throws(fn() => $service->export(['device_ids' => [6]]), '队列不可用', '提交队列失败可重试');
        same(-1, (int)Db::name('sys_export')->order('id desc')->value('export_status'), '队列提交失败不会永远显示导出中');
        echo "PASS {$checks} checks\nArtifacts: " . public_path() . PHP_EOL;
    } catch (\Throwable $e) {
        fwrite(STDERR, $e->__toString() . PHP_EOL);
        exit(1);
    }
}
