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

namespace addon\ai_image\app\validate\aiimagepackage;
use core\base\BaseValidate;
/**
 * 套餐列验证器
 * Class AiimagePackage
 * @package addon\ai_image\app\validate\aiimagepackage
 */
class AiimagePackage extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['name', 'image', 'price', 'point', 'num', 'type', 'day', 'limit', 'status', 'sort'],
            "edit" => ['name', 'image', 'price', 'point', 'num', 'type', 'day', 'limit', 'status', 'sort']
        ];

}
