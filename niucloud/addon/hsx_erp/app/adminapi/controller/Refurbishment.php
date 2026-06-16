<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpRefurbishmentService;
use core\base\BaseAdminController;
use think\App;

class Refurbishment extends BaseAdminController
{
    protected ErpRefurbishmentService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpRefurbishmentService();
    }

    public function lists()
    {
        return success($this->service->getPage($this->request->params([
            ['keyword', ''], ['status', ''], ['assigned_uid', 0], ['start_time', 0], ['end_time', 0],
            ['sort_field', ''], ['sort_order', ''], ['page', 1], ['limit', 20],
        ])));
    }

    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    public function userOptions()
    {
        return success($this->service->userOptions((string)$this->request->param('keyword', '')));
    }

    public function create()
    {
        return success($this->service->create($this->request->params([
            ['asset_id', 0], ['assigned_uid', 0], ['planned_finish_at', 0],
            ['items', []], ['remark', ''],
        ])));
    }

    public function complete(int $id)
    {
        return success($this->service->complete($id, $this->request->params([
            ['items', []], ['completion_remark', ''],
        ])));
    }

    public function cancel(int $id)
    {
        return success($this->service->cancel($id, (string)$this->request->param('remark', '')));
    }

    public function skip(int $asset_id)
    {
        return success($this->service->skip($asset_id, (string)$this->request->param('remark', '')));
    }
}
