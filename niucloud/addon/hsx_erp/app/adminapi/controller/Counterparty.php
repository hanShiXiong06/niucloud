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
            ['keyword', ''], ['role_type', ''], ['counterparty_type', ''], ['status', ''], ['mobile', ''],
            ['sort_field', ''], ['sort_order', ''], ['page', 1], ['limit', 20],
        ])));
    }

    public function options()
    {
        return success($this->service->options($this->request->params([['keyword', ''], ['role_type', '']])));
    }

    public function memberOptions()
    {
        return success($this->service->memberOptions((string)$this->request->param('keyword', '')));
    }

    public function members(int $id)
    {
        return success($this->service->getMembers($id));
    }

    public function detail(int $id)
    {
        return success($this->service->detail($id));
    }

    public function addMember(int $id)
    {
        $p = $this->request->params([['member_id', 0], ['relation_role', 'business'], ['is_finance_contact', 0]]);
        $this->service->addMember($id, (int)$p['member_id'], (string)$p['relation_role'], (int)$p['is_finance_contact']);
        return success();
    }

    public function removeMember(int $id)
    {
        $this->service->removeMember($id, (int)$this->request->param('member_id', 0));
        return success();
    }

    public function delete(int $id)
    {
        $this->service->delete($id);
        return success();
    }

    public function quickContact()
    {
        return success($this->service->quickCreateContact($this->request->params([
            ['name', ''], ['mobile', ''], ['entity_id', 0], ['entity_name', ''],
            ['counterparty_type', 'individual'], ['role_type', 'customer'],
        ])));
    }

    public function resolveContact()
    {
        return success($this->service->resolveContact($this->request->params([
            ['member_id', 0], ['name', ''], ['mobile', ''],
            ['counterparty_type', 'individual'], ['role_type', 'customer'],
        ])));
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
