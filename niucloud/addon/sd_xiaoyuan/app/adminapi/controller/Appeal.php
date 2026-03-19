<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\AppealService;
use core\base\BaseAdminController;

class Appeal extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['appeal_type', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new AppealService();
        $result = $service->getList($this->request->siteId(), $params);
        return success($result);
    }

    public function detail()
    {
        $id = $this->request->param('id', 0);
        
        $service = new AppealService();
        $detail = $service->getDetail($id, $this->request->siteId());
        return success($detail);
    }

    public function handle()
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);
        $reply = $this->request->param('reply', '');
        
        $service = new AppealService();
        $service->handle($id, $this->request->siteId(), $status, $reply);
        return success('处理成功');
    }
}
