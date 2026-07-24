<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpOpeningService;
use core\base\BaseAdminController;

class ErpOpening extends BaseAdminController
{
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
