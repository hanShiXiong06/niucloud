<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\EvaluateService;
use core\base\BaseAdminController;

class Evaluate extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['runner_id', ''],
            ['score', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new EvaluateService();
        $result = $service->getList($this->request->siteId(), $params);
        return success($result);
    }

    public function detail()
    {
        $id = $this->request->param('id', 0);
        
        $service = new EvaluateService();
        $detail = $service->getDetail($id, $this->request->siteId());
        return success($detail);
    }

    public function delete()
    {
        $id = $this->request->param('id', 0);
        
        $service = new EvaluateService();
        $service->delete($id, $this->request->siteId());
        return success('删除成功');
    }
}
