<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\order;

use addon\recycle\app\service\admin\order\OrderRewardService;
use app\adminapi\controller\BaseAdminController;

class OrderReward extends BaseAdminController
{
    public function getConfig()
    {
        $data = (new OrderRewardService())->getConfig();
        return $this->success('', $data);
    }

    public function setConfig()
    {
        $params = $this->request->post();
        (new OrderRewardService())->setConfig($params);
        return $this->success();
    }
}
