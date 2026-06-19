<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpDashboardService;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * 运营看板
 */
class Dashboard extends BaseAdminController
{
    protected ErpDashboardService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpDashboardService();
    }

    public function data(): Response
    {
        $params = $this->request->params([
            ['start', 0],
            ['end', 0],
            ['warehouse_id', 0],
        ]);
        return success($this->service->data($params));
    }
}
