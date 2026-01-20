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

namespace addon\tk_jhkd\app\validate\shop_order;
use core\base\BaseValidate;
/**
 * 商城发单验证器
 * Class ShopOrder
 * @package addon\tk_jhkd\app\validate\shop_order
 */
class ShopOrder extends BaseValidate
{

       protected $rule = [
            'order_no' => 'require',
        ];

       protected $message = [
            'order_no.require' => ['common_validate.require', ['order_no']],
        ];

       protected $scene = [
            "add" => ['order_no', 'delivery_id', 'yida_order_no', 'order_status_name', 'is_pick', 'is_send', 'close_time', 'delete_time'],
            "edit" => ['order_no', 'delivery_id', 'yida_order_no', 'order_status_name', 'is_pick', 'is_send', 'close_time', 'delete_time']
        ];

}
