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

use addon\home_service\app\dict\help_feedback\HelpDict;
use addon\home_service\app\service\admin\help_feedback\HelpService;
use core\base\BaseAdminController;


/**
 *  商品分类控制器
 * Class Help
 * @description  商品分类
 * @package app\adminapi\controller\help
 */
class Help extends BaseAdminController
{

    /**
     * 获取帮助类型
     * @description 获取帮助类型
     * @return \think\Response
     */
    public function type()
    {
        return success(HelpDict::getType());
    }

    /**
     * 获取帮助列表
     * @description 获取帮助列表
     * @return \think\Response
     */
    public function page()
    {
        $data = $this->request->params([
            ["name", ""],
            ["type", ""],
            ["category_id", ""],
        ]);
        return success((new HelpService())->getPage($data));
    }

    /**
     * 帮助详情
     * @description 商品分类详情
     * @param int $help_id
     * @return \think\Response
     */
    public function info(int $help_id)
    {
        return success((new HelpService())->getInfo($help_id));
    }

    /**
     * 添加 商品分类
     * @description 添加 商品分类
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["name", ""],
            ["type", ""],
            ["category_id", 0],
            ['content', ""],
            ["sort", ""],
            ["is_show", ""],
            ["views_count", ""],
        ]);
        $id = (new HelpService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 帮助编辑
     * @description 帮助编辑
     * @param $help_id 帮助id
     * @return \think\Response
     */
    public function edit($help_id)
    {
        $data = $this->request->params([
            ["name", ""],
            ["type", ""],
            ["category_id", 0],
            ['content', ""],
            ["sort", ""],
            ["is_show", ""],
            ["views_count", ""],
        ]);
        (new HelpService())->edit($help_id, $data);
        return success('EDIT_SUCCESS');
    }


    /**
     * 帮助删除
     * @description 帮助删除
     * @param $id  帮助id
     * @return \think\Response
     */
    public function del(int $help_id)
    {
        (new HelpService())->del($help_id);
        return success('DELETE_SUCCESS');
    }

}
