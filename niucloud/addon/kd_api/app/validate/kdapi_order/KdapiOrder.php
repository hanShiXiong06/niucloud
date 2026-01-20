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

namespace addon\kd_api\app\validate\kdapi_order;
use core\base\BaseValidate;
/**
 * 订单列验证器
 * Class KdapiOrder
 * @package addon\kd_api\app\validate\kdapi_order
 */
class KdapiOrder extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['member_id', 'order_id', 'title', 'order_money', 'pay_money', 'commission', 'status', 'is_js', 'sid', 'pub_id'],
            "edit" => ['member_id', 'order_id', 'title', 'order_money', 'pay_money', 'commission', 'status', 'is_js', 'sid', 'pub_id']
        ];

}
