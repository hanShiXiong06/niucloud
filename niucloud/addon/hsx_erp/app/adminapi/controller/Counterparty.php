<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpCounterpartyAdminService;
use core\base\BaseAdminController;
use think\App;

class Counterparty extends BaseAdminController
{
    protected ErpCounterpartyAdminService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpCounterpartyAdminService();
    }

    public function lists()
    {
        return success($this->service->getPage($this->request->params([
            ['keyword', ''], ['role_type', ''], ['status', ''], ['page', 1], ['limit', 20],
        ])));
    }

    public function options()
    {
        return success($this->service->options($this->request->params([['keyword', '']])));
    }

    public function memberOptions()
    {
        return success($this->service->memberOptions((string)$this->request->param('keyword', '')));
    }

    public function members(int $id)
    {
        return success($this->service->getMembers($id));
    }

    public function save(int $id = 0)
    {
        return success($this->service->save($this->request->params([
            ['counterparty_type', 'individual'], ['role_type', 'supplier'], ['name', ''],
            ['mobile', ''], ['contact_name', ''], ['tax_no', ''], ['bank_name', ''],
            ['bank_account', ''], ['status', 1], ['remark', ''], ['members', null],
        ]), $id));
    }
}
