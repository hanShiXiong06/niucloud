<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\express;

use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\job\ExpressProductImport;
use addon\hsx_recycle\app\service\core\express\ExpressProductCatalogService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 快递产品 Excel 导入任务。
 *
 * 任务状态保存在 sys_config，仅保留最近 20 条，避免为低频配置任务新增表。
 */
class ExpressProductImportTaskService extends BaseAdminService
{
    private const IMPORT_DIR = 'upload/hsx_recycle/express_product_import/';
    private const CHUNK_SIZE = 500;
    private const TASK_LIMIT = 20;
    private const ERROR_LIMIT = 30;

    private CoreConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new CoreConfigService();
    }

    public function upload($file, string $provider = ThirdPartyDict::PROVIDER_YISU): array
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

        $provider = $this->normalizeProvider($provider);
        $originalName = (string)$file->getOriginalName();
        $relativeDir = self::IMPORT_DIR . date('Y/m/d') . '/';
        $saveDir = public_path() . $relativeDir;
        if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
            throw new CommonException('无法创建导入文件目录');
        }
        $storedName = uniqid('express_product_', true) . '.' . $extension;
        $file->move($saveDir, $storedName);

        $now = time();
        $taskId = date('YmdHis', $now) . bin2hex(random_bytes(4));
        $task = [
            'task_id' => $taskId,
            'site_id' => (int)$this->site_id,
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'provider' => $provider,
            'file_name' => $originalName,
            'file_path' => $relativeDir . $storedName,
            'sheet_name' => '',
            'status' => 'pending',
            'queue_enabled' => env('queue.state', false) ? 1 : 0,
            'total_rows' => 0,
            'processed_rows' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'errors' => [],
            'message' => '文件已上传，等待进入导入队列',
            'error_message' => '',
            'start_at' => 0,
            'finish_at' => 0,
            'create_at' => $now,
            'update_at' => $now,
        ];
        $this->putTask((int)$this->site_id, $task);

        return $this->dispatchTask($taskId, (int)$this->site_id);
    }

    public function getTask(string $taskId): array
    {
        $task = $this->findTask((int)$this->site_id, $taskId);
        if (empty($task)) {
            throw new CommonException('导入任务不存在');
        }
        return $this->formatTask($task);
    }

    public function getTasks(): array
    {
        $config = $this->getTaskConfig((int)$this->site_id);
        $tasks = [];
        foreach ((array)($config['task_ids'] ?? []) as $taskId) {
            $task = $this->findTask((int)$this->site_id, (string)$taskId);
            if (!empty($task)) {
                $tasks[] = $task;
            }
        }
        usort($tasks, static fn(array $left, array $right): int => (int)$right['create_at'] <=> (int)$left['create_at']);
        return array_map([$this, 'formatTask'], $tasks);
    }

    /**
     * 生成与导入解析规则完全一致的模板，避免用户靠说明猜列名。
     */
    public function generateTemplate(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('快递产品');

        $headers = ['服务商', '分类路径', '产品编号', '产品名称', '图标', '排序'];
        $exampleRows = [
            ['yisu', '快递/常用快递', '1', '申通快递', '', 10],
            ['yisu', '重货/重货物流', '21', '德邦物流（卡航）', '', 20],
        ];

        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);
            $sheet->setCellValue($column . '1', $header);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        foreach ($exampleRows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueExplicit(
                    chr(65 + $columnIndex) . (string)($rowIndex + 2),
                    (string)$value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
        }

        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF3B82F6');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F3');

        $spreadsheet->createSheet();
        $helpSheet = $spreadsheet->setActiveSheetIndex(1);
        $helpSheet->setTitle('填写说明');
        $instructions = [
            ['字段', '说明'],
            ['产品编号', '必填，同一服务商下不可重复；重复编号导入时自动跳过'],
            ['产品名称', '必填，展示给业务人员选择'],
            ['服务商', '可选，默认使用当前页面服务商，例如 yisu'],
            ['分类路径', '可选，使用 / 分隔，例如 快递/常用快递'],
            ['图标', '可选，填写可访问的图片地址'],
            ['排序', '可选，数字越小越靠前'],
            ['导入结果', '新增产品默认停用；导入完成后需在页面启用并保存'],
        ];
        foreach ($instructions as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $helpSheet->setCellValue(chr(65 + $columnIndex) . (string)($rowIndex + 1), $value);
            }
        }
        $helpSheet->getColumnDimension('A')->setWidth(18);
        $helpSheet->getColumnDimension('B')->setWidth(72);
        $helpSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $spreadsheet->setActiveSheetIndex(0);

        $directory = runtime_path() . 'temp' . DIRECTORY_SEPARATOR . 'hsx_recycle';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new CommonException('无法创建模板临时目录');
        }
        $filePath = $directory . DIRECTORY_SEPARATOR . 'express_product_import_template.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);
        $spreadsheet->disconnectWorksheets();

        return $filePath;
    }

    public function retry(string $taskId): array
    {
        $task = $this->getTask($taskId);
        if ($task['status'] === 'processing') {
            throw new CommonException('任务正在执行，请勿重复提交');
        }
        if (!is_file($this->absolutePath((string)$task['file_path']))) {
            throw new CommonException('原始 Excel 文件已失效，请重新上传');
        }
        $this->patchTask((int)$this->site_id, $taskId, [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'errors' => [],
            'error_message' => '',
            'finish_at' => 0,
            'message' => '等待重新进入导入队列',
        ]);
        return $this->dispatchTask($taskId, (int)$this->site_id);
    }

    public function runTask(string $taskId, int $siteId): void
    {
        $task = $this->findTask($siteId, $taskId);
        if (empty($task)) {
            throw new CommonException('快递产品导入任务不存在');
        }
        if ($task['status'] === 'completed') {
            return;
        }
        $path = $this->absolutePath((string)$task['file_path']);
        if (!is_file($path)) {
            $this->markFailed($siteId, $taskId, '原始 Excel 文件不存在');
            throw new CommonException('原始 Excel 文件不存在');
        }

        $this->patchTask($siteId, $taskId, [
            'status' => 'processing',
            'message' => '正在解析 Excel 产品目录',
            'start_at' => time(),
        ]);

        try {
            $meta = $this->inspectWorkbook($path);
            $totalRows = max(0, (int)$meta['total_rows'] - (int)$meta['header_row']);
            if ($totalRows <= 0) {
                throw new CommonException('Excel 中没有可导入数据');
            }
            $this->patchTask($siteId, $taskId, [
                'sheet_name' => (string)$meta['sheet_name'],
                'total_rows' => $totalRows,
                'message' => '正在分批读取产品目录',
            ]);

            $rowsForImport = [];
            $errors = [];
            $invalidCount = 0;
            $processed = 0;
            $dataStart = (int)$meta['header_row'] + 1;
            $dataEnd = (int)$meta['total_rows'];
            for ($start = $dataStart; $start <= $dataEnd; $start += self::CHUNK_SIZE) {
                $end = min($dataEnd, $start + self::CHUNK_SIZE - 1);
                $rows = $this->readRows($path, $start, $end, (string)$meta['last_column']);
                foreach ($rows as $offset => $values) {
                    $line = $start + $offset;
                    if (!$this->hasRowValue($values)) {
                        continue;
                    }
                    $row = $this->combineRow((array)$meta['headers'], $values);
                    $normalized = $this->normalizeImportRow($row, (string)$task['provider']);
                    if ($normalized['product_code'] === '' || $normalized['product_name'] === '') {
                        $invalidCount++;
                        if (count($errors) < self::ERROR_LIMIT) {
                            $errors[] = ['line' => $line, 'reason' => '产品编号或产品名称为空'];
                        }
                        continue;
                    }
                    $rowsForImport[] = $normalized;
                }

                $processed = min($totalRows, $end - (int)$meta['header_row']);
                $this->patchTask($siteId, $taskId, [
                    'processed_rows' => $processed,
                    'skipped_count' => $invalidCount,
                    'error_count' => $invalidCount,
                    'errors' => $errors,
                    'message' => sprintf('已读取 %d / %d 行', $processed, $totalRows),
                ]);
            }

            $result = (new ExpressProductCatalogService())->importProducts(
                $siteId,
                $rowsForImport,
                (string)$task['provider']
            );
            foreach ((array)($result['skipped'] ?? []) as $item) {
                if (count($errors) >= self::ERROR_LIMIT) {
                    break;
                }
                $errors[] = $item;
            }
            $created = (int)($result['created_count'] ?? 0);
            $skipped = $invalidCount + (int)($result['skipped_count'] ?? 0);
            $status = $skipped > 0 ? 'partial' : 'completed';
            $this->patchTask($siteId, $taskId, [
                'status' => $status,
                'processed_rows' => $totalRows,
                'created_count' => $created,
                'skipped_count' => $skipped,
                'error_count' => $skipped,
                'errors' => $errors,
                'message' => $status === 'completed' ? '导入完成，请选择需要启用的产品' : '导入完成，重复或无效数据已跳过',
                'finish_at' => time(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($siteId, $taskId, $e->getMessage());
            throw $e;
        }
    }

    private function dispatchTask(string $taskId, int $siteId): array
    {
        if (!env('queue.state', false)) {
            $message = '导入任务已创建，但当前队列未启用；开启队列后点击“重试”即可执行。';
            $this->patchTask($siteId, $taskId, [
                'status' => 'pending',
                'queue_enabled' => 0,
                'message' => $message,
            ]);
            return ['task_id' => $taskId, 'async' => false, 'queue_enabled' => false, 'message' => $message];
        }

        $message = '文件已进入后台导入队列，可以继续其他操作。';
        $this->patchTask($siteId, $taskId, [
            'status' => 'queued',
            'queue_enabled' => 1,
            'message' => $message,
        ]);
        $pushed = ExpressProductImport::dispatch(['taskId' => $taskId, 'siteId' => $siteId]);
        if ($pushed === false) {
            $message = '队列推送失败，请检查 Redis 与队列进程后重试。';
            $this->patchTask($siteId, $taskId, ['status' => 'pending', 'message' => $message]);
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
        $preview = $this->readRows($path, 1, min($totalRows, 20), $lastColumn);
        $headerIndex = $this->detectHeaderIndex($preview);
        $headers = array_map([$this, 'normalizeHeader'], $preview[$headerIndex] ?? []);
        if (!$this->hasRequiredHeaders($headers)) {
            throw new CommonException('表头至少需要包含“产品编号、产品名称”');
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
        $rows = $spreadsheet->getActiveSheet()->rangeToArray(
            'A' . $startRow . ':' . $lastColumn . $endRow,
            null,
            true,
            false
        );
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        return $rows;
    }

    private function detectHeaderIndex(array $rows): int
    {
        foreach ($rows as $index => $row) {
            $headers = array_map([$this, 'normalizeHeader'], $row);
            if ($this->hasRequiredHeaders($headers)) {
                return (int)$index;
            }
        }
        return 0;
    }

    private function hasRequiredHeaders(array $headers): bool
    {
        $codeAliases = ['产品编号', '产品代码', 'productcode', 'code'];
        $nameAliases = ['产品名称', '快递产品', 'productname', 'name'];
        return !empty(array_intersect($headers, $codeAliases)) && !empty(array_intersect($headers, $nameAliases));
    }

    private function combineRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $row[$header] = $values[$index] ?? null;
            }
        }
        return $row;
    }

    private function normalizeImportRow(array $row, string $defaultProvider): array
    {
        $value = static function (array $aliases) use ($row) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $row)) {
                    return $row[$alias];
                }
            }
            return '';
        };
        $statusValue = strtolower(trim((string)$value(['启用', '状态', 'enabled', 'status'])));
        return [
            'provider' => $this->normalizeProvider((string)($value(['服务商', 'provider']) ?: $defaultProvider)),
            'product_code' => trim((string)$value(['产品编号', '产品代码', 'productcode', 'code'])),
            'product_name' => trim((string)$value(['产品名称', '快递产品', 'productname', 'name'])),
            'category_path' => trim((string)($value(['分类路径', '分类', '产品类型', '类型', 'categorypath', 'category']) ?: '其他')),
            'logo' => trim((string)$value(['图标', 'logo'])),
            'status' => in_array($statusValue, ['1', '是', '启用', 'true', 'yes'], true) ? 1 : 0,
            'sort' => max(0, (int)$value(['排序', 'sort'])),
            'source' => 'excel',
        ];
    }

    private function normalizeHeader($value): string
    {
        $header = strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string)$value)));
        return str_replace([' ', '_', '-'], '', $header);
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

    private function getTaskConfig(int $siteId): array
    {
        $config = $this->configService->getConfigValue($siteId, RecycleConfigKeyDict::EXPRESS_PRODUCT_IMPORT);
        return is_array($config) ? $config : ['schema_version' => 1, 'task_ids' => []];
    }

    private function findTask(int $siteId, string $taskId): array
    {
        $task = $this->configService->getConfigValue($siteId, $this->taskConfigKey($taskId));
        return is_array($task) ? $task : [];
    }

    private function putTask(int $siteId, array $task): void
    {
        $taskId = (string)$task['task_id'];
        $this->configService->setConfig($siteId, $this->taskConfigKey($taskId), $task);

        $config = $this->getTaskConfig($siteId);
        $config['schema_version'] = 1;
        $taskIds = array_values(array_filter(
            (array)($config['task_ids'] ?? []),
            static fn($item): bool => (string)$item !== $taskId
        ));
        array_unshift($taskIds, $taskId);
        $removed = array_slice($taskIds, self::TASK_LIMIT);
        $config['task_ids'] = array_slice($taskIds, 0, self::TASK_LIMIT);
        $config['updated_at'] = time();
        $this->configService->setConfig($siteId, RecycleConfigKeyDict::EXPRESS_PRODUCT_IMPORT, $config);
        foreach ($removed as $removedTaskId) {
            $this->configService->clearConfig($siteId, $this->taskConfigKey((string)$removedTaskId));
        }
    }

    private function patchTask(int $siteId, string $taskId, array $patch): void
    {
        $task = $this->findTask($siteId, $taskId);
        if (empty($task)) {
            return;
        }
        $this->putTask($siteId, array_merge($task, $patch, ['update_at' => time()]));
    }

    private function markFailed(int $siteId, string $taskId, string $message): void
    {
        $this->patchTask($siteId, $taskId, [
            'status' => 'failed',
            'message' => '导入失败',
            'error_message' => mb_substr($message, 0, 1000),
            'finish_at' => time(),
        ]);
    }

    private function formatTask(array $task): array
    {
        $total = max(0, (int)($task['total_rows'] ?? 0));
        $processed = max(0, (int)($task['processed_rows'] ?? 0));
        $task['progress'] = $total > 0 ? min(100, (int)round($processed * 100 / $total)) : 0;
        return $task;
    }

    private function absolutePath(string $relativePath): string
    {
        return public_path() . ltrim($relativePath, '/');
    }

    private function taskConfigKey(string $taskId): string
    {
        return RecycleConfigKeyDict::EXPRESS_PRODUCT_IMPORT . ':' . $taskId;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        return $provider !== '' ? $provider : ThirdPartyDict::PROVIDER_YISU;
    }
}
