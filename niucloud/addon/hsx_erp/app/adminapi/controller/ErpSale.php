<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpSaleService;
use core\base\BaseAdminController;
use think\App;

class ErpSale extends BaseAdminController
{
    protected ErpSaleService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpSaleService();
    }

    public function lists()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['finance_status', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->getPage($params));
    }

    public function stock()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['warehouse_id', 0],
            ['location_id', 0],
            ['category_id', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->stockPage($params));
    }

    public function info(int $id)
    {
        return success($this->service->info($id));
    }

    public function create()
    {
        $params = $this->request->params([
            ['party_name', ''],
            ['party_id', 0],
            ['sale_channel', ''],
            ['salesman_uid', 0],
            ['settle_method', ''],
            ['sale_at', 0],
            ['remark', ''],
            ['items', []],
        ]);
        return success(['id' => $this->service->create($params)]);
    }

    public function cancel(int $id)
    {
        $params = $this->request->params([
            ['remark', ''],
        ]);
        return success($this->service->cancel($id, (string)$params['remark']));
    }
}
