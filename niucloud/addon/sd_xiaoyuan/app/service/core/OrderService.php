<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Credit;
use app\model\member\Member;
use core\base\BaseApiService;

class OrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    public function create($data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['order_no'] = $this->generateOrderNo();
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['expire_pay_time'] = time() + 1800;
        
                
        // 确保actual_fee有值，如果没有则使用total_fee
        if (!isset($data['actual_fee']) || $data['actual_fee'] <= 0) {
            $data['actual_fee'] = $data['total_fee'] ?? 0;
        }
        
        $result = $this->model->create($data);
        return ['id' => $result->id, 'order_no' => $data['order_no']];
    }

    public function calculateFee($params)
    {
        $distance = $params['distance'] ?? 0;
        $weight = $params['weight'] ?? 0;
        $isUrgent = $params['is_urgent'] ?? 0;
        
        $baseFee = 3.00;
        $distanceFee = $distance > 2 ? ($distance - 2) * 1.5 : 0;
        $weightFee = $weight > 5 ? ($weight - 5) * 0.5 : 0;
        $urgentFee = $isUrgent ? 5.00 : 0;
        
        $totalFee = $baseFee + $distanceFee + $weightFee + $urgentFee;
        
        return [
            'base_fee' => $baseFee,
            'distance_fee' => $distanceFee,
            'weight_fee' => $weightFee,
            'urgent_fee' => $urgentFee,
            'total_fee' => $totalFee
        ];
    }

    private function generateOrderNo()
    {
        return 'XY' . date('YmdHis') . rand(1000, 9999);
    }

    public function getOrderList($params)
    {
        $memberId = $params['member_id'] ?? 0;
        $runnerId = $params['runner_id'] ?? 0;
        $status = $params['status'] ?? '';
        $taskType = $params['task_type'] ?? '';
        $schoolId = $params['school_id'] ?? 0;
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 10);

        $where = [['site_id', '=', $this->site_id]];
        
        if ($memberId) {
            $where[] = ['member_id', '=', $memberId];
        }

        if ($runnerId) {
            $where[] = ['runner_id', '=', $runnerId];
        }
        
        if ($status !== '') {
            if (is_string($status) && strpos($status, ',') !== false) {
                $where[] = ['status', 'in', array_map('intval', explode(',', $status))];
            } else {
                $where[] = ['status', '=', $status];
            }
        }

        if ($taskType !== '') {
            $where[] = ['task_type', '=', $taskType];
        }

        if ($schoolId) {
            $where[] = ['school_id', '=', $schoolId];
        }

        $orderNo = $params['order_no'] ?? '';
        if ($orderNo !== '') {
            $where[] = ['order_no', 'like', '%' . $orderNo . '%'];
        }

        $keyword = $params['keyword'] ?? '';
        if ($keyword !== '') {
            $where[] = ['order_no|pickup_name|pickup_mobile|receive_name|receive_mobile|task_desc', 'like', '%' . $keyword . '%'];
        }

        // 接单员订单按接单时间倒序，其他按创建时间倒序
        $orderBy = $runnerId ? 'accept_time desc, create_time desc' : 'create_time desc';

        $list = $this->model->where($where)
            ->order($orderBy)
            ->page($page, $limit)
            ->select()
            ->toArray();

        $count = $this->model->where($where)->count();

        // 附加会员头像、昵称和信誉分
        $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
        $members = [];
        $credits = [];
        if (!empty($member_ids)) {
            $member_list = (new Member())->where([['member_id', 'in', $member_ids]])->field('member_id,nickname,headimg')->select()->toArray();
            foreach ($member_list as $m) {
                $members[$m['member_id']] = $m;
            }
            $credit_list = (new Credit())->where([['member_id', 'in', $member_ids], ['site_id', '=', $this->site_id]])->field('member_id,credit_score')->select()->toArray();
            foreach ($credit_list as $c) {
                $credits[$c['member_id']] = $c['credit_score'];
            }
        }
        // 附加接单员信息
        $runner_ids = array_unique(array_filter(array_column($list, 'runner_id')));
        $runners = [];
        if (!empty($runner_ids)) {
            $runner_list = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where([['id', 'in', $runner_ids]])->field('id,real_name,mobile,avatar,score')->select()->toArray();
            foreach ($runner_list as $r) {
                $runners[$r['id']] = $r;
            }
        }

        // 批量查询已评价的订单ID
        $order_ids = array_column($list, 'id');
        $evaluated_ids = [];
        if (!empty($order_ids)) {
            $evaluateModel = new \addon\sd_xiaoyuan\app\model\Evaluate();
            $evaluated_list = $evaluateModel->where([['order_id', 'in', $order_ids]])->column('order_id');
            $evaluated_ids = array_flip($evaluated_list);
        }

        foreach ($list as &$item) {
            $mid = $item['member_id'] ?? 0;
            $item['member_nickname'] = $members[$mid]['nickname'] ?? '';
            $item['member_headimg'] = $members[$mid]['headimg'] ?? '';
            $item['credit_score'] = $credits[$mid] ?? Credit::INIT_SCORE;

            // 附加接单员名称
            $rid = $item['runner_id'] ?? 0;
            $item['runner_name'] = $runners[$rid]['real_name'] ?? '';
            $item['runner_mobile'] = $runners[$rid]['mobile'] ?? '';

            // 是否已评价
            $item['is_evaluated'] = isset($evaluated_ids[$item['id']]) ? 1 : 0;

            // 解析ext JSON，展开到item中方便前端使用
            if (!empty($item['ext'])) {
                $extData = json_decode($item['ext'], true);
                if (is_array($extData)) {
                    $item['ext_data'] = $extData;
                } else {
                    $item['ext_data'] = [];
                }
            } else {
                $item['ext_data'] = [];
            }
        }
        unset($item);

        return [
            'list' => $list,
            'count' => $count,
            'page' => $page,
            'limit' => $limit
        ];
    }

    public function getMyOrderList($params)
    {
        $params['member_id'] = $this->member_id;
        return $this->getOrderList($params);
    }

    public function getOrderDetail($orderId, $siteId = 0)
    {
        $sid = $siteId ?: $this->site_id;
        $item = $this->model->where([
            ['id', '=', $orderId],
            ['site_id', '=', $sid]
        ])->findOrEmpty()->toArray();

        if (!empty($item)) {
            if (!empty($item['ext'])) {
                $extData = json_decode($item['ext'], true);
                $item['ext_data'] = is_array($extData) ? $extData : [];
            } else {
                $item['ext_data'] = [];
            }

            // 附加会员信息
            if (!empty($item['member_id'])) {
                $member = (new Member())->where('member_id', $item['member_id'])->field('member_id,nickname,headimg')->findOrEmpty()->toArray();
                $item['member_nickname'] = $member['nickname'] ?? '';
                $item['member_headimg'] = $member['headimg'] ?? '';
                $credit = (new Credit())->where([['member_id', '=', $item['member_id']], ['site_id', '=', $sid]])->value('credit_score');
                $item['credit_score'] = $credit ?: Credit::INIT_SCORE;
            }

            // 是否已评价
            $evaluateModel = new \addon\sd_xiaoyuan\app\model\Evaluate();
            $item['is_evaluated'] = $evaluateModel->where('order_id', $orderId)->count() > 0 ? 1 : 0;

            // 附加接单员信息
            if (!empty($item['runner_id'])) {
                $runner = (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where('id', $item['runner_id'])->field('id,real_name,mobile,avatar,score')->findOrEmpty()->toArray();
                $item['runner_name'] = $runner['real_name'] ?? '';
                $item['runner_mobile'] = $runner['mobile'] ?? '';
                $item['runner_avatar'] = $runner['avatar'] ?? '';
                $item['runner_score'] = $runner['score'] ?? 0;
            } else {
                $item['runner_name'] = '';
                $item['runner_mobile'] = '';
                $item['runner_avatar'] = '';
                $item['runner_score'] = 0;
            }
        }

        return $item;
    }

    public function updateStatus($orderId, $status, $data = [])
    {
        $updateData = array_merge($data, [
            'status' => $status,
            'update_time' => time()
        ]);
        
        $result = $this->model->where('id', $orderId)->update($updateData);
        
        // 如果是接单状态，发送接单消息
        if ($status == 20 && $result) {
            $order = $this->model->where('id', $orderId)->find();
            if ($order) {
                (new \addon\sd_xiaoyuan\app\service\core\MessageService())->send(
                    $order['member_id'], 
                    'ORDER', 
                    '订单已被接单', 
                    '您的订单已被接单员接单，接单员正在处理中', 
                    [
                        'link_type' => 'order_detail',
                        'link_id' => $orderId,
                        'order_id' => $orderId
                    ]
                );
            }
        }
        
        return $result;
    }
}
