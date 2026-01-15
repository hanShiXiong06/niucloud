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

namespace app\adminapi\controller\adminapp\site;

use app\service\admin\adminapp\AppsService;
use app\service\admin\adminapp\StatService;
use app\service\admin\adminapp\TodoService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 控制台
 */
class Index extends BaseAdminController
{
    /**
     * 统计
     * @return Response
     */
    public function getStatList()
    {
        //获取拥有权限的应用列表(结合公)
        //整合应用
        return success((new StatService())->getStatList([]));
    }


    public function getAllStatList()
    {
        //获取拥有权限的应用列表(结合公)
        //整合应用
        return success((new StatService())->getAllStatList([]));
    }


    public function setStatList()
    {
        //获取拥有权限的应用列表(结合公)
        $data = $this->request->params([
            ['value', []],
        ]);
        //整合应用
        return success((new StatService())->setStatList($data['value']));
    }
    /**
     * 待办列表
     * @return Response
     */
    public function getTodoList()
    {
        //获取拥有权限的应用列表(结合公)
        //整合应用
        return success((new TodoService())->getTodoList([]));
    }

    public function setTodoList()
    {
        //获取拥有权限的应用列表(结合公)
        $data = $this->request->params([
            ['value', []],
        ]);
        //整合应用
        return success((new TodoService())->setTodoList($data['value']));
    }

    public function getAllTodoList()
    {
        //获取拥有权限的应用列表(结合公)
        //整合应用
        return success((new TodoService())->getAllTodoList([]));
    }
}
