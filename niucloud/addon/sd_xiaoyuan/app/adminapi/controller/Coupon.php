<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\CouponService;
use core\base\BaseAdminController;

class Coupon extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['type', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new CouponService();
        $result = $service->getList($this->request->siteId(), $params);
        return success($result);
    }

    public function detail()
    {
        $id = $this->request->param('id', 0);
        
        $service = new CouponService();
        $detail = $service->getDetail($id, $this->request->siteId());
        return success($detail);
    }

    public function add()
    {
        $data = $this->request->params([
            ['name', ''],
            ['type', 'REDUCE'],
            ['discount_value', 0],
            ['min_amount', 0],
            ['total_count', 0],
            ['limit_per_user', 1],
            ['valid_days', 7],
            ['start_time', 0],
            ['end_time', 0],
            ['status', 1]
        ]);
        
        $data['site_id'] = $this->request->siteId();
        
        $service = new CouponService();
        $service->add($data);
        return success('添加成功');
    }

    public function edit()
    {
        $id = $this->request->param('id', 0);
        $data = $this->request->params([
            ['name', ''],
            ['type', 'REDUCE'],
            ['discount_value', 0],
            ['min_amount', 0],
            ['total_count', 0],
            ['limit_per_user', 1],
            ['valid_days', 7],
            ['start_time', 0],
            ['end_time', 0],
            ['status', 1]
        ]);
        
        $service = new CouponService();
        $service->edit($id, $this->request->siteId(), $data);
        return success('修改成功');
    }

    public function delete()
    {
        $id = $this->request->param('id', 0);
        
        $service = new CouponService();
        $service->delete($id, $this->request->siteId());
        return success('删除成功');
    }

    public function status()
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);
        
        $service = new CouponService();
        $service->setStatus($id, $this->request->siteId(), $status);
        return success('操作成功');
    }

    public function records()
    {
        $id = $this->request->param('id', 0);
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new CouponService();
        $result = $service->getRecords($id, $this->request->siteId(), $params);
        return success($result);
    }
}
