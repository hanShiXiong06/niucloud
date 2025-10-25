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
 * 回收打印机模型
 * Class RecyclePrinter
 * @package addon\recycle\app\model\printer
 */
class RecyclePrinter extends BaseModel
{
    use SoftDelete;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'printer_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_printer';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 时间字段取出后的默认时间格式
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';
    
    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_time';
    
    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_time';

    // 软删除标记字段
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 打印机品牌常量
     */
    const BRAND_XINYECLOUD = 'xinyecloud';  // 芯烨云
    const BRAND_FEIECLOUD = 'feiecloud';    // 飞鹅云

    /**
     * 打印机类型常量
     */
    const TYPE_TICKET = 'ticket';   // 小票打印机
    const TYPE_LABEL = 'label';     // 标签打印机

    /**
     * 状态常量
     */
    const STATUS_DISABLE = 0;  // 禁用
    const STATUS_ENABLE = 1;   // 启用

    /**
     * 获取打印机品牌列表
     * @return array
     */
    public static function getBrandList(): array
    {
        return [
            self::BRAND_XINYECLOUD => '芯烨云',
            self::BRAND_FEIECLOUD => '飞鹅云'
        ];
    }

    /**
     * 获取打印机类型列表
     * @return array
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_TICKET => '小票打印机',
            self::TYPE_LABEL => '标签打印机'
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
     * 获取品牌名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getBrandNameAttr($value, $data)
    {
        $brands = self::getBrandList();
        return $brands[$data['brand']] ?? '';
    }

    /**
     * 获取类型名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getTypeNameAttr($value, $data)
    {
        $types = self::getTypeList();
        return $types[$data['type']] ?? '';
    }

    /**
     * 获取状态文字
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = self::getStatusList();
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
        return $data['is_default'] ? '是' : '否';
    }
} 