<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\admin\ConfigService;
use core\base\BaseAdminController;

class Config extends BaseAdminController
{
    public function get()
    {
        $service = new ConfigService();
        $config = $service->getConfig($this->request->siteId());
        return success($config);
    }

    public function set()
    {
        $data = $this->request->post();
        
        $service = new ConfigService();
        foreach ($data as $key => $value) {
            $service->set($this->request->siteId(), $key, $value);
        }
        
        return success('保存成功');
    }

    public function save()
    {
        return $this->set();
    }

    public function getFee()
    {
        $service = new ConfigService();
        $config = $service->get($this->request->siteId(), 'fee');
        return success($config);
    }

    public function setFee()
    {
        $data = $this->request->params([
            ['base_fee', 3.00],
            ['distance_threshold', 2],
            ['distance_fee', 1.50],
            ['weight_threshold', 5],
            ['weight_fee', 0.50],
            ['urgent_fee', 5.00],
            ['commission_rate', 20]
        ]);
        
        $service = new ConfigService();
        $service->set($this->request->siteId(), 'fee', $data);
        return success('保存成功');
    }

    public function getRange()
    {
        $service = new ConfigService();
        $config = $service->get($this->request->siteId(), 'range');
        return success($config);
    }

    public function setRange()
    {
        $data = $this->request->params([
            ['enabled', true],
            ['center_lng', ''],
            ['center_lat', ''],
            ['radius', 5],
            ['campus_list', []]
        ]);
        
        $service = new ConfigService();
        $service->set($this->request->siteId(), 'range', $data);
        return success('保存成功');
    }
}
