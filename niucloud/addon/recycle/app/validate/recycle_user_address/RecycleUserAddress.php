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

namespace addon\recycle\app\validate\recycle_user_address;
use core\base\BaseValidate;
/**
 * 用户退货地址验证器
 * Class RecycleUserAddress
 * @package addon\recycle\app\validate\recycle_user_address
 */
class RecycleUserAddress extends BaseValidate
{

       protected $rule = [
            'member_id' => 'require',
            'address' => 'require',
            'id_card' => 'require',
            'mobile' => 'require',
            // 'card_pic' => 'require',
            'name' => 'require'
        ];

       protected $message = [
            'member_id.require' => ['common_validate.require', ['member_id']],
            'address.require' => ['common_validate.require', ['address']],
            'id_card.require' => ['common_validate.require', ['id_card']],
            'mobile.require' => ['common_validate.require', ['mobile']],
            // 'card_pic.require' => ['common_validate.require', ['card_pic']],
            'name.require' => ['common_validate.require', ['name']]
        ];

       protected $scene = [
            "add" => ['member_id', 'address', 'id_card', 'mobile', 'card_pic', 'name'],
            "edit" => ['member_id', 'address', 'id_card', 'mobile', 'card_pic', 'name']
        ];

}
