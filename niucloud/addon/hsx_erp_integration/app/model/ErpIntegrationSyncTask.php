<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\model;

use think\Model;

/**
 * 同步任务模型
 */
class ErpIntegrationSyncTask extends Model
{
    protected $name = 'erp_integration_sync_task';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'payload' => 'json',
    ];

    /**
     * 关联连接配置
     */
    public function connection()
    {
        return $this->belongsTo(ErpIntegrationConnection::class, 'connection_id', 'id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            'pending' => '待处理',
            'processing' => '处理中',
            'succeeded' => '已成功',
            'failed' => '失败',
        ];
        return $statusMap[$data['status']] ?? $data['status'];
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
     * 获取创建时间格式化
     */
    public function getCreatedAtTextAttr($value, $data): string
    {
        return $data['created_at'] ? date('Y-m-d H:i:s', $data['created_at']) : '';
    }

    /**
     * 获取成功时间格式化
     */
    public function getSucceededAtTextAttr($value, $data): string
    {
        return $data['succeeded_at'] ? date('Y-m-d H:i:s', $data['succeeded_at']) : '';
    }
}
