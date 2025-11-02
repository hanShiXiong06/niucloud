<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\adminapi\controller\help_feedback;

use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\service\admin\help_feedback\HelpCategoryService;
use core\base\BaseAdminController;
use addon\home_service\app\service\admin\goods_category\GoodsCategoryService;


/**
 *  帮助分类控制器
 * Class HelpCategory
 * @description  帮助分类
 * @package app\adminapi\controller\help_category
 */
class HelpCategory extends BaseAdminController
{
    /**
     * 获取帮助列表
     * @description 获取帮助列表
     * @return \think\Response
     */
    public function page()
    {
        $data = $this->request->params([
            ["category_name", ""],
        ]);
        return success((new HelpCategoryService())->getPage($data));
    }

    /**
     * 获取帮助列表(不分页)
     * @description 获取帮助列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["category_name", ""],
        ]);
        return success((new HelpCategoryService())->getList($data));
    }


    /**
     * 添加帮助分类
     * @description 添加帮助分类
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["category_name", ""],
            ["sort", 0],
            ["is_show", 0],
            ["is_default", 0],
        ]);
        $id = (new HelpCategoryService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 帮助分类编辑
     * @description 帮助分类编辑
     * @param $category_id 分类id
     * @return \think\Response
     */
    public function edit($category_id)
    {
        $data = $this->request->params([
            ["category_name", ""],
            ["sort", 0],
            ["is_show", 0],
            ["is_default", ''],
        ]);
        (new HelpCategoryService())->edit($category_id, $data);
        return success('EDIT_SUCCESS');
    }


    /**
     * 帮助分类删除
     * @description 帮助删除
     * @param $category_id  帮助id
     * @return \think\Response
     */
    public function del(int $category_id)
    {
        (new HelpCategoryService())->del($category_id);
        return success('DELETE_SUCCESS');
    }

}
