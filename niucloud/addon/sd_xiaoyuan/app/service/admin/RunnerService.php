<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\model\RunnerBalanceLog;
use core\base\BaseService;

class RunnerService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Runner();
    }

    public function getList($siteId, $params)
    {
        $where = [['site_id', '=', $siteId]];
        
        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        
        if (!empty($params['keyword'])) {
            $where[] = ['real_name|mobile', 'like', '%' . $params['keyword'] . '%'];
        }
        
        if (isset($params['is_online']) && $params['is_online'] !== '') {
            $where[] = ['is_online', '=', $params['is_online']];
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

    public function audit($id, $siteId, $status, $refuseReason = '')
    {
        $update = [
            'status' => $status,
            'refuse_reason' => $refuseReason,
            'update_time' => time()
        ];
        // 审核通过后默认在线
        if ($status == 1) {
            $update['is_online'] = 1;
        }
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->update($update);
    }

    public function disable($id, $siteId)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->update([
            'status' => 3,
            'is_online' => 0,
            'update_time' => time()
        ]);
    }

    public function enable($id, $siteId)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->update([
            'status' => 1,
            'update_time' => time()
        ]);
    }

    public function getBalanceLog($runnerId, $siteId, $params)
    {
        $logModel = new RunnerBalanceLog();
        
        $where = [
            ['runner_id', '=', $runnerId],
            ['site_id', '=', $siteId]
        ];
        
        if (!empty($params['type'])) {
            $where[] = ['type', '=', $params['type']];
        }
        
        $list = $logModel->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $logModel->where($where)->count();
        
        return [
            'list' => $list,
            'count' => $count
        ];
    }

    public function getStat($siteId)
    {
        return [
            'total' => $this->model->where('site_id', $siteId)->count(),
            'online' => $this->model->where([
                ['site_id', '=', $siteId],
                ['is_online', '=', 1],
                ['status', '=', 1]
            ])->count(),
            'pending' => $this->model->where([
                ['site_id', '=', $siteId],
                ['status', '=', 0]
            ])->count(),
            'disabled' => $this->model->where([
                ['site_id', '=', $siteId],
                ['status', '=', 3]
            ])->count()
        ];
    }
}
