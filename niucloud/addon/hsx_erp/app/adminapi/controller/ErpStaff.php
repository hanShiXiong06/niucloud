<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpStaffService;
use core\base\BaseAdminController;
use think\App;

class ErpStaff extends BaseAdminController
{
    protected ErpStaffService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpStaffService();
    }

    public function options()
    {
        return success($this->service->options($this->request->params([
            ['keyword', ''],
        ])));
    }
}
