<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpOpeningService;
use addon\hsx_erp\app\service\admin\ErpMallInventoryService;
use core\base\BaseAdminController;

class ErpOpening extends BaseAdminController
{
    public function mallInventoryPreview()
    {
        return success((new ErpMallInventoryService())->preview($this->request->params([
            ['keyword', ''], ['page', 1], ['limit', 20], ['include_linked', 0], ['receivable_id', 0], ['scope', 'stock'],
        ])));
    }

    public function mallInventoryConfirm()
    {
        return success((new ErpMallInventoryService())->confirm($this->request->params([
            ['items', []], ['receivable_id', 0], ['warehouse_id', 0], ['location_id', 0], ['opening_at', 0],
        ])));
    }

    public function upload()
    {
        $openingDate = (int)$this->request->param('opening_date', 0);
        return success((new ErpOpeningService())->upload($this->request->file('file'), $openingDate));
    }

    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 15],
        ]);
        return success((new ErpOpeningService())->getPage($params));
    }

    public function info(int $id)
    {
        return success((new ErpOpeningService())->getInfo($id));
    }

    public function items(int $id)
    {
        $params = $this->request->params([
            ['status', ''],
            ['item_type', ''],
            ['member_action', ''],
            ['party_action', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 50],
        ]);
        return success((new ErpOpeningService())->getItems($id, $params));
    }

    public function retry(int $id)
    {
        return success((new ErpOpeningService())->retry($id));
    }

    public function confirm(int $id)
    {
        return success((new ErpOpeningService())->confirm($id));
    }

    public function delete(int $id)
    {
        return success((new ErpOpeningService())->delete($id));
    }
}
