<?php
declare(strict_types=1);

namespace {
    // 仅加载依赖，使用 SQLite 内存库和临时工作簿；绝不加载应用 .env。
    require dirname(__DIR__) . '/niucloud/vendor/autoload.php';
    $testRoot = sys_get_temp_dir() . '/recycle-check-import-' . bin2hex(random_bytes(6)) . '/';
    mkdir($testRoot . 'upload/check_import', 0700, true);
    function public_path(): string { return $GLOBALS['testRoot']; }
    function runtime_path(): string { return $GLOBALS['testRoot']; }
    function config($key) { return $key === 'database.connections.mysql.prefix' ? 'ut_' : null; }
    class CheckImportDb extends \think\DbManager {
        public function query(string $sql, array $bind = []): array {
            if (preg_match('/^SHOW COLUMNS FROM `([a-z_]+)` LIKE \'([a-z_]+)\'$/', $sql, $m)) {
                return array_values(array_filter($this->connect()->query('PRAGMA table_info(' . $m[1] . ')'), static fn($column) => $column['name'] === $m[2]));
            }
            return $this->connect()->query($sql, $bind);
        }
    }
}
namespace think\facade {
    class Db extends \think\Facade {
        protected static function getFacadeClass() { return \CheckImportDb::class; }
    }
}
namespace core\base {
    class BaseAdminService {
        public int $site_id = 100005;
        public int $uid = 21;
        public string $username = '导入测试';
    }
}
namespace addon\hsx_recycle\app\model\check {
    class RecycleCheckImportBatch extends \think\Model { protected $name = 'recycle_check_import_batch'; }
    class RecycleCheckDict extends \think\Model { protected $name = 'recycle_check_dict'; }
}
namespace {
    use think\facade\Db;
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use addon\hsx_recycle\app\service\admin\check\RecycleCheckCatalogService as Catalog;
    use addon\hsx_recycle\app\service\admin\check\RecycleCheckSeverityService as Severity;

    $root = dirname(__DIR__) . '/niucloud/';
    require $root . 'addon/hsx_recycle/app/service/admin/check/RecycleCheckCatalogService.php';
    require $root . 'addon/hsx_recycle/app/service/admin/check/RecycleCheckSeverityService.php';
    Db::setConfig(['default' => 'isolated', 'auto_timestamp' => false, 'connections' => ['isolated' => [
        'type' => 'sqlite', 'database' => ':memory:', 'prefix' => 'ut_', 'fields_strict' => true,
    ]]]);
    foreach ([
        'recycle_check_import_batch' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INT, source TEXT, file_name TEXT, total_rows INT, status TEXT, error_message TEXT DEFAULT "", operator_uid INT, operator_name TEXT, inserted INT DEFAULT 0, updated INT DEFAULT 0, skipped_same INT DEFAULT 0, skipped_user INT DEFAULT 0, create_at INT, update_at INT',
        'recycle_check_template' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INT, template_key TEXT, template_name TEXT, scene TEXT, is_default INT, status INT, sort INT, version INT, schema_hash TEXT, schema_json TEXT, create_at INT, update_at INT',
        'recycle_check_dict' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INT, dict_type TEXT, text TEXT, severity TEXT, is_user_modified INT, sort INT, create_at INT, update_at INT',
        'recycle_device_model_dict' => 'id INTEGER PRIMARY KEY, site_id INT, product_source_id TEXT',
        'recycle_template_binding' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INT, target_type TEXT, target_id INT, scene_key TEXT, check_template_id INT, print_template_id INT, inherit_enabled INT, status INT, sort INT, remark TEXT, create_at INT, update_at INT',
        'recycle_check_field' => 'id INTEGER PRIMARY KEY, site_id INT, template_id INT',
        'recycle_check_group' => 'id INTEGER PRIMARY KEY, site_id INT, template_id INT',
        'recycle_check_option' => 'id INTEGER PRIMARY KEY, site_id INT, field_id INT',
    ] as $table => $columns) Db::execute('CREATE TABLE ut_' . $table . ' (' . $columns . ')');
    for ($id = 1; $id <= 6; $id++) Db::name('recycle_device_model_dict')->insert(['id' => $id, 'site_id' => 100005, 'product_source_id' => (string)$id]);
    Db::name('recycle_device_model_dict')->insert(['id' => 7, 'site_id' => 100024, 'product_source_id' => '1']);

    $checks = 0;
    function check(bool $ok, string $label): void {
        if (!$ok) throw new RuntimeException('FAIL ' . $label);
        $GLOBALS['checks']++;
        echo 'PASS ' . $label . PHP_EOL;
    }
    function rejects(callable $action, string $message): void {
        try { $action(); } catch (\core\exception\CommonException $e) {
            check(str_contains($e->getMessage(), $message), '明确拦截：' . $message . ' [' . $e->getMessage() . ']');
            return;
        }
        throw new RuntimeException('FAIL 未抛出预期错误：' . $message);
    }
    function invoke(object $service, string $method, array $args) {
        $reflection = new ReflectionMethod($service, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($service, $args);
    }
    function workbook(string $name, array $rows, string $title = '检测数据'): string {
        $path = public_path() . 'upload/check_import/' . $name;
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle($title)->fromArray($rows, null, 'A1');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return $path;
    }
    function uploaded(string $path): object {
        return new class($path) {
            private string $path;
            public function __construct(string $path) { $this->path = $path; }
            public function isValid(): bool { return true; }
            public function getOriginalExtension(): string { return pathinfo($this->path, PATHINFO_EXTENSION); }
            public function getPathname(): string { return $this->path; }
            public function getSize(): int { return filesize($this->path); }
        };
    }
    $catalog = new Catalog();
    $severity = new Severity();
    $header = ['型号', '产品ID', '检测项', '分类', '默认选项', '全部选项'];
    $data = ['测试型号', '1', '屏幕外观', '外观', '完美', '完美 | 划痕'];
    try {
        $xml = '<x:row xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" r="2"><x:c r="A2" t="s"><x:v>0</x:v></x:c><x:c r="B2"><x:v>123</x:v></x:c><x:c r="C2" t="inlineStr"><x:is><x:r><x:t>屏幕</x:t></x:r><x:r><x:t>外观</x:t></x:r></x:is></x:c><x:c r="G2"><x:v>忽略</x:v></x:c></x:row>';
        check(invoke($catalog, 'parseXlsxRow', [$xml, ['测试型号']]) === ['测试型号', '123', '屏幕外观', '', '', ''], '带前缀命名空间、共享字符串、数字和富文本读取正确');

        $path = workbook('prefixed.xlsx', [$header, $data, ['测试型号', 1, '机身颜色', '基本问题', '黑色', '黑色 | 白色']]);
        $zip = new ZipArchive(); $zip->open($path);
        foreach (['xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $entry) {
            $content = (string)$zip->getFromName($entry);
            $content = preg_replace('/(<\/?)([a-zA-Z][\w-]*)(?=[\s>\/])/', '$1x:$2', $content);
            $content = str_replace('xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"', 'xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"', $content);
            $zip->addFromString($entry, $content);
        }
        $zip->close();
        $batch = $catalog->importInit($path, basename($path), 'prefixed.xlsx');
        $result = $catalog->importChunk($batch['batch_id'], $batch['token'], 0);
        check($result['done'] && $result['rows_done'] === 2 && $result['templates'] === 1 && $result['bindings'] === 1, '前缀 XML 文件实际写入模板与绑定，不再完成却零条');
        $template = Db::name('recycle_check_template')->where('site_id', 100005)->find();
        $schema = json_decode($template['schema_json'], true);
        check(count($schema['groups']) === 2, '同型号不同分组完整保存');
        check(Db::name('recycle_check_dict')->where('dict_type', 'option')->count() === 4, '共享字符串的选项字典已保存');
        check(Db::name('recycle_template_binding')->where('site_id', 100024)->count() === 0, '不串站绑定其他站点型号');

        $path = workbook('append.xlsx', [$header, $data]);
        $batch = $catalog->importInit($path, basename($path), 'append.xlsx');
        $result = $catalog->importChunk($batch['batch_id'], $batch['token'], 0);
        check($result['done'] && $result['skipped_exists'] === 1 && $result['templates'] === 0 && $result['rows_done'] === 1, '追加全部已存在时合法完成并显示跳过，而非空导入');

        $path = workbook('inline.xlsx', [$header]);
        $zip->open($path);
        $zip->deleteName('xl/sharedStrings.xml');
        $xmlRow = static function (int $row, array $values): string {
            $cells = '';
            foreach ($values as $col => $value) $cells .= '<c r="' . chr(65 + $col) . $row . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$value, ENT_XML1) . '</t></is></c>';
            return '<row r="' . $row . '">' . $cells . '</row>';
        };
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xmlRow(1, $header)
            . $xmlRow(3, ['二号机', 2, '显示', '功能', '正常', '正常 | 异常'])
            . $xmlRow(13350, ['三号机', 3, '声音', '功能', '正常', '正常 | 异常']) . '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet7.xml', $xml);
        $zip->deleteName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/_rels/workbook.xml.rels', str_replace('worksheets/sheet1.xml', 'worksheets/sheet7.xml', (string)$zip->getFromName('xl/_rels/workbook.xml.rels')));
        $zip->close();
        $batch = $catalog->importInit($path, basename($path), 'inline.xlsx');
        check($batch['total_rows'] === 13349, '稀疏工作表以实际行号计算扫描范围');
        $first = $catalog->importChunk($batch['batch_id'], $batch['token'], 0, 1);
        check(!$first['done'] && $first['next_offset'] === 13350 && $first['rows_done'] === 1, '分批保留下一型号的第一行');
        $last = $catalog->importChunk($batch['batch_id'], $batch['token'], $first['next_offset'], 1);
        check($last['done'] && $last['rows_done'] === 2 && $last['bindings'] === 2, '无共享字符串、非 sheet1、稀疏末行正常导入');

        $before = Db::name('recycle_check_template')->count();
        $path = workbook('empty.xlsx', [$header]);
        rejects(fn() => $catalog->importInit($path, basename($path), 'empty.xlsx', 'overwrite'), '没有可导入的检测项');
        // 模拟已上传批次在升级后从 offset=0 发起覆盖，仍需先校验。
        $id = Db::name('recycle_check_import_batch')->insertGetId(['site_id' => 100005, 'file_name' => 'empty.xlsx|empty.xlsx|overwrite', 'total_rows' => 0, 'status' => 'processing']);
        rejects(fn() => $catalog->importChunk($id, basename($path), 0, 80, 'overwrite'), '没有可导入的检测项');
        check(Db::name('recycle_check_template')->count() === $before, '空文件覆盖不会清空已有模板');
        check(Db::name('recycle_check_import_batch')->where('id', $id)->value('status') === 'failed' && is_file($path), '失败批次记录原因并保留上传文件');
        check(str_contains((string)Db::name('recycle_check_import_batch')->where('id', $id)->value('error_message'), '没有可导入'), '失败原因可以返回管理端');

        $path = workbook('rollback.xlsx', [$header, $data]);
        $batch = $catalog->importInit($path, basename($path), 'rollback.xlsx', 'overwrite');
        $templateIds = Db::name('recycle_check_template')->order('id')->column('id');
        $bindingCount = Db::name('recycle_template_binding')->count();
        Db::execute("CREATE TRIGGER deny_template_insert BEFORE INSERT ON ut_recycle_check_template BEGIN SELECT RAISE(ABORT, 'fixture_db_error'); END");
        $failed = false;
        try { $catalog->importChunk($batch['batch_id'], $batch['token'], 0, 80, 'overwrite'); }
        catch (\Throwable $e) { $failed = true; }
        Db::execute('DROP TRIGGER deny_template_insert');
        check($failed && Db::name('recycle_check_template')->order('id')->column('id') === $templateIds, '首片覆盖写入失败，原模板和ID事务回滚');
        check(Db::name('recycle_template_binding')->count() === $bindingCount, '覆盖写入失败，型号绑定也完整回滚');
        check(Db::name('recycle_check_import_batch')->where('id', $batch['batch_id'])->value('status') === 'failed', '数据库异常批次也明确标记失败');

        $path = workbook('blank.xlsx', []);
        $id = Db::name('recycle_check_import_batch')->insertGetId(['site_id' => 100005, 'file_name' => 'blank.xlsx|blank.xlsx|append', 'total_rows' => 13349, 'status' => 'processing']);
        rejects(fn() => $catalog->importChunk($id, basename($path), 2), '未读取到可导入');
        check(Db::name('recycle_check_import_batch')->where('id', $id)->value('status') === 'failed', '分批结束零条必须失败，不返回 done 成功');

        $path = workbook('wrong.xlsx', [['id', '型号', '产品ID', '检测项'], [1, '机型', 1, '屏幕']]);
        rejects(fn() => $catalog->importInit($path, basename($path), 'wrong.xlsx'), '格式不匹配');
        $levelHeader = ['系统选项ID', '选项文本', '建议级别', '确认状态'];
        $path = workbook('level-wrong-entry.xlsx', [$levelHeader, [1, '完美', '正常', '已确认']]);
        rejects(fn() => $catalog->importInit($path, basename($path), 'level.xlsx'), '选项级别');
        $path = workbook('catalog-wrong-entry.xlsx', [$header, $data]);
        rejects(fn() => $severity->previewImport(uploaded($path)), '检测目录');

        $dictId = (int)Db::name('recycle_check_dict')->where('dict_type', 'option')->where('text', '完美')->value('id');
        $path = workbook('renamed-level.xlsx', [$levelHeader, [$dictId, '完美', '异常', '已确认']], '人工标注后的数据');
        $preview = $severity->previewImport(uploaded($path));
        check($preview['summary']['will_update'] === 1 && $preview['summary']['errors'] === 0, '改名后的级别工作表按必要表头定位并预检');
        check(Db::name('recycle_check_dict')->where('id', $dictId)->value('severity') === 'normal', '预检不修改级别，仍需确认导入');
        $path = workbook('pending-level.xlsx', [$levelHeader, [$dictId, '完美', '异常', '待确认']], '级别标注');
        $preview = $severity->previewImport(uploaded($path));
        check($preview['summary']['pending'] === 1 && $preview['summary']['will_update'] === 0, '待确认建议不会自动当作人工确认');
        $path = workbook('unknown-level.xlsx', [$levelHeader, [9999, '其他站点选项', '异常', '已确认']], '级别标注');
        check($severity->previewImport(uploaded($path))['summary']['errors'] === 1, '未知或其他站点 ID 不允许误匹配');
        $path = workbook('empty-level.xlsx', [$levelHeader], '级别标注');
        rejects(fn() => $severity->previewImport(uploaded($path)), '只有表头');

        $path = public_path() . 'upload/check_import/multiline.csv';
        $fh = fopen($path, 'w');
        fputcsv($fh, $header); fputcsv($fh, ['四号机', 4, "屏幕\n显示", '功能', '正常', '正常 | 异常']); fclose($fh);
        $batch = $catalog->importInit($path, basename($path), 'multiline.csv');
        check($batch['total_rows'] === 1, 'CSV 单元格换行不虚增数据行数');
        $result = $catalog->importChunk($batch['batch_id'], $batch['token'], 0);
        check($result['rows_done'] === 1 && $result['bindings'] === 1, 'CSV 真实模板绑定流程通过');

        $path = public_path() . 'upload/check_import/legacy.xls';
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([$header, $data, ['二号机', 2, '显示', '功能', '正常', '正常 | 异常']], null, 'A1');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xls($book))->save($path);
        $book->disconnectWorksheets();
        $batch = $catalog->importInit($path, basename($path), 'legacy.xls');
        $first = $catalog->importChunk($batch['batch_id'], $batch['token'], 0, 1);
        $last = $catalog->importChunk($batch['batch_id'], $batch['token'], $first['next_offset'], 1);
        check(!$first['done'] && $last['done'] && $last['rows_done'] === 2 && $last['skipped_exists'] === 2, 'xls 格式按型号切片和重复追加均正常');

        // 20000 行切片边界不能拆开同一型号，后半部分不能被 append 规则吞掉。
        $path = workbook('boundary.xlsx', [$header]);
        $zip->open($path); $zip->deleteName('xl/sharedStrings.xml');
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xmlRow(1, $header);
        for ($i = 2; $i <= 20002; $i++) $xml .= $xmlRow($i, ['五号机', 5, '检测项' . $i, '功能', '正常', '正常']);
        $xml .= $xmlRow(20003, ['六号机', 6, '下一型号', '功能', '正常', '正常']) . '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml); $zip->close(); unset($xml);
        $batch = $catalog->importInit($path, basename($path), 'boundary.xlsx');
        $result = $catalog->importChunk($batch['batch_id'], $batch['token'], 0);
        check(!$result['done'] && $result['rows_done'] === 20001 && $result['next_offset'] === 20003, '跨两万行仍完整处理同一型号，再切换到下一型号');
        $result = $catalog->importChunk($batch['batch_id'], $batch['token'], $result['next_offset']);
        check($result['done'] && $result['rows_done'] === 20002 && $result['bindings'] === 2, '型号边界续传没有漏掉或重复处理');

        echo "\n全部 {$checks} 项导入回归检查通过（SQLite 内存库，无业务数据改动）。\n";
    } finally {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($testRoot);
    }
}
