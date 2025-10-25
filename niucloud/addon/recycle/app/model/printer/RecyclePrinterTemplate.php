<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace addon\recycle\app\model\printer;

use core\base\BaseModel;
use think\model\concern\SoftDelete;

/**
 * 回收打印模板模型
 * Class RecyclePrinterTemplate
 * @package addon\recycle\app\model\printer
 */
class RecyclePrinterTemplate extends BaseModel
{
    use SoftDelete;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'template_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_printer_template';

    // 软删除标记字段
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 模板类型常量
     */
    const TYPE_DEVICE_LABEL = 'device_label';     // 设备标签
    const TYPE_ORDER_RECEIPT = 'order_receipt';   // 订单小票
    const TYPE_RETURN_LABEL = 'return_label';     // 退回标签
    const TYPE_CUSTOM = 'custom';                 // 自定义

    /**
     * 状态常量
     */
    const STATUS_DISABLE = 0;  // 禁用
    const STATUS_ENABLE = 1;   // 启用

    /**
     * 获取模板类型列表
     * @return array
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_DEVICE_LABEL => '设备标签',
            self::TYPE_ORDER_RECEIPT => '订单小票', 
            self::TYPE_RETURN_LABEL => '退回标签',
            self::TYPE_CUSTOM => '自定义模板'
        ];
    }

    /**
     * 获取状态列表
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_DISABLE => '禁用',
            self::STATUS_ENABLE => '启用'
        ];
    }

    /**
     * 获取模板类型名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getTypeNameAttr($value, $data)
    {
        $types = [
            self::TYPE_DEVICE_LABEL => '设备标签',
            self::TYPE_ORDER_RECEIPT => '订单小票',
            self::TYPE_RETURN_LABEL => '退回标签',
            self::TYPE_CUSTOM => '自定义模板'
        ];
        return $types[$data['template_type']] ?? '';
    }

    /**
     * 获取状态文字
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            0 => '禁用',
            1 => '启用'
        ];
        return $status[$data['status']] ?? '';
    }

    /**
     * 获取是否默认文字
     * @param $value
     * @param $data
     * @return string
     */
    public function getIsDefaultTextAttr($value, $data)
    {
        $is_default = [
            0 => '否',
            1 => '是'
        ];
        return $is_default[$data['is_default']] ?? '';
    }

    /**
     * 保存前的钩子 - 调试content字段
     * @param $data
     */
    public static function onBeforeInsert($data)
    {
        // 确保$data是数组类型
        $dataArray = is_array($data) ? $data : $data->toArray();
        
        \think\facade\Log::info('模型保存前钩子 - INSERT', [
            'content' => $dataArray['content'] ?? '无content字段',
            'content_length' => isset($dataArray['content']) ? strlen($dataArray['content']) : 0,
            'all_fields' => array_keys($dataArray)
        ]);
    }
    
    /**
     * 保存前的钩子 - 调试content字段
     * @param $data
     */
    public static function onBeforeUpdate($data)
    {
        // 确保$data是数组类型
        $dataArray = is_array($data) ? $data : $data->toArray();
        
        \think\facade\Log::info('模型保存前钩子 - UPDATE', [
            'content' => $dataArray['content'] ?? '无content字段',
            'content_length' => isset($dataArray['content']) ? strlen($dataArray['content']) : 0,
            'all_fields' => array_keys($dataArray)
        ]);
    }
} 