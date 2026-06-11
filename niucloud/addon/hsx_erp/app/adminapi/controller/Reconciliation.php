<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpReconciliationService;
use core\base\BaseAdminController;
use think\App;

class Reconciliation extends BaseAdminController
{
    protected ErpReconciliationService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpReconciliationService();
    }

    public function scan()
    {
        return success($this->service->scan($this->request->params([
            ['keyword', ''],
            ['differences_only', 1],
            ['limit', 100],
        ])));
    }

    public function asset(int $id)
    {
        return success($this->service->checkAsset($id));
    }
}
