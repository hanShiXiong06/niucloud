<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\service\admin\order\OrderRewardService;
use core\base\BaseAdminController;

class OrderReward extends BaseAdminController
{
    public function getConfig()
    {
        $data = (new OrderRewardService())->getConfig();
        return success($data);
    }

    public function setConfig()
    {
        $params = $this->request->post();
        (new OrderRewardService())->setConfig($params);
        return success();
    }
}
