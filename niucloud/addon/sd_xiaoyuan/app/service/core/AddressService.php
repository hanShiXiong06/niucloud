<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Address;
use core\base\BaseApiService;

class AddressService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Address();
    }

    public function getList()
    {
      
        
        return $this->model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->order('is_default desc, create_time desc')->select()->toArray();
    }

    public function getDetail($id)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
    }

    public function add($data)
    {
        $data['member_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        
        if ($data['is_default'] == 1) {
            $this->model->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id]
            ])->update(['is_default' => 0]);
        }

        $data['create_time'] = time();
        $data['update_time'] = time();
        
        return $this->model->create($data);
    }

    public function edit($id, $data)
    {
        if (isset($data['is_default']) && $data['is_default'] == 1) {
            $this->model->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id]
            ])->update(['is_default' => 0]);
        }

        $data['update_time'] = time();
        
        return $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->update($data);
    }

    public function delete($id)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->delete();
    }
}
