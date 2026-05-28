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

namespace addon\hsx_recycle\app\api\controller\category;

use addon\hsx_recycle\app\service\api\recycle_category\RecycleCategoryService;
use core\base\BaseAdminController;


/**
 * 二手机分类控制器
 * Class RecycleCategory
 * @package addon\hsx_recycle\app\adminapi\controller\recycle_category
 */
class RecycleCategory extends BaseAdminController
{

     /**
     * 获取商品分类树结构
     * @return \think\Response
     */
    public function tree()
    {
        return success(( new RecycleCategoryService() )->getTree());
    }
    public function address_list (){
        return success(( new RecycleCategoryService() )->address_list());
    }
    public function hot(){
        return success(( new RecycleCategoryService() )->hot());
    }

    /**
     * 报价单浏览埋点
     */
    public function recordView(int $id)
    {
        (new RecycleCategoryService())->recordView($id);
        return success('ok');
    }

}
