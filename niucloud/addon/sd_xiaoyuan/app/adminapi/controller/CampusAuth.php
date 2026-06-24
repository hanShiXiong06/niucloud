<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\CampusAuthService;
use core\base\BaseAdminController;

class CampusAuth extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['identity_type', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new CampusAuthService();
        $result = $service->getList($this->request->siteId(), $params);
        return success($result);
    }

    public function detail()
    {
        $id = $this->request->param('id', 0);
        
        $service = new CampusAuthService();
        $detail = $service->getDetail($id, $this->request->siteId());
        return success($detail);
    }

    public function audit()
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);
        $refuseReason = $this->request->param('refuse_reason', '');
        
        $service = new CampusAuthService();
        $service->audit($id, $this->request->siteId(), $status, $refuseReason);
        return success('操作成功');
    }

    public function stat()
    {
        $service = new CampusAuthService();
        $stat = $service->getStat($this->request->siteId());
        return success($stat);
    }

    public function cancel()
    {
        $id = $this->request->param('id', 0);
        
        $service = new CampusAuthService();
        $service->cancel($id, $this->request->siteId());
        return success('取消认证成功');
    }
}
