<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\model;

use think\Model;

/**
 * 同步失败记录模型
 */
class ErpIntegrationFailure extends Model
{
    protected $name = 'erp_integration_failure';
    protected $autoWriteTimestamp = false;

    /**
     * 关联任务
     */
    public function task()
    {
        return $this->belongsTo(ErpIntegrationSyncTask::class, 'task_id', 'id');
    }

    /**
     * 关联连接
     */
    public function connection()
    {
        return $this->belongsTo(ErpIntegrationConnection::class, 'connection_id', 'id');
    }

    /**
     * 获取失败类型文本
     */
    public function getFailureTypeTextAttr($value, $data): string
    {
        $typeMap = [
            'mapping_missing' => '映射缺失',
            'api_error' => 'API错误',
            'timeout' => '超时',
            'validation_error' => '验证错误',
        ];
        return $typeMap[$data['failure_type']] ?? $data['failure_type'];
    }

    /**
     * 获取业务类型文本
     */
    public function getBusinessTypeTextAttr($value, $data): string
    {
        $typeMap = [
            'purchase' => '采购入库',
            'sale' => '销售出库',
            'payment' => '付款',
            'receipt' => '收款',
            'return' => '退货',
        ];
        return $typeMap[$data['business_type']] ?? $data['business_type'];
    }

    /**
     * 获取解决状态文本
     */
    public function getResolvedTextAttr($value, $data): string
    {
        return $data['resolved'] ? '已解决' : '待处理';
    }

    /**
     * 获取创建时间格式化
     */
    public function getCreatedAtTextAttr($value, $data): string
    {
        return $data['created_at'] ? date('Y-m-d H:i:s', $data['created_at']) : '';
    }

    /**
     * 获取解决时间格式化
     */
    public function getResolvedAtTextAttr($value, $data): string
    {
        return $data['resolved_at'] ? date('Y-m-d H:i:s', $data['resolved_at']) : '';
    }
}
