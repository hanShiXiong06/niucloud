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

namespace addon\hsx_phone_query\app\api\controller\hsx_phone_query;

use core\base\BaseApiController;
use addon\hsx_phone_query\app\service\api\hsx_phone_query\HsxPhoneQueryCategoryService;


/**
 * 分类控制器
 * Class HsxPhoneQueryCategory
 * @package addon\hsx_phone_query\app\api\controller\hsx_phone_query_category
 */
class HsxPhoneQueryCategory extends BaseApiController
{
   /**
    * 获取分类列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["type_id",""],
             ["name",""],
             ["price",""]
        ]);
        return success((new HsxPhoneQueryCategoryService())->getPage($data));
    }
}
