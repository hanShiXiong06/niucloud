<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\validate\quotation;

use core\base\BaseValidate;

/**
 * 价格配置验证器
 * Class QuotationPriceConfig
 * @package addon\recycle\app\validate\quotation
 */
class QuotationPriceConfig extends BaseValidate
{
    protected $rule = [
        'id' => 'require',
        'config_type' => 'require|integer|in:1,2,3,4',
        'goods_id' => 'integer',
        'capacity' => 'max:50',
        'config_item_name' => 'max:100',
        'group_key' => 'integer',
        'adjustment_type' => 'require|integer|in:1,2,3',
        'adjustment_value' => 'require|float',
        'is_enable' => 'integer',
    ];

    protected $message = [
        'id.require' => ['common_validate.require', ['id']],
        'config_type.require' => ['common_validate.require', ['config_type']],
        'config_type.integer' => ['common_validate.integer', ['config_type']],
        'config_type.in' => '配置类型必须是1-SKU级别、2-批量管理、3-按型号、4-按分组',
        'goods_id.integer' => ['common_validate.integer', ['goods_id']],
        'capacity.max' => '容量最多50个字符',
        'config_item_name.max' => '配置项名称最多100个字符',
        'group_key.integer' => ['common_validate.integer', ['group_key']],
        'adjustment_type.require' => ['common_validate.require', ['adjustment_type']],
        'adjustment_type.integer' => ['common_validate.integer', ['adjustment_type']],
        'adjustment_type.in' => '调整方式必须是1-固定金额、2-百分比、3-固定价格覆盖',
        'adjustment_value.require' => ['common_validate.require', ['adjustment_value']],
        'adjustment_value.float' => ['common_validate.float', ['adjustment_value']],
        'is_enable.integer' => ['common_validate.integer', ['is_enable']],
    ];

    protected $scene = [
        'add' => ['config_type', 'adjustment_type', 'adjustment_value'],
        'edit' => ['config_type', 'adjustment_type', 'adjustment_value'],
    ];
}

