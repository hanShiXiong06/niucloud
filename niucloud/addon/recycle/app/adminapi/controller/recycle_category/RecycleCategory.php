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

namespace addon\recycle\app\adminapi\controller\recycle_category;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\recycle_category\RecycleCategoryService;


/**
 * 二手机分类控制器
 * Class RecycleCategory
 * @package addon\recycle\app\adminapi\controller\recycle_category
 */
class RecycleCategory extends BaseAdminController
{
   /**
    * 获取二手机分类列表
    * @return \think\Response
    */
    public function lists(){
        $data = $this->request->params([
             ["category_name",""],
             ["level",""],
             ["pid",""],
             ["category_full_name",""],
             ["is_show",""],
             ["need_vip",""],
             ["sort",""],
             ["create_time",""],
             ["update_time",""],
             ['images',''] 
        ]);
        return success((new RecycleCategoryService())->getPage($data));
    }

    /**
     * 二手机分类详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id){
        return success((new RecycleCategoryService())->getInfo($id));
    }
     /**
     * 获取商品分类树结构
     * @return \think\Response
     */
    public function tree()
    {
    
        return success(( new RecycleCategoryService() )->getTree());
    }

    /**
     * 添加二手机分类
     * @return \think\Response
     */
    public function add(){
        $data = $this->request->params([
             ["category_name",""],
             ["image",""],
             ["level",0],
             ["pid",0],
             ["category_full_name",""],
             ["is_show",0],
             ["sort",0],
             ["images",""],
             ["need_vip",""]
             
        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_category\RecycleCategory.add');
        $id = (new RecycleCategoryService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 二手机分类编辑
     * @param $id  二手机分类id
     * @return \think\Response
     */
    public function edit(int $id){
        $data = $this->request->params([
             ["category_name",""],
             ["image",""],
             ["level",0],
             ["pid",0],
             ["category_full_name",""],
             ["is_show",0],
             ["sort",0],
             ["images",""],
             ["need_vip",""]
        ]);
        $this->validate($data, 'addon\recycle\app\validate\recycle_category\RecycleCategory.edit');
        (new RecycleCategoryService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    // editCategory
    public function updateCategory(){
        $data = $this->request->params([
             ["category_sort_array",""],
                         
        ]);
      //  $this->validate($data, 'addon\recycle\app\validate\recycle_category\RecycleCategory.edit');
        (new RecycleCategoryService())->updateCategory($data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 二手机分类删除
     * @param $id  二手机分类id
     * @return \think\Response
     */
    public function del(int $id){
        (new RecycleCategoryService())->del($id);
        return success('DELETE_SUCCESS');
    }

    
}
