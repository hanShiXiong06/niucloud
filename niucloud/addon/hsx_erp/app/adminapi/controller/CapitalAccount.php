<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpCapitalAccountService;
use core\base\BaseAdminController;
use think\App;

class CapitalAccount extends BaseAdminController
{
    protected ErpCapitalAccountService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpCapitalAccountService();
    }

    public function lists()
    {
        return success([
            'list' => $this->service->lists(),
            'type_map' => $this->service->typeMap(),
        ]);
    }

    public function save(int $id)
    {
        $params = $this->request->params([
            ['account_name', ''],
            ['account_type', 'bank'],
            ['bank_name', ''],
            ['account_no', ''],
            ['holder', ''],
            ['balance', 0],
            ['is_default', 0],
            ['status', 1],
            ['sort', 0],
            ['remark', ''],
        ]);
        return success(['id' => $this->service->save($params, $id)]);
    }

    public function delete(int $id)
    {
        return success($this->service->delete($id));
    }

    public function entry()
    {
        $params = $this->request->params([
            ['account_id', 0],
            ['direction', 'in'],
            ['amount', 0],
            ['party_id', 0],
            ['counterparty_name', ''],
            ['remark', ''],
        ]);
        return success(['id' => $this->service->entry($params)]);
    }

    public function ledger()
    {
        $params = $this->request->params([
            ['account_id', 0],
            ['direction', ''],
            ['keyword', ''],
            ['start_time', 0],
            ['end_time', 0],
            ['page', 1],
            ['limit', 15],
        ]);
        return success($this->service->ledger($params));
    }
}
