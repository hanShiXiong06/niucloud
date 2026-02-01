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

namespace addon\fast_pay\app\validate\businessmember;
use core\base\BaseValidate;
/**
 * 商户会员验证器
 * Class BusinessMember
 * @package addon\fast_pay\app\validate\businessmember
 */
class BusinessMember extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['business_id', 'member_id', 'level', 'balance'],
            "edit" => ['business_id', 'member_id', 'level', 'balance']
        ];

}
