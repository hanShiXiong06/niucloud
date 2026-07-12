<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelImportTaskService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;

$app = new think\App();
$app->initialize();

$siteId = 100005;
$suffix = date('YmdHis') . substr(md5((string)microtime(true)), 0, 6);
$source = 'codex_import_smoke_' . $suffix;
$category = '异步导入测试_' . $suffix;
$relativeDir = 'upload/hsx_recycle/model_import/smoke/';
$relativePath = $relativeDir . $suffix . '.xlsx';
$absoluteDir = public_path() . $relativeDir;
$absolutePath = public_path() . $relativePath;
$taskId = 0;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0755, true);
    }
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([
        ['品类', '品类ID', '品牌', '品牌ID', '系列', '型号', '产品ID', '热门'],
        [$category, '900001', 'TestBrand', '900002', 'SmokeSeries', 'SmokeModel A', '900003', '否'],
        [$category, '900001', 'TestBrand', '900002', 'SmokeSeries', 'SmokeModel B', '900004', '是'],
        [$category, '900001', 'TestBrand', '900002', 'SmokeSeries', 'SmokeModel B', '900004', '是'],
    ]);
    (new Xlsx($spreadsheet))->save($absolutePath);
    $spreadsheet->disconnectWorksheets();

    $now = time();
    $taskId = (int)Db::name('recycle_device_model_import_task')->insertGetId([
        'site_id' => $siteId,
        'operator_uid' => 1,
        'operator_name' => 'smoke',
        'source' => $source,
        'file_name' => basename($absolutePath),
        'file_path' => $relativePath,
        'status' => 'queued',
        'queue_enabled' => 1,
        'message' => 'smoke',
        'result_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'create_at' => $now,
        'update_at' => $now,
    ]);

    $taskService = new RecycleDeviceModelImportTaskService();
    $taskService->runTask($taskId, $siteId);
    $task = Db::name('recycle_device_model_import_task')->where('id', $taskId)->find();
    $assert((string)$task['status'] === 'partial', '包含重复行的任务应标记为部分完成');
    $assert((int)$task['total_rows'] === 3 && (int)$task['processed_rows'] === 3, '任务进度应完整记录');
    $assert((int)$task['created_count'] === 2, '应成功导入两个型号');
    $assert((int)$task['skipped_count'] === 1, '应跳过文件内重复型号');
    $leafCount = Db::name('recycle_device_model_dict')->where([
        ['site_id', '=', $siteId],
        ['source', '=', $source],
        ['node_type', '=', 'model'],
    ])->count();
    $assert((int)$leafCount === 2, '型号字典应写入两个末级型号');
    $formatTime = new ReflectionMethod($taskService, 'formatTime');
    $formatTime->setAccessible(true);
    $assert(
        $formatTime->invoke($taskService, '2026-07-12 14:00:00') === '2026-07-12 14:00:00',
        '日期字符串不得被强制转整数后显示为 1970 年'
    );

    echo "[PASS] recycle device model async import task smoke test\n";
} finally {
    Db::name('recycle_device_model_dict')->where([
        ['site_id', '=', $siteId],
        ['source', '=', $source],
    ])->delete();
    if ($taskId > 0) {
        Db::name('recycle_device_model_import_task')->where('id', $taskId)->delete();
    }
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
