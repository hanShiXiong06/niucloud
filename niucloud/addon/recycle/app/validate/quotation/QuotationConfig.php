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
 * 报价单配置验证器
 * Class QuotationConfig
 * @package addon\recycle\app\validate\quotation
 */
class QuotationConfig extends BaseValidate
{
    protected $rule = [
        'id' => 'require',
        'quotation_id' => 'require|integer',
        'price_name' => 'require',
        'config_name' => 'require',
        'is_enable' => 'integer',
        'auto_request' => 'integer',
    ];

    protected $message = [
        'id.require' => ['common_validate.require', ['id']],
        'quotation_id.require' => ['common_validate.require', ['quotation_id']],
        'quotation_id.integer' => ['common_validate.integer', ['quotation_id']],
        'price_name.require' => ['common_validate.require', ['price_name']],
        'config_name.require' => ['common_validate.require', ['config_name']],
        'is_enable.integer' => ['common_validate.integer', ['is_enable']],
        'auto_request.integer' => ['common_validate.integer', ['auto_request']],
    ];

    protected $scene = [
        'add' => ['quotation_id', 'price_name', 'config_name'],
        'edit' => ['quotation_id', 'price_name', 'config_name'],
    ];
}

