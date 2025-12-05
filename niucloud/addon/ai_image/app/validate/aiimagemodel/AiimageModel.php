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

namespace addon\ai_image\app\validate\aiimagemodel;
use core\base\BaseValidate;
/**
 * 智能体验证器
 * Class AiimageModel
 * @package addon\ai_image\app\validate\aiimagemodel
 */
class AiimageModel extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['name', 'logo', 'desc', 'prompt', 'sort', 'demo_image', 'status', 'point', 'is_vip'],
            "edit" => ['name', 'logo', 'desc', 'prompt', 'sort', 'demo_image', 'status', 'point', 'is_vip']
        ];

}
