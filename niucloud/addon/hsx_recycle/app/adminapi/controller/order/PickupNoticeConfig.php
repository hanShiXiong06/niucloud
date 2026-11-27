<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\service\core\recycle_order\PickupNoticeConfigService;
use core\base\BaseAdminController;

class PickupNoticeConfig extends BaseAdminController
{
    public function info()
    {
        return success((new PickupNoticeConfigService())->get((int)$this->request->siteId()));
    }

}
