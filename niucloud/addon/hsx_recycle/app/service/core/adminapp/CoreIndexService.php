<?php

namespace addon\hsx_recycle\app\service\core\adminapp;

use core\base\BaseCoreService;

/**
 * 手机管理端首页指标字典。
 */
class CoreIndexService extends BaseCoreService
{
    public function getStatList()
    {
        return (new AdminAppDictService())->loadStat([]);
    }

    public function getTodoList()
    {
        return (new AdminAppDictService())->loadTodo([]);
    }
}
