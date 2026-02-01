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

namespace addon\fast_pay\app\validate\business;
use core\base\BaseValidate;
/**
 * 商户列验证器
 * Class Business
 * @package addon\fast_pay\app\validate\business
 */
class Business extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['member_id', 'admin_id', 'mch_id', 'name', 'banner', 'desc', 'address', 'content', 'lat', 'lng', 'type', 'finderUserName', 'feedId', 'page', 'mini_appid', 'mini_page', 'active_num', 'status', 'over_time'],
            "edit" => ['member_id', 'admin_id', 'mch_id', 'name', 'banner', 'desc', 'address', 'content', 'lat', 'lng', 'type', 'finderUserName', 'feedId', 'page', 'mini_appid', 'mini_page', 'active_num', 'status', 'over_time']
        ];

}
