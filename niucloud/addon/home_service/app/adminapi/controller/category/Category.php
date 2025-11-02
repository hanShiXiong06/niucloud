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

namespace addon\home_service\app\adminapi\controller\category;

use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\model\technician\TechnicianApplication;
use core\base\BaseAdminController;
use addon\home_service\app\service\admin\goods_category\GoodsCategoryService;


/**
 *  商品分类控制器
 * Class O2oGoodsCategory
 * @description  商品分类
 * @package app\adminapi\controller\o2o_goods_category
 */
class Category extends BaseAdminController
{
    /**
     * 获取商品分类列表
     * @description 获取商品分类列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["category_name", ""],
            ["create_time", []]
        ]);
        return success((new GoodsCategoryService())->getPage($data));
    }

    /**
     * 商品分类详情
     * @description 商品分类详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new GoodsCategoryService())->getInfo($id));
    }

    /**
     * 添加 商品分类
     * @description 添加 商品分类
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["category_name", ""],
            ["image", ""],
            ["adv_image", ""],
            ["sort", 0],
            ['pid', 0],
            ["is_show", 0],
            ["is_settled", 0],
            ["intro", 0],
            ["errand_business", 0],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\Category.add');
        $id = (new GoodsCategoryService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 商品分类编辑
     * @description 商品分类编辑
     * @param $id 商品分类id
     * @return \think\Response
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ["category_name", ""],
            ["image", ""],
            ["adv_image", ""],
            ["sort", 0],
            ['pid', 0],
            ["is_show", 0],
            ["is_settled", 0],
            ["intro", 0],
            ["errand_business", 0],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\Category.add');
        (new GoodsCategoryService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }


    public function changeSort()
    {
        $data = $this->request->params([
            ["sort", 0],
            ["category_id", 0],
        ]);
        (new GoodsCategoryService())->changeSort($data['category_id'], $data['sort']);
        return success('EDIT_SUCCESS');
    }


    /**
     * 商品分类删除
     * @description 商品分类删除
     * @param $id  商品分类id
     * @return \think\Response
     */
    public function del(int $id)
    {
        $use_num = (new Goods())->getCountByCategoryID($id);
        $technician_use_num = (new Technician())->getCountByCategoryID($id);
        $technician_application_use_num = (new TechnicianApplication())->getCountByCategoryID($id);
        if ($use_num > 0 || $technician_use_num > 0 || $technician_application_use_num > 0) {
            return fail('HOME_SERVICE_CATEGORY_BE_USED');
        }
        (new GoodsCategoryService())->del($id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 获取分类列表
     * @description 获取分类列表
     */
    public function getCategoryList()
    {
        $data = $this->request->params([
            ["type", 1]
        ]);
        return success((new GoodsCategoryService())->getList($data['type']));
    }

    /**
     * 获取分级分类
     * @description 获取分类列表（树结构）
     */
    public function tree()
    {
        return success((new GoodsCategoryService())->getTree());
    }

}
