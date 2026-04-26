<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;
use app\model\sys\SysUser;

/**
 * 回收价格导入记录模型
 * Class RecyclePriceImportRecord
 * @package addon\recycle\app\model
 */
class RecyclePriceImportRecord extends BaseModel
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
    protected $name = 'recycle_price_import_record';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * JSON字段
     * @var array
     */
    protected $json = ['import_summary', 'error_details'];

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
        'status_name',
        'import_type_name',
        'operator_name',
        'import_summary_text'
    ];

    /**
     * 获取状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = [
            0 => '导入中',
            1 => '导入成功',
            2 => '导入失败',
            3 => '部分成功'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 获取导入类型名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getImportTypeNameAttr($value, $data)
    {
        $types = [
            'excel' => 'Excel导入',
            'csv' => 'CSV导入',
            'api' => 'API导入'
        ];
        return $types[$data['import_type']] ?? 'Excel导入';
    }

    /**
     * 获取操作员名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getOperatorNameAttr($value, $data)
    {
        if (isset($data['operator']) && $data['operator']) {
            return $data['operator']['username'] ?? '系统';
        }
        return '系统';
    }

    /**
     * 获取导入摘要文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getImportSummaryTextAttr($value, $data)
    {
        if (empty($data['import_summary'])) {
            return '暂无摘要';
        }
        
        $summary = is_string($data['import_summary']) ? json_decode($data['import_summary'], true) : $data['import_summary'];
        if (!is_array($summary)) {
            return '数据格式错误';
        }
        
        $text = [];
        if (isset($summary['total_rows'])) {
            $text[] = "总行数:{$summary['total_rows']}";
        }
        // if (isset($summary['success_count'])) {
        //     $text[] = "成功:{$summary['success_count']}";
        // }
        if (isset($summary['failed_count'])) {
            $text[] = "失败:{$summary['failed_count']}";
        }
        if (isset($summary['new_devices'])) {
            $text[] = "新设备:{$summary['new_devices']}";
        }
        
        return implode(' | ', $text);
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
     * 导入类型搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchImportTypeAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('import_type', $value);
        }
    }

    /**
     * 操作员搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchOperatorIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('operator_id', $value);
        }
    }

    /**
     * 导入时间搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCreateAtAttr($query, $value, $data)
    {
        if (!empty($value) && is_array($value) && count($value) == 2) {
            $query->whereBetweenTime('create_at', $value[0], $value[1]);
        }
    }

    /**
     * 关联操作员
     * @return \think\model\relation\BelongsTo
     */
    public function operator()
    {
        return $this->belongsTo(SysUser::class, 'operator_id', 'uid')->field('uid,username,real_name');
    }

    /**
     * 关联导入日志
     * @return \think\model\relation\HasMany
     */
    public function importLogs()
    {
        return $this->hasMany(RecycleImportLog::class, 'import_record_id', 'id');
    }

    /**
     * 关联价格记录
     * @return \think\model\relation\HasMany
     */
    public function prices()
    {
        return $this->hasMany(RecycleDevicePrice::class, 'import_record_id', 'id');
    }

    /**
     * 创建导入记录
     * @param array $data
     * @return mixed
     */
    public function createImportRecord(array $data)
    {
        $data['status'] = 0; // 导入中
        $data['import_type'] = $data['import_type'] ?? 'excel';
        $data['start_time'] = date('Y-m-d H:i:s');
        
        return $this->save($data);
    }

    /**
     * 更新导入进度
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateImportProgress(int $id, array $data)
    {
        return $this->where('id', $id)->update($data) > 0;
    }

    /**
     * 完成导入
     * @param int $id
     * @param int $status
     * @param array $summary
     * @param array $errorDetails
     * @return bool
     */
    public function completeImport(int $id, int $status, array $summary = [], array $errorDetails = [])
    {
        $updateData = [
            'status' => $status,
            // 'end_time' => date('Y-m-d H:i:s'),
            // 'import_summary' => $summary,
            // 'error_details' => $errorDetails
        ];
        
        return $this->where('id', $id)->update($updateData) > 0;
    }

    /**
     * 获取导入记录列表
     * @param array $condition
     * @param int $page
     * @param int $page_size
     * @param string $order
     * @param string $field
     * @return array
     */
    public function getImportRecordList(array $condition = [], int $page = 1, int $page_size = 10, string $order = 'id desc', string $field = '*'): array
    {
        $data = $this->where($condition)
            ->field($field)
            ->with(['operator' => function($query) {
                $query->field('uid,username,real_name');
            }])
            ->order($order)
            ->page($page, $page_size)
            ->select()
            ->toArray();

        $count = $this->where($condition)->count();
        return ['list' => $data, 'count' => $count];
    }

    /**
     * 获取导入记录信息
     * @param int $id
     * @return array
     */
    public function getImportRecordInfo(int $id)
    {
        $field = 'id,site_id,file_name,file_path,import_type,status,operator_id,start_time,end_time,total_rows,success_count,failed_count,import_summary,error_details,create_at,update_at';
        return $this->field($field)
            ->where(['id' => $id])
            ->with(['operator', 'importLogs'])
            ->findOrEmpty()
            ->toArray();
    }

    /**
     * 删除导入记录
     * @param int $id
     * @return bool
     */
    public function deleteImportRecord(int $id)
    {
        // 开启事务，同时删除相关的日志和价格记录
        $this->startTrans();
        try {
            // 删除相关的导入日志
            (new RecycleImportLog())->where('import_record_id', $id)->delete();
            
            // 删除导入记录（价格记录保留，只清空关联）
            (new RecycleDevicePrice())->where('import_record_id', $id)->update(['import_record_id' => 0]);
            
            // 删除主记录
            $result = $this->destroy($id);
            
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * 获取导入统计
     * @param int $siteId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getImportStatistics(int $siteId, string $startDate = '', string $endDate = '')
    {
        $where = ['site_id' => $siteId];
        
        if ($startDate && $endDate) {
            $where[] = ['create_at', 'between', [$startDate, $endDate]];
        }
        
        $total = $this->where($where)->count();
        $success = $this->where($where)->where('status', 1)->count();
        $failed = $this->where($where)->where('status', 2)->count();
        $partial = $this->where($where)->where('status', 3)->count();
        
        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'partial' => $partial,
            'success_rate' => $total > 0 ? round(($success + $partial) / $total * 100, 2) : 0
        ];
    }
} 