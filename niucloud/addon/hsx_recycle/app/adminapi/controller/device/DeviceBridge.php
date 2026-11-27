<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\device;

use addon\hsx_recycle\app\service\admin\device\DeviceBridgeConfigService;
use addon\hsx_recycle\app\service\core\device\DeviceBridgeDownloads;
use core\base\BaseAdminController;

class DeviceBridge extends BaseAdminController
{
    public function downloads()
    {
        return success(DeviceBridgeDownloads::getConfig());
    }

    public function info()
    {
        return success((new DeviceBridgeConfigService())->info());
    }

    public function save()
    {
        (new DeviceBridgeConfigService())->save($this->request->post());
        return success('EDIT_SUCCESS');
    }
}
