<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\model;

use think\Model;

/**
 * 资源映射模型
 */
class ErpIntegrationMapping extends Model
{
    protected $name = 'erp_integration_mapping';
    protected $autoWriteTimestamp = false;

    /**
     * 关联连接
     */
    public function connection()
    {
        return $this->belongsTo(ErpIntegrationConnection::class, 'connection_id', 'id');
    }

    /**
     * 获取资源类型文本
     */
    public function getResourceTypeTextAttr($value, $data): string
    {
        $typeMap = [
            'product' => '商品',
            'warehouse' => '仓库',
            'party' => '往来单位',
            'staff' => '员工',
            'payment_method' => '收付款方式',
        ];
        return $typeMap[$data['resource_type']] ?? $data['resource_type'];
    }

    /**
     * 获取创建时间格式化
     */
    public function getCreatedAtTextAttr($value, $data): string
    {
        return $data['created_at'] ? date('Y-m-d H:i:s', $data['created_at']) : '';
    }

    /**
     * 获取更新时间格式化
     */
    public function getUpdatedAtTextAttr($value, $data): string
    {
        return $data['updated_at'] ? date('Y-m-d H:i:s', $data['updated_at']) : '';
    }
}
