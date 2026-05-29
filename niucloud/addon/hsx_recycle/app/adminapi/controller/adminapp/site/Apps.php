<?php

namespace addon\hsx_recycle\app\adminapi\controller\adminapp\site;

use addon\hsx_recycle\app\service\admin\adminapp\AppsService;
use addon\hsx_recycle\app\service\admin\adminapp\NavService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 手机管理端应用。
 */
class Apps extends BaseAdminController
{
    public function getAppsOfIndex()
    {
        return success((new AppsService())->getAppsOfIndex([]));
    }

    public function setAppsOfIndex()
    {
        $data = $this->request->params([
            ['value', []],
        ]);
        return success((new AppsService())->setAppsOfIndex($data['value']));
    }

    public function getApps()
    {
        return success((new AppsService())->getAppsOfCheck([]));
    }

    public function getBottomNav()
    {
        return success((new NavService())->getNavList([]));
    }

    public function getAppOfUserCenter()
    {
        return success((new AppsService())->getAppOfUserCenter([]));
    }
}
