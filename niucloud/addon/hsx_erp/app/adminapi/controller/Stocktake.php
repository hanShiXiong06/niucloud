<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpStocktakeService;
use core\base\BaseAdminController;
use think\App;

/**
 * 库存盘点
 */
class Stocktake extends BaseAdminController
{
    protected ErpStocktakeService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpStocktakeService();
    }

    public function lists()
    {
        $where = $this->request->params([
            ['warehouse_id', 0], ['status', ''], ['page', 1], ['limit', 15],
        ]);
        return success($this->service->getPage($where));
    }

    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    /** 新建盘点单(按仓库/库位快照应在库清单) */
    public function create()
    {
        $p = $this->request->params([
            ['warehouse_id', 0], ['location_id', 0], ['remark', ''],
        ]);
        return success($this->service->createStocktake((int)$p['warehouse_id'], (int)$p['location_id'], (string)$p['remark']));
    }

    /** 录入实物(一批 IMEI) */
    public function scan(int $id)
    {
        $codes = $this->request->param('codes', []);
        if (is_string($codes)) {
            $codes = preg_split('/[\s,，;；]+/', $codes) ?: [];
        }
        return success($this->service->scan($id, (array)$codes));
    }

    /** 完成盘点(未盘到判盘亏, 可据实核销) */
    public function finish(int $id)
    {
        $adjust = (int)$this->request->param('adjust_loss', 1) === 1;
        return success($this->service->finish($id, $adjust));
    }
}
