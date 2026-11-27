<?php

namespace addon\hsx_recycle\app\service\core\adminapp;

use core\base\BaseCoreService;

/**
 * 手机管理端应用字典。
 */
class CoreAppService extends BaseCoreService
{
    public function getMobileAppGroup()
    {
        return (new AdminAppDictService())->load([]);
    }

    public function getMobileApp()
    {
        return (new AdminAppDictService())->loadApps([]);
    }
}
