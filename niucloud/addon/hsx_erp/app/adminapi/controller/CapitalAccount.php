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

    /** 账户列表 */
    public function lists()
    {
        return success([
            'list' => $this->service->getAll(),
            'type_map' => $this->service->accountTypeMap(),
        ]);
    }

    /** 新建/编辑账户 */
    public function save(int $id = 0)
    {
        return success($this->service->save($this->request->params([
            ['account_name', ''], ['account_type', 'bank'], ['bank_name', ''], ['account_no', ''],
            ['holder', ''], ['currency', 'CNY'], ['balance', 0], ['is_default', 0],
            ['status', 1], ['sort', 0], ['remark', ''],
        ]), $id));
    }

    /** 删除账户 */
    public function delete(int $id)
    {
        return success($this->service->delete($id));
    }

    /** 手工记一笔收/付 */
    public function entry()
    {
        return success($this->service->recordEntry($this->request->params([
            ['account_id', 0], ['direction', 'in'], ['amount', 0], ['biz_type', 'manual'],
            ['counterparty_id', 0], ['counterparty_name', ''], ['source_no', ''], ['remark', ''],
        ])));
    }

    /** 账目往来流水 */
    public function ledger()
    {
        return success($this->service->ledgerPage($this->request->params([
            ['account_id', 0], ['direction', ''], ['keyword', ''], ['page', 1], ['limit', 15],
        ])));
    }
}
