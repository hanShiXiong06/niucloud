<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpPayeeLookupService;
use core\base\BaseAdminController;

class ErpPayeeLookup extends BaseAdminController
{
    public function customers()
    {
        return success((new ErpPayeeLookupService())->customers($this->request->params([
            ['keyword', ''], ['page', 1], ['limit', 10],
        ])))->header(['Cache-Control' => 'no-store, private']);
    }

    public function customer(int $member_id)
    {
        return success((new ErpPayeeLookupService())->customer($member_id))->header(['Cache-Control' => 'no-store, private']);
    }
}
