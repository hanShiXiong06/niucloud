<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core;

use addon\recycle\app\model\RecycleImportLog;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Log;

/**
 * 导入日志服务
 * Class ImportLogService
 * @package addon\recycle\app\service\core
 */
class ImportLogService extends BaseCoreService
{
    /**
     * @var int
     */
    protected $site_id = 0;

    /**
     * @var RecycleImportLog
     */
    protected $logModel;

    /**
     * 批量日志缓存
     * @var array
     */
    protected $batchLogs = [];

    /**
     * 批量大小
     * @var int
     */
    protected $batchSize = 100;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->logModel = new RecycleImportLog();
    }

    /**
     * 记录处理步骤日志
     * @param int $importRecordId
     * @param string $step
     * @param string $message
     * @param array $data
     * @return bool
     */
    public function logProcessStep(int $importRecordId, string $step, string $message, array $data = []): bool
    {
        return $this->addLog($importRecordId, 'info', "[$step] $message", array_merge($data, [
            'step' => $step
        ]));
    }

    /**
     * 记录成功日志
     * @param int $importRecordId
     * @param string $message
     * @param array $extra
     * @return bool
     */
    public function logSuccess(int $importRecordId, string $message, array $extra = []): bool
    {
        $extra['status'] = 1;
        return $this->addLog($importRecordId, 'success', $message, $extra);
    }

    /**
     * 记录警告日志
     * @param int $importRecordId
     * @param string $message
     * @param array $extra
     * @return bool
     */
    public function logWarning(int $importRecordId, string $message, array $extra = []): bool
    {
        $extra['status'] = 3;
        return $this->addLog($importRecordId, 'warning', $message, $extra);
    }

    /**
     * 记录错误日志
     * @param int $importRecordId
     * @param string $message
     * @param array $extra
     * @return bool
     */
    public function logError(int $importRecordId, string $message, array $extra = []): bool
    {
        $extra['status'] = 2;
        return $this->addLog($importRecordId, 'error', $message, $extra);
    }

    /**
     * 添加日志记录
     * @param int $importRecordId
     * @param string $logType
     * @param string $message
     * @param array $extra
     * @return bool
     */
    protected function addLog(int $importRecordId, string $logType, string $message, array $extra = []): bool
    {
        try {
            $logData = [
                'site_id' => $extra['site_id'] ?? 1,
                'import_record_id' => $importRecordId,
                'log_type' => $logType,
                'message' => $message,
                'sheet_name' => $extra['sheet_name'] ?? '',
                'row_number' => $extra['row_number'] ?? 0,
                'row_data' => $extra['row_data'] ?? [],
                'processed_data' => $extra['processed_data'] ?? [],
                'status' => $extra['status'] ?? 1,
                'error_code' => $extra['error_code'] ?? '',
                'create_at' => date('Y-m-d H:i:s')
            ];

            // 立即写入数据库
            return $this->logModel->save($logData);

        } catch (\Exception $e) {
            // 日志记录失败时写入系统日志
            Log::error('导入日志记录失败：' . $e->getMessage() . ' | 原日志：' . $message);
            return false;
        }
    }

    /**
     * 批量添加日志（用于性能优化）
     * @param int $importRecordId
     * @param array $logs
     * @return bool
     */
    public function batchAddLogs(int $importRecordId, array $logs): bool
    {
        try {
            $formattedLogs = [];
            
            foreach ($logs as $log) {
                $formattedLogs[] = [
                    'site_id' => $log['site_id'] ?? 1,
                    'import_record_id' => $importRecordId,
                    'log_type' => $log['log_type'] ?? 'info',
                    'message' => $log['message'] ?? '',
                    'sheet_name' => $log['sheet_name'] ?? '',
                    'row_number' => $log['row_number'] ?? 0,
                    'row_data' => $log['row_data'] ?? [],
                    'processed_data' => $log['processed_data'] ?? [],
                    'status' => $log['status'] ?? 1,
                    'error_code' => $log['error_code'] ?? '',
                    'create_at' => date('Y-m-d H:i:s')
                ];
            }

            return $this->logModel->batchAddImportLogs($formattedLogs);

        } catch (\Exception $e) {
            Log::error('批量导入日志记录失败：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取导入统计信息
     * @param int $importRecordId
     * @return array
     */
    public function getImportLogStatistics(int $importRecordId): array
    {
        try {
            $stats = $this->logModel->getImportLogStatistics($importRecordId);
            return $stats;
        } catch (\Exception $e) {
            Log::error('获取导入统计失败：' . $e->getMessage());
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 0,
                'success_rate' => 0,
                'error_types' => []
            ];
        }
    }

    /**
     * 记录文件验证日志
     * @param int $importRecordId
     * @param array $fileInfo
     * @param bool $isValid
     * @param string $message
     * @return bool
     */
    public function logFileValidation(int $importRecordId, array $fileInfo, bool $isValid, string $message = ''): bool
    {
        $logType = $isValid ? 'success' : 'error';
        $status = $isValid ? 1 : 2;
        
        return $this->addLog($importRecordId, $logType, $message ?: ($isValid ? '文件验证通过' : '文件验证失败'), [
            'status' => $status,
            'error_code' => $isValid ? '' : 'FILE_VALIDATION_ERROR',
            'processed_data' => $fileInfo
        ]);
    }

    /**
     * 记录容量格式化日志
     * @param int $importRecordId
     * @param string $sheetName
     * @param int $rowNumber
     * @param string $originalCapacity
     * @param string $standardizedCapacity
     * @return bool
     */
    public function logCapacityStandardization(int $importRecordId, string $sheetName, int $rowNumber, string $originalCapacity, string $standardizedCapacity): bool
    {
        if ($originalCapacity === $standardizedCapacity) {
            return true; // 无需记录
        }
        
        return $this->logProcessStep($importRecordId, '容量标准化', "容量格式标准化：{$originalCapacity} → {$standardizedCapacity}", [
            'sheet_name' => $sheetName,
            'row_number' => $rowNumber,
            'processed_data' => [
                'original_capacity' => $originalCapacity,
                'standardized_capacity' => $standardizedCapacity
            ]
        ]);
    }

    /**
     * 记录数据验证错误
     * @param int $importRecordId
     * @param string $sheetName
     * @param int $rowNumber
     * @param array $rowData
     * @param array $errors
     * @return bool
     */
    public function logValidationError(int $importRecordId, string $sheetName, int $rowNumber, array $rowData, array $errors): bool
    {
        return $this->logError($importRecordId, '数据验证失败：' . implode('; ', $errors), [
            'sheet_name' => $sheetName,
            'row_number' => $rowNumber,
            'error_code' => 'DATA_VALIDATION_ERROR',
            'row_data' => $rowData,
            'processed_data' => ['errors' => $errors]
        ]);
    }

    /**
     * 清理旧日志
     * @param int $days
     * @return array
     */
    public function cleanOldLogs(int $days = 30): array
    {
        try {
            $deletedCount = $this->logModel->cleanOldLogs($days);
            return success("清理完成，删除了 {$deletedCount} 条旧日志");
        } catch (\Exception $e) {
            return fail('清理旧日志失败：' . $e->getMessage());
        }
    }

    /**
     * 设置批量大小
     * @param int $size
     * @return void
     */
    public function setBatchSize(int $size): void
    {
        $this->batchSize = max(1, $size);
    }
}