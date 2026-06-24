<?php

namespace addon\sd_xiaoyuan\app\api\controller\runner;

use addon\sd_xiaoyuan\app\service\core\RunnerService;
use addon\sd_xiaoyuan\app\model\RunnerBalanceLog;
use addon\sd_xiaoyuan\app\model\order\Order;
use core\base\BaseApiController;

class Runner extends BaseApiController
{
    public function apply()
    {
        $data = $this->request->params([
            ['real_name', ''],
            ['mobile', ''],
            ['avatar', ''],
            ['student_cert', ''],
            ['school_id', 0],
            ['campus', '']
        ]);
        
        $service = new RunnerService();
        $result = $service->apply($data);
        
        if ($result['code'] != 0) {
            return fail($result['msg'] ?? '申请失败');
        }
        if (!empty($result['need_pay'])) {
            return success([
                'need_pay' => 1,
                'order_id' => $result['order_id'],
                'trade_type' => 'sd_xiaoyuan_card',
            ], '请完成入驻费用支付');
        }
        return success('申请成功，请等待审核');
    }

    public function info()
    {
        $service = new RunnerService();
        $info = $service->getInfo();
        return success($info);
    }

    public function updateLocation()
    {
        $lng = $this->request->param('lng', '');
        $lat = $this->request->param('lat', '');
        
        $service = new RunnerService();
        $info = $service->getInfo();
        
        if (empty($info)) {
            return fail('跑腿员信息不存在');
        }
        
        $service->updateLocation($info['id'], $lng, $lat);
        return success('更新成功');
    }

    public function setOnline()
    {
        $isOnline = $this->request->param('is_online', 0);
        
        $service = new RunnerService();
        $info = $service->getInfo();
        
        if (empty($info)) {
            return fail('跑腿员信息不存在');
        }
        
        if ($info['status'] != 1) {
            return fail('您的账号未通过审核或已被禁用');
        }
        
        $service->setOnlineStatus($info['id'], $isOnline);
        return success('设置成功');
    }

    public function subscribeRecord()
    {
        $num = (int)$this->request->param('num', 1);
        $service = new RunnerService();
        $result = $service->addWeappSubscribe($num);
        if (!empty($result['code'])) {
            return fail($result['msg'] ?? '记录失败');
        }
        return success([
            'weapp_subscribe_num' => $result['weapp_subscribe_num'] ?? 0,
        ], '授权成功');
    }

    public function updateInfo()
    {
        $data = $this->request->params([
            ['real_name', ''],
            ['mobile', ''],
            ['avatar', '']
        ]);
        
        $service = new RunnerService();
        $info = $service->getInfo();
        
        if (empty($info)) {
            return fail('跑腿员信息不存在');
        }
        
        $updateData = [];
        if (!empty($data['real_name'])) $updateData['real_name'] = $data['real_name'];
        if (!empty($data['mobile'])) $updateData['mobile'] = $data['mobile'];
        if (!empty($data['avatar'])) $updateData['avatar'] = $data['avatar'];
        
        if (!empty($updateData)) {
            $updateData['update_time'] = time();
            (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where('id', $info['id'])->update($updateData);
        }
        
        return success('更新成功');
    }

    public function setRange()
    {
        $data = $this->request->params([
            ['accept_types', '']
        ]);
        
        $service = new RunnerService();
        $info = $service->getInfo();
        
        if (empty($info)) {
            return fail('跑腿员信息不存在');
        }
        
        $service->setRange($info['id'], $data);
        return success('设置成功');
    }

    public function stat()
    {
        $service = new RunnerService();
        $info = $service->getInfo();
        
        if (empty($info)) {
            return fail('跑腿员信息不存在');
        }
        
        // 从框架会员账户读取可提现金额(money字段)
        $memberModel = new \app\model\member\Member();
        $member = $memberModel->where('member_id', $info['member_id'])->field('money,money_cash_outing')->findOrEmpty()->toArray();
        
        $orderModel = new Order();
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd = $todayStart + 86400;
        
        $stat = [
            'balance' => $member['money'] ?? 0,
            'freeze_balance' => $member['money_cash_outing'] ?? 0,
            'total_income' => $info['total_income'],
            'total_orders' => $info['total_orders'],
            'complete_orders' => $info['complete_orders'],
            'score' => $info['score'],
            'level' => $info['level'],
            'today_orders' => $orderModel->where([
                ['runner_id', '=', $info['id']],
                ['accept_time', '>=', $todayStart],
                ['accept_time', '<', $todayEnd]
            ])->count(),
            'today_income' => $orderModel->where([
                ['runner_id', '=', $info['id']],
                ['status', '=', 50],
                ['complete_time', '>=', $todayStart],
                ['complete_time', '<', $todayEnd]
            ])->sum('runner_income') ?: 0
        ];
        
        return success($stat);
    }

    public function income()
    {
        $params = $this->request->params([
            ['type', 'week'],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new RunnerService();
        $info = $service->getInfo();
        
        if (empty($info)) {
            return fail('跑腿员信息不存在');
        }
        
        $orderModel = new Order();
        $tipModel = new \addon\sd_xiaoyuan\app\model\TipOrder();
        
        if ($params['type'] == 'week') {
            $startTime = strtotime('-7 days');
        } elseif ($params['type'] == 'month') {
            $startTime = strtotime('-30 days');
        } else {
            $startTime = 0;
        }
        
        // 订单收益
        $orderWhere = [
            ['runner_id', '=', $info['id']],
            ['status', '=', 50]
        ];
        if ($startTime > 0) {
            $orderWhere[] = ['complete_time', '>=', $startTime];
        }
        
        $orderList = $orderModel->where($orderWhere)
            ->field('id, order_no, task_type, runner_income as amount, complete_time as time')
            ->order('complete_time desc')
            ->select()
            ->toArray();
        foreach ($orderList as &$item) {
            $item['income_type'] = 'order';
            $item['remark'] = '订单收益';
        }
        unset($item);
        
        // 小费收入
        $tipWhere = [
            ['runner_id', '=', $info['id']],
            ['pay_status', '=', 1]
        ];
        if ($startTime > 0) {
            $tipWhere[] = ['pay_time', '>=', $startTime];
        }
        
        $tipList = $tipModel->where($tipWhere)
            ->field('id, order_id, amount, pay_time as time')
            ->order('pay_time desc')
            ->select()
            ->toArray();
        
        // 查关联订单号
        $tipOrderIds = array_filter(array_column($tipList, 'order_id'));
        $orderNos = [];
        if (!empty($tipOrderIds)) {
            $nos = $orderModel->where([['id', 'in', $tipOrderIds]])->column('order_no', 'id');
            $orderNos = $nos;
        }
        foreach ($tipList as &$tip) {
            $tip['income_type'] = 'tip';
            $tip['remark'] = '小费收入';
            $tip['order_no'] = $orderNos[$tip['order_id']] ?? '';
            $tip['task_type'] = '';
        }
        unset($tip);
        
        // 合并并按时间倒序
        $merged = array_merge($orderList, $tipList);
        usort($merged, function($a, $b) {
            return ($b['time'] ?? 0) - ($a['time'] ?? 0);
        });
        
        $total = count($merged);
        $offset = ($params['page'] - 1) * $params['limit'];
        $list = array_slice($merged, $offset, $params['limit']);
        
        $orderTotalIncome = $orderModel->where($orderWhere)->sum('runner_income') ?: 0;
        $tipTotalIncome = $tipModel->where($tipWhere)->sum('amount') ?: 0;
        $totalIncome = round($orderTotalIncome + $tipTotalIncome, 2);
        
        return success([
            'list' => array_values($list),
            'count' => $total,
            'total_income' => $totalIncome
        ]);
    }

    public function balanceLog()
    {
        $params = $this->request->params([
            ['type', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new RunnerService();
        $info = $service->getInfo();
        
        if (empty($info)) {
            return fail('跑腿员信息不存在');
        }
        
        $logModel = new RunnerBalanceLog();
        $where = [
            ['runner_id', '=', $info['id']]
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
        
        return success([
            'list' => $list,
            'count' => $count
        ]);
    }
}
