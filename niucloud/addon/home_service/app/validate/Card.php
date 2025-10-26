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

namespace addon\home_service\app\validate;

use core\base\BaseValidate;

/**
 * 服务验证器
 */
class Card extends BaseValidate
{

    protected $rule = [
        'card_name' => 'require',
        'card_image' => 'require',
        'status' => 'number|between:0,1',
        'card_content' => 'require',
    ];

    protected $message = [
        'card_name.require' => 'home_service_card.card_name_require',

        'card_image.require' => 'home_service_card.card_image_require',
        'card_content.require' => 'home_service_card.goods_content_require',
    ];

    protected $scene = [
        "add" => ['card_name', 'card_image', 'status', 'sort', 'card_content'],
        "edit" => ['card_name', 'card_image', 'status', 'sort', 'card_content'],
    ];

}