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
        // 审核通过后默认在线、可接单
        if ($status == 1) {
            $update['is_online'] = 1;
            $update['can_jiedan'] = 1;
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

        // 审核通过：同步等级表中的佣金比例等到 runner 表（与 getInfo/checkAndUpgrade 一致，避免预估用错比例）
        if ($status == 1) {
            try {
                (new \addon\sd_xiaoyuan\app\service\core\RunnerLevelService())->checkAndUpgrade((int)$id);
            } catch (\Throwable $e) {
                trace('sd_xiaoyuan runner audit checkAndUpgrade: ' . $e->getMessage(), 'error');
            }
            try {
                (new \addon\sd_xiaoyuan\app\service\core\RunnerInviteService())->onRunnerApproved((int)$id);
            } catch (\Throwable $e) {
                trace('sd_xiaoyuan runner audit invite: ' . $e->getMessage(), 'error');
            }
        }
        
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
            ['is_online', '=', 1],
            ['can_jiedan', '=', 1]
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
        $siteId = $this->request->siteId();
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd = $todayStart + 86400;
        $orderModel = new Order();

        // 与后台 runner/list.vue 统计字段一致：total / online / pending_audit / today_orders
        $stat = [
            'total' => $model->where('site_id', $siteId)->count(),
            'online' => $model->where([
                ['site_id', '=', $siteId],
                ['is_online', '=', 1],
                ['status', '=', 1],
            ])->count(),
            'pending_audit' => $model->where([
                ['site_id', '=', $siteId],
                ['status', '=', 0],
            ])->count(),
            'today_orders' => $orderModel->where([
                ['site_id', '=', $siteId],
                ['runner_id', '>', 0],
                ['accept_time', '>=', $todayStart],
                ['accept_time', '<', $todayEnd],
            ])->count(),
        ];

        return success($stat);
    }

    public function setCanJiedan()
    {
        $id = (int)$this->request->param('id', 0);
        $can_jiedan = (int)$this->request->param('can_jiedan', 1);
        if ($id <= 0) {
            return fail('参数错误');
        }
        $can_jiedan = $can_jiedan === 1 ? 1 : 0;
        $model = new RunnerModel();
        $runner = $model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();
        if (empty($runner)) {
            return fail('接单员不存在');
        }
        $model->where('id', $id)->update([
            'can_jiedan' => $can_jiedan,
            'update_time' => time()
        ]);
        return success('操作成功');
    }

    /**
     * 删除接单员
     */
    public function delete()
    {
        $id = (int)$this->request->param('id', 0);
        if ($id <= 0) {
            return fail('参数错误');
        }
        $siteId = $this->request->siteId();
        $model = new RunnerModel();
        $runner = $model->where([
            ['id', '=', $id],
            ['site_id', '=', $siteId]
        ])->find();
        if (empty($runner)) {
            return fail('接单员不存在');
        }
        $activeCount = (new Order())->where([
            ['runner_id', '=', $id],
            ['site_id', '=', $siteId],
            ['status', 'in', [10, 20, 30, 40, 45]]
        ])->count();
        if ($activeCount > 0) {
            return fail('该接单员还有进行中的订单，无法删除');
        }
        $model->where('id', $id)->delete();
        return success('删除成功');
    }
}
