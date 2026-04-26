<?php
declare(strict_types=1);

namespace addon\recycle\app\dict\quotation;

/**
 * 报价相关字典
 * Class QuotationDict
 * @package addon\recycle\app\dict\quotation
 */
class QuotationDict
{
    // ========== 请求状态 ==========
    const REQUEST_STATUS_PENDING = 0;    // 待请求
    const REQUEST_STATUS_SUCCESS = 1;    // 请求成功
    const REQUEST_STATUS_FAILED = 2;     // 请求失败
    
    // ========== 通用状态 ==========
    const STATUS_DISABLED = 0;           // 禁用
    const STATUS_ENABLED = 1;            // 启用
    
    // ========== 配置类型 ==========
    const CONFIG_TYPE_SKU = 1;                    // SKU级别：型号+内存+配置项
    const CONFIG_TYPE_MODEL_CAPACITY = 2;         // 批量管理：型号+内存
    const CONFIG_TYPE_MODEL = 3;                  // 按型号配置
    const CONFIG_TYPE_GROUP = 4;                   // 按分组配置
    
    // ========== 调整方式 ==========
    const ADJUSTMENT_TYPE_FIXED = 1;     // 固定金额调整
    const ADJUSTMENT_TYPE_PERCENT = 2;   // 百分比调整
    const ADJUSTMENT_TYPE_COVER = 3;     // 固定价格覆盖
    
    // ========== 价格状态 ==========
    const PRICE_STATUS_NORMAL = 0;       // 正常（黑色）
    const PRICE_STATUS_HIGH = 1;         // 高于大盘（红色）
    const PRICE_STATUS_LOW = 2;          // 低于大盘（绿色）
    
    // ========== 是否当前价格 ==========
    const IS_CURRENT_NO = 0;             // 否
    const IS_CURRENT_YES = 1;            // 是
    
    // ========== 自动请求 ==========
    const AUTO_REQUEST_NO = 0;           // 否
    const AUTO_REQUEST_YES = 1;          // 是
    
    /**
     * 请求状态字典
     * @param int|null $status
     * @return array
     */
    public static function getRequestStatusDict(?int $status = null): array
    {
        $data = [
            self::REQUEST_STATUS_PENDING => [
                'name' => '待请求',
                'status' => self::REQUEST_STATUS_PENDING,
                'color' => '#909399',
            ],
            self::REQUEST_STATUS_SUCCESS => [
                'name' => '请求成功',
                'status' => self::REQUEST_STATUS_SUCCESS,
                'color' => '#67C23A',
            ],
            self::REQUEST_STATUS_FAILED => [
                'name' => '请求失败',
                'status' => self::REQUEST_STATUS_FAILED,
                'color' => '#F56C6C',
            ],
        ];
        
        return $status !== null ? ($data[$status] ?? []) : $data;
    }
    
    /**
     * 配置类型字典
     * @param int|null $type
     * @return array
     */
    public static function getConfigTypeDict(?int $type = null): array
    {
        $data = [
            self::CONFIG_TYPE_SKU => [
                'name' => 'SKU级别（型号+内存+配置项）',
                'type' => self::CONFIG_TYPE_SKU,
                'desc' => '精确到单个SKU的价格配置',
            ],
            self::CONFIG_TYPE_MODEL_CAPACITY => [
                'name' => '批量管理（型号+内存）',
                'type' => self::CONFIG_TYPE_MODEL_CAPACITY,
                'desc' => '针对该型号+内存的所有配置项',
            ],
            self::CONFIG_TYPE_MODEL => [
                'name' => '按型号',
                'type' => self::CONFIG_TYPE_MODEL,
                'desc' => '针对该型号的所有内存和配置项',
            ],
            self::CONFIG_TYPE_GROUP => [
                'name' => '按分组',
                'type' => self::CONFIG_TYPE_GROUP,
                'desc' => '针对该分组的所有型号',
            ],
        ];
        
        return $type !== null ? ($data[$type] ?? []) : $data;
    }
    
    /**
     * 调整方式字典
     * @param int|null $type
     * @return array
     */
    public static function getAdjustmentTypeDict(?int $type = null): array
    {
        $data = [
            self::ADJUSTMENT_TYPE_FIXED => [
                'name' => '固定金额',
                'type' => self::ADJUSTMENT_TYPE_FIXED,
                'desc' => '在原始价格基础上加/减固定金额',
            ],
            self::ADJUSTMENT_TYPE_PERCENT => [
                'name' => '百分比',
                'type' => self::ADJUSTMENT_TYPE_PERCENT,
                'desc' => '按原始价格的百分比调整',
            ],
            self::ADJUSTMENT_TYPE_COVER => [
                'name' => '固定价格覆盖',
                'type' => self::ADJUSTMENT_TYPE_COVER,
                'desc' => '直接覆盖为固定价格',
            ],
        ];
        
        return $type !== null ? ($data[$type] ?? []) : $data;
    }
    
    /**
     * 价格状态字典
     * @param int|null $status
     * @return array
     */
    public static function getPriceStatusDict(?int $status = null): array
    {
        $data = [
            self::PRICE_STATUS_NORMAL => [
                'name' => '正常',
                'status' => self::PRICE_STATUS_NORMAL,
                'color' => '#000000',  // 黑色
            ],
            self::PRICE_STATUS_HIGH => [
                'name' => '高于大盘',
                'status' => self::PRICE_STATUS_HIGH,
                'color' => '#F56C6C',  // 红色
            ],
            self::PRICE_STATUS_LOW => [
                'name' => '低于大盘',
                'status' => self::PRICE_STATUS_LOW,
                'color' => '#67C23A',  // 绿色
            ],
        ];
        
        return $status !== null ? ($data[$status] ?? []) : $data;
    }
    
    /**
     * 状态字典（启用/禁用）
     * @param int|null $status
     * @return array
     */
    public static function getStatusDict(?int $status = null): array
    {
        $data = [
            self::STATUS_DISABLED => [
                'name' => '禁用',
                'status' => self::STATUS_DISABLED,
            ],
            self::STATUS_ENABLED => [
                'name' => '启用',
                'status' => self::STATUS_ENABLED,
            ],
        ];
        
        return $status !== null ? ($data[$status] ?? []) : $data;
    }
    
    /**
     * 获取请求状态名称
     * @param int $status
     * @return string
     */
    public static function getRequestStatusName(int $status): string
    {
        $dict = self::getRequestStatusDict($status);
        return $dict['name'] ?? '未知';
    }
    
    /**
     * 获取配置类型名称
     * @param int $type
     * @return string
     */
    public static function getConfigTypeName(int $type): string
    {
        $dict = self::getConfigTypeDict($type);
        return $dict['name'] ?? '未知';
    }
    
    /**
     * 获取调整方式名称
     * @param int $type
     * @return string
     */
    public static function getAdjustmentTypeName(int $type): string
    {
        $dict = self::getAdjustmentTypeDict($type);
        return $dict['name'] ?? '未知';
    }
    
    /**
     * 获取价格状态名称
     * @param int $status
     * @return string
     */
    public static function getPriceStatusName(int $status): string
    {
        $dict = self::getPriceStatusDict($status);
        return $dict['name'] ?? '未知';
    }
}

