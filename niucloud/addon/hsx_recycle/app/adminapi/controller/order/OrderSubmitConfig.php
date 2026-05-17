<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\service\admin\order\OrderSubmitConfigService;
use core\base\BaseAdminController;

/**
 * 回收下单配置
 */
class OrderSubmitConfig extends BaseAdminController
{
    public function info()
    {
        return success((new OrderSubmitConfigService())->getConfig());
    }

    public function save()
    {
        (new OrderSubmitConfigService())->setConfig($this->request->post());
        return success('EDIT_SUCCESS');
    }
}
