<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use core\base\BaseAdminController;

class ErpConfig extends BaseAdminController
{
    public function info()
    {
        return success([
            'allow_instant_settle' => 1,
        ]);
    }
}
