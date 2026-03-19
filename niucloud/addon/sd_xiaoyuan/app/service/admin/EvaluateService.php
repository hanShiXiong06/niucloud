<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\Evaluate;
use core\base\BaseService;

class EvaluateService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Evaluate();
    }

    public function getList($siteId, $params)
    {
        $where = [['site_id', '=', $siteId]];
        
        if (!empty($params['runner_id'])) {
            $where[] = ['runner_id', '=', $params['runner_id']];
        }
        
        if (isset($params['score']) && $params['score'] !== '') {
            $where[] = ['score', '=', $params['score']];
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
                // 格式化时间
                if (!empty($item['create_time'])) {
                    $item['create_time'] = date('Y-m-d H:i:s', is_numeric($item['create_time']) ? $item['create_time'] : strtotime($item['create_time']));
                }
            }
            unset($item);
        }
        
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

    public function delete($id, $siteId)
    {
        return $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->delete();
    }
}
