<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\job\GoodsCatalogImport;
use addon\hsx_erp\app\model\ErpCatalogImportTask;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use think\facade\Log;

/** 商品目录大文件异步导入 */
class ErpGoodsCatalogImportTaskService extends BaseAdminService
{
    private const IMPORT_DIR = 'upload/hsx_erp/catalog_import/';
    private const CHUNK_SIZE = 500;
    private const CONFLICT_SAMPLE_LIMIT = 50;

    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ErpCatalogImportTask();
    }

    public function upload($file, string $sourceKey = 'excel_product_catalog'): array
    {
        if (!$file || !$file->isValid()) throw new CommonException('Excel 文件上传失败');
        $extension = strtolower((string)$file->getOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) throw new CommonException('只支持上传 xls、xlsx 格式');
        if ((int)$file->getSize() > 50 * 1024 * 1024) throw new CommonException('文件大小不能超过 50MB');

        $sourceKey = $this->normalizeSourceKey($sourceKey);
        $relativeDir = self::IMPORT_DIR . date('Y/m/d') . '/';
        $saveDir = public_path() . $relativeDir;
        if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
            throw new CommonException('无法创建商品目录导入目录');
        }
        $originalName = (string)$file->getOriginalName();
        $storedName = uniqid('catalog_', true) . '.' . $extension;
        $file->move($saveDir, $storedName);
        $now = time();
        $task = $this->model->create([
            'site_id' => (int)$this->site_id,
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'source_key' => $sourceKey,
            'file_name' => $originalName,
            'file_path' => $relativeDir . $storedName,
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
        $query = $this->model->withSearch(['site_id', 'status'], $where)->order('id desc');
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
        $task = $this->model->where([['site_id', '=', (int)$this->site_id], ['id', '=', $id]])->findOrEmpty()->toArray();
        if (!$task) throw new CommonException('商品目录导入任务不存在');
        return $this->formatTask($task);
    }

    public function retry(int $id): array
    {
        $task = $this->getInfo($id);
        if (in_array($task['status'], ['queued', 'processing'], true)) throw new CommonException('任务正在执行，请勿重复提交');
        if (!is_file($this->absolutePath((string)$task['file_path']))) throw new CommonException('原始 Excel 文件已失效，请重新上传');
        $this->model->where([['site_id', '=', (int)$this->site_id], ['id', '=', $id]])->update([
            'status' => 'pending', 'total_rows' => 0, 'processed_rows' => 0,
            'created_count' => 0, 'updated_count' => 0, 'skipped_count' => 0, 'error_count' => 0,
            'result_json' => json_encode([], JSON_UNESCAPED_UNICODE), 'error_message' => '',
            'start_at' => 0, 'finish_at' => 0, 'message' => '等待重新进入导入队列', 'update_at' => time(),
        ]);
        return $this->dispatchTask($id, (int)$this->site_id);
    }

    public function delete(int $id): bool
    {
        $task = $this->getInfo($id);
        if (in_array($task['status'], ['queued', 'processing'], true)) throw new CommonException('任务正在执行，暂时不能删除');
        $path = $this->absolutePath((string)$task['file_path']);
        if (is_file($path)) @unlink($path);
        $this->model->where([['site_id', '=', (int)$this->site_id], ['id', '=', $id]])->delete();
        return true;
    }

    public function runTask(int $taskId, int $siteId): void
    {
        $task = $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->findOrEmpty()->toArray();
        if (!$task) throw new CommonException('商品目录导入任务不存在');
        if ($task['status'] === 'completed') return;
        $path = $this->absolutePath((string)$task['file_path']);
        if (!is_file($path)) {
            $this->markFailed($taskId, $siteId, '原始 Excel 文件不存在');
            throw new CommonException('原始 Excel 文件不存在');
        }

        $this->updateTask($taskId, $siteId, [
            'status' => 'processing', 'message' => '正在解析 Excel 商品目录', 'start_at' => time(),
        ]);
        try {
            $meta = $this->inspectWorkbook($path);
            $headerRow = (int)$meta['header_row'];
            $totalRows = max(0, (int)$meta['total_rows'] - $headerRow);
            if ($totalRows <= 0) throw new CommonException('Excel 中没有可导入数据');
            $this->updateTask($taskId, $siteId, [
                'sheet_name' => $meta['sheet_name'], 'total_rows' => $totalRows,
                'message' => '正在分批导入商品目录',
            ]);

            $aggregate = [
                'master_created' => 0, 'site_created' => 0, 'site_updated' => 0,
                'skipped' => 0, 'duplicate_skipped' => 0, 'invalid_skipped' => 0,
                'master_conflicts' => 0,
                'conflict_samples' => [],
            ];
            $service = new ErpGoodsCatalogService();
            $dataStart = $headerRow + 1;
            $dataEnd = (int)$meta['total_rows'];
            foreach (range($dataStart, $dataEnd, self::CHUNK_SIZE) as $start) {
                $end = min($dataEnd, $start + self::CHUNK_SIZE - 1);
                $rows = $this->readRows($path, $start, $end, (string)$meta['last_column']);
                $payloadRows = [];
                foreach ($rows as $values) {
                    if ($this->hasRowValue($values)) $payloadRows[] = $this->combineRow($meta['headers'], $values);
                }
                if ($payloadRows) {
                    $result = $service->importRowsForSite($payloadRows, (string)$task['source_key'], $siteId, false);
                    foreach (['master_created', 'site_created', 'site_updated', 'skipped', 'duplicate_skipped', 'invalid_skipped', 'master_conflicts'] as $key) {
                        $aggregate[$key] += (int)($result[$key] ?? 0);
                    }
                    foreach ((array)($result['conflict_samples'] ?? []) as $sample) {
                        if (count($aggregate['conflict_samples']) < self::CONFLICT_SAMPLE_LIMIT) $aggregate['conflict_samples'][] = $sample;
                    }
                }
                $processed = min($totalRows, $end - $headerRow);
                $this->updateTask($taskId, $siteId, [
                    'processed_rows' => $processed,
                    'created_count' => $aggregate['site_created'],
                    'updated_count' => $aggregate['site_updated'],
                    'skipped_count' => $aggregate['skipped'],
                    'error_count' => $aggregate['invalid_skipped'],
                    'result_json' => json_encode($aggregate, JSON_UNESCAPED_UNICODE),
                    'message' => sprintf('已处理 %d / %d 行', $processed, $totalRows),
                ]);
            }

            $status = $aggregate['invalid_skipped'] > 0 ? 'partial' : 'completed';
            $this->updateTask($taskId, $siteId, [
                'status' => $status, 'processed_rows' => $totalRows,
                'error_count' => $aggregate['invalid_skipped'],
                'message' => $status === 'completed'
                    ? sprintf('商品目录导入完成，重复跳过 %d 条', $aggregate['duplicate_skipped'])
                    : sprintf('导入完成，重复跳过 %d 条，无效数据 %d 条', $aggregate['duplicate_skipped'], $aggregate['invalid_skipped']),
                'finish_at' => time(),
            ]);
            event('HsxErpCatalogChanged', [
                'site_id' => $siteId, 'source_key' => (string)$task['source_key'], 'stats' => $aggregate,
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
            $this->updateTask($taskId, $siteId, ['status' => 'pending', 'queue_enabled' => 0, 'message' => $message]);
            return ['task_id' => $taskId, 'async' => false, 'queue_enabled' => false, 'message' => $message];
        }
        $message = '文件已进入后台导入队列，可以继续其他操作。';
        $this->updateTask($taskId, $siteId, ['status' => 'queued', 'queue_enabled' => 1, 'message' => $message]);
        $pushed = GoodsCatalogImport::dispatch(['taskId' => $taskId, 'siteId' => $siteId]);
        if ($pushed === false) {
            $message = '任务创建成功，但队列推送失败，请检查 Redis 与队列进程后重试。';
            $this->updateTask($taskId, $siteId, ['status' => 'pending', 'message' => $message]);
            return ['task_id' => $taskId, 'async' => false, 'queue_enabled' => true, 'message' => $message];
        }
        return ['task_id' => $taskId, 'async' => true, 'queue_enabled' => true, 'message' => $message];
    }

    private function inspectWorkbook(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) $reader->setReadDataOnly(true);
        $sheet = $reader->listWorksheetInfo($path)[0] ?? [];
        $totalRows = (int)($sheet['totalRows'] ?? 0);
        $lastColumn = (string)($sheet['lastColumnLetter'] ?? 'A');
        if ($totalRows <= 0) throw new CommonException('Excel 工作表为空');
        $preview = $this->readRows($path, 1, min(20, $totalRows), $lastColumn);
        $headerIndex = $this->detectHeaderIndex($preview);
        $headers = array_map(static fn($value) => trim((string)$value), $preview[$headerIndex] ?? []);
        if (!$this->hasRequiredHeaders($headers)) throw new CommonException('表头至少需要包含“品类、品牌、型号”');
        return [
            'sheet_name' => (string)($sheet['worksheetName'] ?? ''), 'total_rows' => $totalRows,
            'last_column' => $lastColumn, 'header_row' => $headerIndex + 1, 'headers' => $headers,
        ];
    }

    private function readRows(string $path, int $startRow, int $endRow, string $lastColumn): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class($startRow, $endRow) implements IReadFilter {
                private int $startRow;
                private int $endRow;
                public function __construct(int $startRow, int $endRow) { $this->startRow = $startRow; $this->endRow = $endRow; }
                public function readCell($columnAddress, $row, $worksheetName = ''): bool { return $row >= $this->startRow && $row <= $this->endRow; }
            });
        }
        $spreadsheet = $reader->load($path);
        $rows = $spreadsheet->getSheet(0)->rangeToArray('A' . $startRow . ':' . $lastColumn . $endRow, '', true, true, false);
        $spreadsheet->disconnectWorksheets();
        return $rows;
    }

    private function detectHeaderIndex(array $rows): int
    {
        $aliases = ['品类', '分类路径', 'category_path', '品牌', 'brand_name', '型号', '产品名称', 'product_name', '产品id', 'source_product_id'];
        $bestIndex = 0;
        $bestScore = -1;
        foreach ($rows as $index => $row) {
            $values = array_map(static fn($value) => mb_strtolower(trim((string)$value)), $row);
            $score = count(array_intersect($values, $aliases));
            if ($score > $bestScore) { $bestScore = $score; $bestIndex = (int)$index; }
        }
        return $bestIndex;
    }

    private function hasRequiredHeaders(array $headers): bool
    {
        $normalized = array_map(static fn($value) => mb_strtolower(trim((string)$value)), $headers);
        foreach ([['品类', '分类路径', 'category_path'], ['品牌', 'brand_name'], ['型号', '产品名称', 'product_name']] as $aliases) {
            if (!array_intersect($normalized, $aliases)) return false;
        }
        return true;
    }

    private function combineRow(array $headers, array $values): array
    {
        $row = [];
        for ($index = 0, $count = max(count($headers), count($values)); $index < $count; $index++) {
            $header = trim((string)($headers[$index] ?? ''));
            if ($header !== '') $row[$header] = trim((string)($values[$index] ?? ''));
        }
        return $row;
    }

    private function hasRowValue(array $row): bool
    {
        foreach ($row as $value) if (trim((string)$value) !== '') return true;
        return false;
    }

    private function updateTask(int $taskId, int $siteId, array $data): void
    {
        $data['update_at'] = time();
        $this->model->where([['site_id', '=', $siteId], ['id', '=', $taskId]])->update($data);
    }

    private function markFailed(int $taskId, int $siteId, string $message): void
    {
        $message = mb_substr($message, 0, 1000);
        $this->updateTask($taskId, $siteId, [
            'status' => 'failed', 'message' => '商品目录导入失败', 'error_message' => $message, 'finish_at' => time(),
        ]);
        Log::error('ERP商品目录导入失败', ['task_id' => $taskId, 'site_id' => $siteId, 'message' => $message]);
    }

    public function formatTask(array $task): array
    {
        $status = (string)($task['status'] ?? 'pending');
        $labels = ['pending' => '待执行', 'queued' => '排队中', 'processing' => '导入中', 'completed' => '已完成', 'partial' => '部分完成', 'failed' => '失败'];
        $types = ['pending' => 'info', 'queued' => 'primary', 'processing' => 'primary', 'completed' => 'success', 'partial' => 'warning', 'failed' => 'danger'];
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
        if ($value === '' || $value === null || $value === 0 || $value === '0') return '-';
        if (is_numeric($value)) return date('Y-m-d H:i:s', (int)$value);
        $timestamp = strtotime((string)$value);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : '-';
    }

    private function absolutePath(string $relativePath): string { return public_path() . ltrim($relativePath, '/'); }

    private function normalizeSourceKey(string $sourceKey): string
    {
        $sourceKey = strtolower(trim($sourceKey));
        return substr(preg_replace('/[^a-z0-9_.-]+/', '_', $sourceKey) ?: 'excel_product_catalog', 0, 40);
    }
}
