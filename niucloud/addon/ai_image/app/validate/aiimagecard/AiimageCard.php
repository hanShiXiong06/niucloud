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

namespace addon\ai_image\app\validate\aiimagecard;
use core\base\BaseValidate;
/**
 * 卡密兑换验证器
 * Class AiimageCard
 * @package addon\ai_image\app\validate\aiimagecard
 */
class AiimageCard extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['member_id', 'card_num', 'point', 'is_use', 'use_time', 'is_export', 'pid', 'expire_time'],
            "edit" => ['member_id', 'card_num', 'point', 'is_use', 'use_time', 'is_export', 'pid', 'expire_time']
        ];

}
