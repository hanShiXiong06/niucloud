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

namespace addon\ai_image\app\validate\aiimagecreate;
use core\base\BaseValidate;
/**
 * 作品列验证器
 * Class AiimageCreate
 * @package addon\ai_image\app\validate\aiimagecreate
 */
class AiimageCreate extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['member_id', 'model_id', 'prompt', 'image_urls', 'aspect_ratio', 'images', 'status', 'point', 'is_self', 'msg', 'platform', 'channel'],
            "edit" => ['member_id', 'model_id', 'prompt', 'image_urls', 'aspect_ratio', 'images', 'status', 'point', 'is_self', 'msg', 'platform', 'channel']
        ];

}
