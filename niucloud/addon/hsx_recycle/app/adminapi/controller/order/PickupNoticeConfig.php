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

    public function save()
    {
        (new PickupNoticeConfigService())->save((int)$this->request->siteId(), $this->request->params([
            ['weapp', []], ['wechat', []],
        ]));
        return success('EDIT_SUCCESS');
    }
}
