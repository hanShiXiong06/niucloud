<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\Coupon;
use addon\sd_xiaoyuan\app\model\CouponRecord;
use core\base\BaseService;

class CouponService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Coupon();
    }

    public function getList($siteId, $params)
    {
        $where = [['site_id', '=', $siteId]];
        
        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        
        if (!empty($params['type'])) {
            $where[] = ['type', '=', $params['type']];
        }
        
        if (!empty($params['keyword'])) {
            $where[] = ['name', 'like', '%' . $params['keyword'] . '%'];
        }
        
        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $this->model->where($where)->count();
        
        return [
            'list' => $list,
            'count' => $count
        ];
    }

    public function getDetail($id, $siteId)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->findOrEmpty()->toArray();
    }

    public function add($data)
    {
        $data['create_time'] = time();
        $data['update_time'] = time();
        return $this->model->create($data);
    }

    public function edit($id, $siteId, $data)
    {
        $data['update_time'] = time();
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->update($data);
    }

    public function delete($id, $siteId)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->delete();
    }

    public function setStatus($id, $siteId, $status)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->update([
            'status' => $status,
            'update_time' => time()
        ]);
    }

    public function getRecords($couponId, $siteId, $params)
    {
        $recordModel = new CouponRecord();
        
        $where = [
            ['coupon_id', '=', $couponId],
            ['site_id', '=', $siteId]
        ];
        
        $list = $recordModel->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $recordModel->where($where)->count();
        
        return [
            'list' => $list,
            'count' => $count
        ];
    }
}
