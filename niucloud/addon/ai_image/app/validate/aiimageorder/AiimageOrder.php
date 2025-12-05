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

namespace addon\ai_image\app\validate\aiimageorder;
use core\base\BaseValidate;
/**
 * 订单列验证器
 * Class AiimageOrder
 * @package addon\ai_image\app\validate\aiimageorder
 */
class AiimageOrder extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['member_id', 'package_id', 'order_id', 'name', 'image', 'order_money', 'point', 'num', 'type', 'day', 'status', 'out_trade_no', 'pay_time', 'pid', 'close_time'],
            "edit" => ['member_id', 'package_id', 'order_id', 'name', 'image', 'order_money', 'point', 'num', 'type', 'day', 'status', 'out_trade_no', 'pay_time', 'pid', 'close_time']
        ];

}
