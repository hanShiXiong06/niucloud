<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\CardAdminService;
use core\base\BaseAdminController;

class CardOrder extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['card_type', ''],
            ['pay_status', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        return success((new CardAdminService())->getOrderList($params));
    }
}
