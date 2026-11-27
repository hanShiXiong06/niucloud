<?php

namespace addon\hsx_recycle\app\adminapi\controller\adminapp\site;

use addon\hsx_recycle\app\service\admin\adminapp\StatService;
use addon\hsx_recycle\app\service\admin\adminapp\TodoService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 手机管理端首页。
 */
class Index extends BaseAdminController
{
    public function getStatList()
    {
        return success((new StatService())->getStatList([]));
    }

    public function getAllStatList()
    {
        return success((new StatService())->getAllStatList([]));
    }

    public function setStatList()
    {
        $data = $this->request->params([
            ['value', []],
        ]);
        return success((new StatService())->setStatList($data['value']));
    }

    public function getTodoList()
    {
        return success((new TodoService())->getTodoList([]));
    }

    public function setTodoList()
    {
        $data = $this->request->params([
            ['value', []],
        ]);
        return success((new TodoService())->setTodoList($data['value']));
    }

    public function getAllTodoList()
    {
        return success((new TodoService())->getAllTodoList([]));
    }
}
