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

namespace addon\ai_image\app\validate\aiimagehelp;
use core\base\BaseValidate;
/**
 * 帮助中心验证器
 * Class AiimageHelp
 * @package addon\ai_image\app\validate\aiimagehelp
 */
class AiimageHelp extends BaseValidate
{

       protected $rule = [
            
        ];

       protected $message = [
            
        ];

       protected $scene = [
            "add" => ['cat_id', 'title', 'image', 'desc', 'content', 'view_num', 'sort'],
            "edit" => ['cat_id', 'title', 'image', 'desc', 'content', 'view_num', 'sort']
        ];

}
