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

namespace addon\tk_jhkd\app\validate\orderdelivery;
use core\base\BaseValidate;
/**
 * 订单快递信息验证器
 * Class OrderDelivery
 * @package addon\tk_jhkd\app\validate\orderdelivery
 */
class OrderDelivery extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['order_no', 'start_address', 'end_address', 'goods', 'long', 'width', 'height', 'weight', 'package_count', 'delivery_id', 'delivery_status', 'delivery_type', 'customer_type', 'delivery_Business', 'delivery_info', 'online_pay', 'start_time', 'end_time', 'order_status_desc', 'order_status', 'courier_context', 'remark', 'delete_time'],
            "edit" => ['order_no', 'start_address', 'end_address', 'goods', 'long', 'width', 'height', 'weight', 'package_count', 'delivery_id', 'delivery_status', 'delivery_type', 'customer_type', 'delivery_Business', 'delivery_info', 'online_pay', 'start_time', 'end_time', 'order_status_desc', 'order_status', 'courier_context', 'remark', 'delete_time']
        ];

}
