<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\runner\Runner as RunnerModel;
use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use core\base\BaseAdminController;

class Runner extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['school_id', ''],
            ['keyword', ''],
            ['real_name', ''],
            ['mobile', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $where = [['site_id', '=', $this->request->siteId()]];
        
        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        
        if (!empty($params['school_id'])) {
            $where[] = ['school_id', '=', $params['school_id']];
        }
        
        if ($params['keyword']) {
            $where[] = ['real_name|mobile', 'like', '%' . $params['keyword'] . '%'];
        }
        
        if ($params['real_name']) {
            $where[] = ['real_name', 'like', '%' . $params['real_name'] . '%'];
        }
        
        if ($params['mobile']) {
            $where[] = ['mobile', 'like', '%' . $params['mobile'] . '%'];
        }
        
        $model = new RunnerModel();
        
        $list = $model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
        
        $count = $model->where($where)->count();
        
        // 统计每个接单员的订单数
        $orderModel = new Order();
        foreach ($list as &$item) {
            $item['total_orders'] = $orderModel->where([
                ['runner_id', '=', $item['id']],
                ['site_id', '=', $this->request->siteId()]
            ])->count();
            $item['complete_orders'] = $orderModel->where([
                ['runner_id', '=', $item['id']],
                ['site_id', '=', $this->request->siteId()],
                ['status', '=', 50]
            ])->count();
        }
        unset($item);
        
        // 关联学校名称
        if (!empty($list)) {
            $school_ids = array_unique(array_filter(array_column($list, 'school_id')));
            $schools = [];
            if (!empty($school_ids)) {
                $schools = (new \addon\sd_xiaoyuan\app\model\School())->where([['id', 'in', $school_ids]])->column('name', 'id');
            }
            foreach ($list as &$item) {
                $item['school_name'] = $schools[$item['school_id'] ?? 0] ?? '';
            }
            unset($item);
        }
        
        return success([
            'list' => $list,
            'count' => $count
        ]);
    }

    public function audit()
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 0);
        $refuseReason = $this->request->param('refuse_reason', '');
        
        $update = [
            'status' => $status,
            'refuse_reason' => $refuseReason,
            'update_time' => time()
        ];
        // 审核通过后默认在线
        if ($status == 1) {
            $update['is_online'] = 1;
        }
        
        $model = new RunnerModel();
        $runner = $model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();
        
        if (empty($runner)) {
            return fail('接单员不存在');
        }
        
        $model->where('id', $id)->update($update);
        
        // 通知接单员审核结果
        $msgService = new MessageService();
        if ($status == 1) {
            $msgService->sendDirect($this->request->siteId(), $runner['member_id'], 'SYSTEM', '接单员入驻审核通过', '恭喜！您的接单员入驻申请已通过审核，您已默认上线，可以开始接单了');
        } elseif ($status == 2) {
            $reason = $refuseReason ? "，原因：{$refuseReason}" : '';
            $msgService->sendDirect($this->request->siteId(), $runner['member_id'], 'SYSTEM', '接单员入驻审核未通过', "很抱歉，您的接单员入驻申请未通过审核{$reason}，您可以修改信息后重新申请");
        }
        
        return success('操作成功');
    }

    /**
     * 获取在线接单员列表（用于指派订单）
     */
    public function onlineList()
    {
        $keyword = $this->request->param('keyword', '');
        $school_id = $this->request->param('school_id', 0);
        
        $where = [
            ['site_id', '=', $this->request->siteId()],
            ['status', '=', 1],
            ['is_online', '=', 1]
        ];
        
        if ($keyword) {
            $where[] = ['real_name|mobile', 'like', '%' . $keyword . '%'];
        }
        
        if ($school_id > 0) {
            $where[] = ['school_id', '=', $school_id];
        }
        
        $model = new RunnerModel();
        $list = $model->where($where)
            ->field('id,real_name,mobile,avatar,score,is_online,today_orders,complete_orders,school_id')
            ->order('score desc')
            ->limit(20)
            ->select()
            ->toArray();
        
        return success(['list' => $list]);
    }

    public function stat()
    {
        $model = new RunnerModel();
        
        $stat = [
            'total_runners' => $model->where('site_id', $this->request->siteId())->count(),
            'online_runners' => $model->where([
                ['site_id', '=', $this->request->siteId()],
                ['is_online', '=', 1]
            ])->count(),
            'pending_audit' => $model->where([
                ['site_id', '=', $this->request->siteId()],
                ['status', '=', 0]
            ])->count()
        ];
        
        return success($stat);
    }
}
