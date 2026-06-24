<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\CardAdminService;
use core\base\BaseAdminController;

class ServiceCard extends BaseAdminController
{
    public function lists()
    {
        return success((new CardAdminService())->getCardList());
    }

    public function save()
    {
        $list = $this->request->post('list', []);
        (new CardAdminService())->saveCards(is_array($list) ? $list : []);
        return success('保存成功');
    }
}
