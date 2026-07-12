<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device;

use addon\hsx_recycle\app\job\DeviceModelImport;
use addon\hsx_recycle\app\model\device\RecycleDeviceModelImportTask;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use think\facade\Log;

/**
 * 设备分类导入任务服务
 */
class RecycleDeviceModelImportTaskService extends BaseAdminService
{
    private const IMPORT_DIR = 'upload/hsx_recycle/model_import/';
    private const CHUNK_SIZE = 500;
    private const ERROR_SAMPLE_LIMIT = 300;

    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceModelImportTask();
    }

    public function upload($file, string $source = 'recycle_spider'): array
    {
        if (!$file || !$file->isValid()) {
            throw new CommonException('Excel 文件上传失败');
        }
        $extension = strtolower((string)$file->getOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            throw new CommonException('只支持上传 xls、xlsx 格式');
        }
        if ((int)$file->getSize() > 20 * 1024 * 1024) {
            throw new CommonException('文件大小不能超过 20MB');
        }

        $source = trim($source) !== '' ? trim($source) : 'recycle_spider';
        $relativeDir = self::IMPORT_DIR . date('Y/m/d') . '/';
        $saveDir = public_path() . $relativeDir;
        if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
            throw new CommonException('无法创建导入文件目录');
        }
        $storedName = uniqid('model_', true) . '.' . $extension;
        $file->move($saveDir, $storedName);
        $relativePath = $relativeDir . $storedName;
        $now = time();

        $task = $this->model->create([
            'site_id' => (int)$this->site_id,
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'source' => $source,
            'file_name' => (string)$file->getOriginalName(),
            'file_path' => $relativePath,
            'status' => 'pending',
            'queue_enabled' => env('queue.state', false) ? 1 : 0,
            'message' => '文件已上传，等待进入导入队列',
            'result_json' => [],
            'create_at' => $now,
            'update_at' => $now,
        ]);

        return $this->dispatchTask((int)$task->id, (int)$this->site_id);
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = (int)$this->site_id;
        $query = $this->model
            ->withSearch(['site_id', 'status'], $where)
            ->order('id desc');
        $result = $this->pageQuery($query);
        foreach (['data', 'list'] as $key) {
            if (!empty($result[$key]) && is_array($result[$key])) {
                $result[$key] = array_map([$this, 'formatTask'], $result[$key]);
            }
        }
        return $result;
    }

    public function getInfo(int $id): array
    {
        $task = $this->model->where([
            ['site_id', '=', (int)$this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty()->toArray();
        if (empty($task)) {
            throw new CommonException('导入任务不存在');
        }
        return $this->formatTask($task);
    }

    public function retry(int $id): array
    {
        $task = $this->getInfo($id);
        if ($task['status'] === 'processing') {
            throw new CommonException('任务正在执行，请勿重复提交');
        }
        if (!is_file($this->absolutePath((string)$task['file_path']))) {
            throw new CommonException('原始 Excel 文件已失效，请重新上传');
        }
        $this->model->where([
            ['site_id', '=', (int)$this->site_id],
            ['id', '=', $id],
        ])->update([
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'result_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'error_message' => '',
            'finish_at' => 0,
            'message' => '等待重新进入导入队列',
            'update_at' => time(),
        ]);
        return $this->dispatchTask($id, (int)$this->site_id);
    }

    public function delete(int $id): bool
    {
        $task = $this->getInfo($id);
        if (in_array($task['status'], ['queued', 'processing'], true)) {
            throw new CommonException('任务正在执行，暂时不能删除');
        }
        $path = $this->absolutePath((string)$task['file_path']);
        if (is_file($path)) {
            @unlink($path);
        }
        $this->model->where([
            ['site_id', '=', (int)$this->site_id],
            ['id', '=', $id],
        ])->delete();
        return true;
    }

    public function runTask(int $taskId, int $siteId): void
    {
        $task = $this->model->where([
            ['site_id', '=', $siteId],
            ['id', '=', $taskId],
        ])->findOrEmpty()->toArray();
        if (empty($task)) {
            throw new CommonException('设备分类导入任务不存在');
        }
        if ($task['status'] === 'completed') {
            return;
        }

        $path = $this->absolutePath((string)$task['file_path']);
        if (!is_file($path)) {
            $this->markFailed($taskId, $siteId, '原始 Excel 文件不存在');
            throw new CommonException('原始 Excel 文件不存在');
        }

        $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
            'status' => 'processing',
            'message' => '正在解析 Excel 并导入分类',
            'start_at' => time(),
            'update_at' => time(),
        ]);

        try {
            $meta = $this->inspectWorkbook($path);
            $headerRow = $meta['header_row'];
            $totalRows = max(0, $meta['total_rows'] - $headerRow);
            $headers = $meta['headers'];
            $lastColumn = $meta['last_column'];
            $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
                'sheet_name' => $meta['sheet_name'],
                'total_rows' => $totalRows,
                'message' => $totalRows > 0 ? '正在分批导入设备分类' : 'Excel 中没有可导入数据',
                'update_at' => time(),
            ]);

            if ($totalRows <= 0) {
                throw new CommonException('Excel 中没有可导入数据');
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $processed = 0;
            $errorSamples = [];
            $dictService = new RecycleDeviceModelDictService();
            $dataStart = $headerRow + 1;
            $dataEnd = $meta['total_rows'];

            for ($start = $dataStart; $start <= $dataEnd; $start += self::CHUNK_SIZE) {
                $end = min($dataEnd, $start + self::CHUNK_SIZE - 1);
                $rows = $this->readRows($path, $start, $end, $lastColumn);
                $payloadRows = [];
                $lineNumbers = [];
                foreach ($rows as $offset => $values) {
                    $lineNo = $start + $offset;
                    if (!$this->hasRowValue($values)) {
                        continue;
                    }
                    $payloadRows[] = $this->combineRow($headers, $values);
                    $lineNumbers[] = $lineNo;
                }

                if (!empty($payloadRows)) {
                    $result = $dictService->importExternalRowsForSite(
                        $payloadRows,
                        (string)$task['source'],
                        $siteId,
                        $lineNumbers
                    );
                    $created += (int)($result['created_count'] ?? 0);
                    $updated += (int)($result['updated_count'] ?? 0);
                    $skipped += (int)($result['skipped_count'] ?? 0);
                    foreach (($result['skipped'] ?? []) as $error) {
                        if (count($errorSamples) < self::ERROR_SAMPLE_LIMIT) {
                            $errorSamples[] = $error;
                        }
                    }
                }

                $processed = min($totalRows, $end - $headerRow);
                $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
                    'processed_rows' => $processed,
                    'created_count' => $created,
                    'updated_count' => $updated,
                    'skipped_count' => $skipped,
                    'error_count' => $skipped,
                    'result_json' => json_encode([
                        'skipped' => $errorSamples,
                        'errors_truncated' => $skipped > count($errorSamples),
                    ], JSON_UNESCAPED_UNICODE),
                    'message' => sprintf('已处理 %d / %d 行', $processed, $totalRows),
                    'update_at' => time(),
                ]);
            }

            $status = $skipped > 0 ? 'partial' : 'completed';
            $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
                'status' => $status,
                'processed_rows' => $totalRows,
                'created_count' => $created,
                'updated_count' => $updated,
                'skipped_count' => $skipped,
                'error_count' => $skipped,
                'message' => $status === 'completed' ? '导入完成' : '导入完成，部分数据已跳过',
                'finish_at' => time(),
                'update_at' => time(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($taskId, $siteId, $e->getMessage());
            throw $e;
        }
    }

    private function dispatchTask(int $taskId, int $siteId): array
    {
        if (!env('queue.state', false)) {
            $message = '导入任务已创建，但当前队列未启用；开启队列后点击“重试”即可执行。';
            $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
                'status' => 'pending',
                'queue_enabled' => 0,
                'message' => $message,
                'update_at' => time(),
            ]);
            return ['task_id' => $taskId, 'async' => false, 'queue_enabled' => false, 'message' => $message];
        }

        $message = '文件已进入后台导入队列，可以关闭窗口继续其他操作。';
        $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
            'status' => 'queued',
            'queue_enabled' => 1,
            'message' => $message,
            'update_at' => time(),
        ]);
        $pushed = DeviceModelImport::dispatch(['taskId' => $taskId, 'siteId' => $siteId]);
        if ($pushed === false) {
            $message = '任务创建成功，但队列推送失败，请检查 Redis 与队列进程后重试。';
            $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
                'status' => 'pending',
                'queue_enabled' => 1,
                'message' => $message,
                'update_at' => time(),
            ]);
            return ['task_id' => $taskId, 'async' => false, 'queue_enabled' => true, 'message' => $message];
        }

        return ['task_id' => $taskId, 'async' => true, 'queue_enabled' => true, 'message' => $message];
    }

    private function inspectWorkbook(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $worksheets = $reader->listWorksheetInfo($path);
        $sheet = $worksheets[0] ?? [];
        $totalRows = (int)($sheet['totalRows'] ?? 0);
        $lastColumn = (string)($sheet['lastColumnLetter'] ?? 'A');
        if ($totalRows <= 0) {
            throw new CommonException('Excel 工作表为空');
        }

        $previewEnd = min($totalRows, 20);
        $preview = $this->readRows($path, 1, $previewEnd, $lastColumn);
        $headerIndex = $this->detectHeaderIndex($preview);
        $headers = array_map(static fn($value) => trim((string)$value), $preview[$headerIndex] ?? []);
        if (!$this->hasRequiredHeaders($headers)) {
            throw new CommonException('表头至少需要包含“品类、品牌、型号”');
        }

        return [
            'sheet_name' => (string)($sheet['worksheetName'] ?? ''),
            'total_rows' => $totalRows,
            'last_column' => $lastColumn,
            'header_row' => $headerIndex + 1,
            'headers' => $headers,
        ];
    }

    private function readRows(string $path, int $startRow, int $endRow, string $lastColumn): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($startRow, $endRow) implements IReadFilter {
                private int $startRow;
                private int $endRow;

                public function __construct(int $startRow, int $endRow)
                {
                    $this->startRow = $startRow;
                    $this->endRow = $endRow;
                }

                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row >= $this->startRow && $row <= $this->endRow;
                }
            });
        }
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->rangeToArray(
            'A' . $startRow . ':' . $lastColumn . $endRow,
            '',
            true,
            true,
            false
        );
        $spreadsheet->disconnectWorksheets();
        return $rows;
    }

    private function detectHeaderIndex(array $rows): int
    {
        $aliases = ['品类', 'category', 'category_name', '品牌', 'brand', 'brand_name', '型号', 'model', 'model_name', 'goods_name'];
        $bestIndex = 0;
        $bestScore = -1;
        foreach ($rows as $index => $row) {
            $values = array_map(static fn($value) => mb_strtolower(trim((string)$value)), $row);
            $score = count(array_intersect($values, $aliases));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = (int)$index;
            }
        }
        return $bestIndex;
    }

    private function hasRequiredHeaders(array $headers): bool
    {
        $normalized = array_map(static fn($value) => mb_strtolower(trim((string)$value)), $headers);
        $groups = [
            ['品类', 'category', 'category_name'],
            ['品牌', 'brand', 'brand_name'],
            ['型号', 'model', 'model_name', 'goods_name'],
        ];
        foreach ($groups as $aliases) {
            if (empty(array_intersect($normalized, $aliases))) {
                return false;
            }
        }
        return true;
    }

    private function combineRow(array $headers, array $values): array
    {
        $row = [];
        $columnCount = max(count($headers), count($values));
        for ($index = 0; $index < $columnCount; $index++) {
            $header = trim((string)($headers[$index] ?? ''));
            if ($header === '') {
                continue;
            }
            $row[$header] = trim((string)($values[$index] ?? ''));
        }
        return $row;
    }

    private function hasRowValue(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return true;
            }
        }
        return false;
    }

    private function markFailed(int $taskId, int $siteId, string $message): void
    {
        $message = mb_substr($message, 0, 1000);
        $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update([
            'status' => 'failed',
            'message' => '导入失败',
            'error_message' => $message,
            'finish_at' => time(),
            'update_at' => time(),
        ]);
        Log::error('设备分类导入失败', ['task_id' => $taskId, 'site_id' => $siteId, 'message' => $message]);
    }

    private function formatTask(array $task): array
    {
        $status = (string)($task['status'] ?? 'pending');
        $labels = [
            'pending' => '待执行',
            'queued' => '排队中',
            'processing' => '导入中',
            'completed' => '已完成',
            'partial' => '部分完成',
            'failed' => '失败',
        ];
        $types = [
            'pending' => 'info',
            'queued' => 'primary',
            'processing' => 'primary',
            'completed' => 'success',
            'partial' => 'warning',
            'failed' => 'danger',
        ];
        $total = (int)($task['total_rows'] ?? 0);
        $processed = (int)($task['processed_rows'] ?? 0);
        $task['status_name'] = $labels[$status] ?? $status;
        $task['status_type'] = $types[$status] ?? 'info';
        $task['progress'] = $total > 0 ? min(100, (int)floor($processed * 100 / $total)) : ($status === 'completed' ? 100 : 0);
        $task['create_at_text'] = $this->formatTime($task['create_at'] ?? 0);
        $task['finish_at_text'] = $this->formatTime($task['finish_at'] ?? 0);
        return $task;
    }

    private function formatTime($value): string
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return '-';
        }
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int)$value);
        }
        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : '-';
    }

    private function absolutePath(string $relativePath): string
    {
        return public_path() . ltrim($relativePath, '/');
    }
}
