<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 回收导入日志模型
 * Class RecycleImportLog
 * @package addon\recycle\app\model
 */
class RecycleImportLog extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_import_log';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * JSON字段
     * @var array
     */
    protected $json = ['row_data', 'processed_data'];

    /**
     * JSON字段自动转换
     * @var array
     */
    protected $jsonAssoc = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_at';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_at';

    /**
     * 追加属性
     * @var array
     */
    protected $append = [
        'log_type_name',
        'status_name',
        'row_summary'
    ];

    /**
     * 获取日志类型名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getLogTypeNameAttr($value, $data)
    {
        $types = [
            'info' => '信息',
            'warning' => '警告',
            'error' => '错误',
            'success' => '成功',
            'debug' => '调试'
        ];
        return $types[$data['log_type']] ?? '信息';
    }

    /**
     * 获取状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = [
            0 => '处理中',
            1 => '成功',
            2 => '失败',
            3 => '跳过'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 获取行数据摘要（用于列表显示）
     * @param $value
     * @param $data
     * @return string
     */
    public function getRowSummaryAttr($value, $data)
    {
        if (empty($data['row_data'])) {
            return '无数据';
        }
        
        $rowData = is_string($data['row_data']) ? json_decode($data['row_data'], true) : $data['row_data'];
        if (!is_array($rowData)) {
            return '数据格式错误';
        }
        
        $summary = [];
        if (isset($rowData['brand'])) {
            $summary[] = "品牌:{$rowData['brand']}";
        }
        if (isset($rowData['model'])) {
            $summary[] = "型号:{$rowData['model']}";
        }
        if (isset($rowData['capacity'])) {
            $summary[] = "容量:{$rowData['capacity']}";
        }
        
        return implode(' | ', array_slice($summary, 0, 3));
    }

    /**
     * 站点隔离搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('site_id', $value);
        }
    }

    /**
     * 导入记录ID搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchImportRecordIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('import_record_id', $value);
        }
    }

    /**
     * 日志类型搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchLogTypeAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('log_type', $value);
        }
    }

    /**
     * 状态搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }

    /**
     * 工作表名搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSheetNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('sheet_name', 'like', "%{$value}%");
        }
    }

    /**
     * 行号搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchRowNumberAttr($query, $value, $data)
    {
        if (!empty($value)) {
            if (is_array($value) && count($value) == 2) {
                $query->whereBetween('row_number', [$value[0], $value[1]]);
            } else {
                $query->where('row_number', $value);
            }
        }
    }

    /**
     * 关联导入记录
     * @return \think\model\relation\BelongsTo
     */
    public function importRecord()
    {
        return $this->belongsTo(RecyclePriceImportRecord::class, 'import_record_id', 'id');
    }

    /**
     * 添加导入日志
     * @param int $importRecordId
     * @param string $logType
     * @param string $message
     * @param array $extra
     * @return bool
     */
    public function addImportLog(int $importRecordId, string $logType, string $message, array $extra = []): bool
    {
        $data = [
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
        
        return $this->save($data);
    }

    /**
     * 批量添加导入日志
     * @param array $logs
     * @return bool
     */
    public function batchAddImportLogs(array $logs): bool
    {
        if (empty($logs)) {
            return true;
        }
        
        // 添加创建时间
        foreach ($logs as &$log) {
            $log['create_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->saveAll($logs) !== false;
    }

    /**
     * 记录处理步骤
     * @param int $importRecordId
     * @param string $step
     * @param string $message
     * @param array $data
     * @return bool
     */
    public function logProcessStep(int $importRecordId, string $step, string $message, array $data = []): bool
    {
        return $this->addImportLog($importRecordId, 'info', "[$step] $message", $data);
    }

    /**
     * 记录错误
     * @param int $importRecordId
     * @param string $message
     * @param array $extra
     * @return bool
     */
    public function logError(int $importRecordId, string $message, array $extra = []): bool
    {
        $extra['status'] = 2;
        return $this->addImportLog($importRecordId, 'error', $message, $extra);
    }

    /**
     * 记录警告
     * @param int $importRecordId
     * @param string $message
     * @param array $extra
     * @return bool
     */
    public function logWarning(int $importRecordId, string $message, array $extra = []): bool
    {
        $extra['status'] = 3;
        return $this->addImportLog($importRecordId, 'warning', $message, $extra);
    }

    /**
     * 记录成功
     * @param int $importRecordId
     * @param string $message
     * @param array $extra
     * @return bool
     */
    public function logSuccess(int $importRecordId, string $message, array $extra = []): bool
    {
        $extra['status'] = 1;
        return $this->addImportLog($importRecordId, 'success', $message, $extra);
    }

    /**
     * 获取导入日志列表
     * @param array $condition
     * @param int $page
     * @param int $page_size
     * @param string $order
     * @param string $field
     * @return array
     */
    public function getImportLogList(array $condition = [], int $page = 1, int $page_size = 10, string $order = 'id desc', string $field = '*'): array
    {
        $data = $this->where($condition)
            ->field($field)
            ->order($order)
            ->page($page, $page_size)
            ->select()
            ->toArray();

        $count = $this->where($condition)->count();
        return ['list' => $data, 'count' => $count];
    }

    /**
     * 获取导入统计
     * @param int $importRecordId
     * @return array
     */
    public function getImportLogStatistics(int $importRecordId): array
    {
        $total = $this->where('import_record_id', $importRecordId)->count();
        $success = $this->where(['import_record_id' => $importRecordId, 'status' => 1])->count();
        $failed = $this->where(['import_record_id' => $importRecordId, 'status' => 2])->count();
        $skipped = $this->where(['import_record_id' => $importRecordId, 'status' => 3])->count();
        
        $errorTypes = $this->where(['import_record_id' => $importRecordId, 'log_type' => 'error'])
            ->group('error_code')
            ->column('count(*) as count, error_code');
        
        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            'error_types' => $errorTypes
        ];
    }

    /**
     * 清理旧日志
     * @param int $days
     * @return int
     */
    public function cleanOldLogs(int $days = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->where('create_at', '<', $cutoffDate)->delete();
    }

    /**
     * 获取错误摘要
     * @param int $importRecordId
     * @return array
     */
    public function getErrorSummary(int $importRecordId): array
    {
        $errors = $this->where([
            'import_record_id' => $importRecordId,
            'log_type' => 'error'
        ])->field('error_code,message,count(*) as count')
            ->group('error_code,message')
            ->order('count desc')
            ->select()
            ->toArray();
        
        return $errors;
    }

    /**
     * 删除日志
     * @param int $id
     * @return bool
     */
    public function deleteLog(int $id)
    {
        return $this->destroy($id);
    }

    /**
     * 获取日志信息
     * @param int $id
     * @return array
     */
    public function getLogInfo(int $id)
    {
        $field = 'id,site_id,import_record_id,log_type,message,sheet_name,row_number,row_data,processed_data,status,error_code,create_at';
        return $this->field($field)->where(['id' => $id])->findOrEmpty()->toArray();
    }
} 