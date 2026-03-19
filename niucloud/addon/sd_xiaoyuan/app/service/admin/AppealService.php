<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\Appeal;
use core\base\BaseService;

class AppealService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Appeal();
    }

    public function getList($siteId, $params)
    {
        $where = [['site_id', '=', $siteId]];
        
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        
        if (!empty($params['appeal_type'])) {
            $where[] = ['appeal_type', '=', $params['appeal_type']];
        }
        
        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $this->model->where($where)->count();
        
        // 附加订单号和接单员信息
        if (!empty($list)) {
            $order_ids = array_unique(array_filter(array_column($list, 'order_id')));
            $runner_ids = array_unique(array_filter(array_column($list, 'runner_id')));
            
            $orders = [];
            $runners = [];
            
            if (!empty($order_ids)) {
                $order_list = (new \addon\sd_xiaoyuan\app\model\order\Order())->where([['id', 'in', $order_ids]])->column('order_no', 'id');
                $orders = $order_list;
            }
            
            if (!empty($runner_ids)) {
                $runner_list = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where([['id', 'in', $runner_ids]])->column('real_name,mobile,avatar', 'id');
                $runners = $runner_list;
            }
            
            foreach ($list as &$item) {
                $item['order_no'] = $orders[$item['order_id']] ?? '';
                $item['runner_name'] = $runners[$item['runner_id']]['real_name'] ?? '';
                $item['runner_mobile'] = $runners[$item['runner_id']]['mobile'] ?? '';
                $item['runner_avatar'] = $runners[$item['runner_id']]['avatar'] ?? '';
            }
            unset($item);
        }
        
        // 统计
        $stat = [
            'pending' => $this->model->where([['site_id', '=', $siteId], ['status', '=', 0]])->count(),
            'passed' => $this->model->where([['site_id', '=', $siteId], ['status', '=', 1]])->count(),
            'rejected' => $this->model->where([['site_id', '=', $siteId], ['status', '=', 2]])->count(),
        ];
        
        return [
            'list' => $list,
            'count' => $count,
            'stat' => $stat
        ];
    }

    public function getDetail($id, $siteId)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->findOrEmpty()->toArray();
    }

    public function handle($id, $siteId, $status, $reply)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->update([
            'status' => $status,
            'reply' => $reply,
            'handle_time' => time()
        ]);
    }
}
