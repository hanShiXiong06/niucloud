<?php
declare(strict_types=1);

namespace addon\hsx_erp_integration\app\model;

use think\Model;

/**
 * 外部ERP连接配置模型
 */
class ErpIntegrationConnection extends Model
{
    protected $name = 'erp_integration_connection';
    protected $hidden = ['config'];
    protected $autoWriteTimestamp = false;

    protected $type = [
        'business_types' => 'json',
        'config' => 'json',
    ];

    /**
     * 获取适配器显示名称
     */
    public function getAdapterDisplayNameAttr($value, $data): string
    {
        return $data['adapter_name'] ?: $data['adapter_code'];
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            'enabled' => '已启用',
            'disabled' => '已禁用',
            'testing' => '测试中',
        ];
        return $statusMap[$data['status']] ?? $data['status'];
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
