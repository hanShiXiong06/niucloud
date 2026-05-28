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

namespace addon\hsx_recycle\app\adminapi\controller\category;

use core\base\BaseAdminController;
use addon\hsx_recycle\app\service\admin\category\RecycleCategoryService;
use addon\hsx_recycle\app\service\admin\category\RecycleCategoryQuoteHistoryService;


/**
 * 二手机分类控制器
 * Class RecycleCategory
 * @package addon\hsx_recycle\app\adminapi\controller\recycle_category
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
     * 获取商品分类树结构（支持按日期查看历史报价单）
     * @return \think\Response
     */
    public function tree()
    {
        $date = (string)$this->request->param('date', '');
        return success((new RecycleCategoryService())->getTree($date ?: null));
    }

    /**
     * 报价单历史列表（支持按分类、日期筛选）
     */
    public function quoteHistory()
    {
        $data = $this->request->params([
            ['category_id', 0],
            ['date', ''],
            ['start_time', 0],
            ['end_time', 0],
        ]);
        return success((new RecycleCategoryQuoteHistoryService())->getPage($data));
    }

    /**
     * 单分类的全部历史快照（分页）
     */
    public function quoteHistoryByCategory(int $id)
    {
        $data = $this->request->params([
            ['category_id', $id],
            ['date', ''],
            ['start_time', 0],
            ['end_time', 0],
        ]);
        $data['category_id'] = $id;
        return success((new RecycleCategoryQuoteHistoryService())->getPage($data));
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
        $this->validate($data, 'addon\hsx_recycle\app\validate\recycle_category\RecycleCategory.add');
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
        $this->validate($data, 'addon\hsx_recycle\app\validate\recycle_category\RecycleCategory.edit');
        (new RecycleCategoryService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    // editCategory
    public function updateCategory(){
        $data = $this->request->params([
             ["category_sort_array",""],
                         
        ]);
      //  $this->validate($data, 'addon\hsx_recycle\app\validate\recycle_category\RecycleCategory.edit');
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
