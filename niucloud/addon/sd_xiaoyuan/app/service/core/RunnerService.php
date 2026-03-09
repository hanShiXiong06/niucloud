<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use app\service\core\member\CoreMemberAccountService;
use app\dict\member\MemberAccountTypeDict;
use core\base\BaseApiService;

class RunnerService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Runner();
    }

    public function apply($data)
    {
        $exists = $this->model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();

        if (!$exists->isEmpty()) {
            // 重新申请，更新现有记录并重置为待审核状态
            $updateData = [
                'real_name' => $data['real_name'],
                'mobile' => $data['mobile'],
                'avatar' => $data['avatar'],
                'student_cert' => $data['student_cert'],
                'school_id' => $data['school_id'],
                'campus' => $data['campus'],
                'status' => 0, // 重置为待审核
                'audit_time' => 0, // 清空审核时间
                'refuse_reason' => '', // 清空拒绝原因
                'update_time' => time()
            ];
            
            $this->model->where('id', $exists['id'])->update($updateData);
            
            // 通知申请人：重新申请已提交
            (new MessageService())->send($this->member_id, 'SYSTEM', '接单员重新申请已提交', '您的接单员重新申请已提交，请耐心等待审核');
            
            return ['code' => 0, 'data' => $exists];
        }

        // 首次申请
        $data['member_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['status'] = 0;

        $res = $this->model->create($data);

        // 通知申请人：申请已提交
        (new MessageService())->send($this->member_id, 'SYSTEM', '接单员入驻申请已提交', '您的接单员入驻申请已提交，请耐心等待审核');

        return ['code' => 0, 'data' => $res];
    }

    public function getInfo()
    {
        $info = $this->model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();

        if (!empty($info) && !empty($info['school_id'])) {
            $school = (new \addon\sd_xiaoyuan\app\model\School())->where('id', $info['school_id'])->value('name');
            $info['school_name'] = $school ?: '';
        } else if (!empty($info)) {
            $info['school_name'] = '';
        }

        return $info;
    }

    public function updateLocation($runnerId, $lng, $lat)
    {
        return $this->model->where('id', $runnerId)->update([
            'lng' => $lng,
            'lat' => $lat,
            'last_location_time' => time(),
            'update_time' => time()
        ]);
    }

    public function setOnlineStatus($runnerId, $isOnline)
    {
        return $this->model->where('id', $runnerId)->update([
            'is_online' => $isOnline,
            'update_time' => time()
        ]);
    }

    public function getNearbyRunners($lng, $lat, $limit = 10)
    {
        return $this->model->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['is_online', '=', 1]
        ])->limit($limit)->select()->toArray();
    }

    public function setRange($runnerId, $data)
    {
        $update = ['update_time' => time()];
        if (isset($data['accept_types'])) {
            $update['accept_types'] = $data['accept_types'];
        }
        return $this->model->where('id', $runnerId)->update($update);
    }

    public function updateScore($runnerId)
    {
        $evaluateModel = new \addon\sd_xiaoyuan\app\model\Evaluate();
        $avgScore = $evaluateModel->where('runner_id', $runnerId)->avg('score');
        
        if ($avgScore) {
            $this->model->where('id', $runnerId)->update([
                'score' => round($avgScore, 1),
                'update_time' => time()
            ]);
        }
    }

    public function addIncome($runnerId, $amount, $orderId, $remark = '')
    {
        $runner = $this->model->where('id', $runnerId)->find();
        if (!$runner) {
            return false;
        }
        
        // 更新接单员统计数据
        $this->model->where('id', $runnerId)->update([
            'total_income' => $runner['total_income'] + $amount,
            'complete_orders' => $runner['complete_orders'] + 1,
            'update_time' => time()
        ]);
        
        // 使用框架的账户系统发放收益到可提现金额
        (new CoreMemberAccountService())->addLog(
            $this->site_id,
            $runner['member_id'],
            MemberAccountTypeDict::MONEY,
            $amount,
            'sd_xiaoyuan_runner_income',
            $remark ?: '跟腿订单收益',
            $orderId
        );
        
        return true;
    }
}
