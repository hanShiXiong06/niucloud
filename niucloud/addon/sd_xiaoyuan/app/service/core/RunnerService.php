<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use addon\sd_xiaoyuan\app\service\core\NoticeWechatService;
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
            if ((int)$exists['status'] === 1) {
                return ['code' => 1, 'msg' => '您已是接单员'];
            }
            if ((int)$exists['status'] === 0) {
                return ['code' => 1, 'msg' => '申请审核中，请勿重复提交'];
            }
            $updateData = [
                'real_name' => $data['real_name'],
                'mobile' => $data['mobile'],
                'avatar' => $data['avatar'],
                'student_cert' => $data['student_cert'],
                'school_id' => $data['school_id'],
                'campus' => $data['campus'],
                'status' => 0,
                'refuse_reason' => '',
                'update_time' => time()
            ];
            $this->model->where('id', $exists['id'])->update($updateData);
            $runnerId = (int)$exists['id'];
            $payResult = $this->handleRunnerApplyPay($runnerId);
            if (!empty($payResult['need_pay'])) {
                return ['code' => 0, 'need_pay' => 1, 'order_id' => $payResult['order_id'], 'data' => $exists];
            }
            (new MessageService())->send($this->member_id, 'SYSTEM', '接单员重新申请已提交', '您的接单员重新申请已提交，请耐心等待审核');
            $this->sendRunnerApplyNotice($runnerId);
            return ['code' => 0, 'data' => $exists];
        }

        // 首次申请
        $data['member_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $data['update_time'] = time();
        $data['status'] = 0;
        $data['can_jiedan'] = 1;

        $res = $this->model->create($data);
        $runnerId = (int)$res->id;
        $payResult = $this->handleRunnerApplyPay($runnerId);
        if (!empty($payResult['need_pay'])) {
            return ['code' => 0, 'need_pay' => 1, 'order_id' => $payResult['order_id'], 'data' => $res];
        }
        (new MessageService())->send($this->member_id, 'SYSTEM', '接单员入驻申请已提交', '您的接单员入驻申请已提交，请耐心等待审核');
        $this->sendRunnerApplyNotice($runnerId);
        return ['code' => 0, 'data' => $res];
    }

    private function handleRunnerApplyPay(int $runner_id): array
    {
        $config = (new ConfigService())->getConfig($this->site_id);
        $fee = floatval($config['runner_apply_fee'] ?? 0);
        $enable = (int)($config['runner_apply_pay_enable'] ?? 0);
        if ($enable !== 1 || $fee <= 0) {
            return ['need_pay' => 0];
        }
        $orderId = (new CardService())->createRunnerApplyOrder($runner_id, $fee);
        return ['need_pay' => 1, 'order_id' => $orderId];
    }

    /**
     * 骑手申请公众号通知管理员
     */
    private function sendRunnerApplyNotice(int $runner_id)
    {
        if (!$runner_id) {
            return;
        }
        $noticeWechat = new NoticeWechatService();
        if (!$noticeWechat->isEnabled($this->site_id)) {
            return;
        }
        $admin_ids = $noticeWechat->parseAdminMemberIds($this->site_id);
        if (empty($admin_ids)) {
            return;
        }
        foreach ($admin_ids as $member_id) {
            $noticeWechat->send($this->site_id, 'sd_xiaoyuan_runner_apply', [
                'runner_id' => $runner_id,
                'member_id' => $member_id,
            ]);
        }
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

        // 按完成单量同步等级，并用后台等级配置覆盖展示名称（避免 runner 表 level_name 仍为旧文案）
        if (!empty($info['id'])) {
            try {
                (new RunnerLevelService())->checkAndUpgrade((int)$info['id']);
            } catch (\Throwable $e) {
                trace('sd_xiaoyuan runner checkAndUpgrade: ' . $e->getMessage(), 'error');
            }
            $info = $this->model->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id],
            ])->findOrEmpty()->toArray();
        if (!empty($info)) {
            foreach ((new RunnerLevelService())->getLevelList() as $row) {
                if ((int)($row['level'] ?? 0) === (int)($info['level'] ?? 1)) {
                    if (!empty($row['name'])) {
                        $info['level_name'] = $row['name'];
                    }
                    break;
                }
            }
            if (!empty($info['school_id'])) {
                $school = (new \addon\sd_xiaoyuan\app\model\School())->where('id', $info['school_id'])->value('name');
                $info['school_name'] = $school ?: '';
            } else {
                $info['school_name'] = '';
            }
            if ((int)$info['status'] === 0) {
                $unpaid = (new \addon\sd_xiaoyuan\app\model\CardOrder())->where([
                    ['site_id', '=', $this->site_id],
                    ['member_id', '=', $this->member_id],
                    ['card_type', '=', 'RUNNER'],
                    ['runner_id', '=', (int)$info['id']],
                    ['pay_status', '=', 0],
                ])->order('id desc')->find();
                if ($unpaid) {
                    $info['need_pay'] = 1;
                    $info['pay_order_id'] = (int)$unpaid['id'];
                }
            }
        }
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

    public function addWeappSubscribe(int $num = 1): array
    {
        $info = $this->getInfo();
        if (empty($info)) {
            return ['code' => 1, 'msg' => '接单员信息不存在'];
        }
        if ((int)$info['status'] !== 1) {
            return ['code' => 1, 'msg' => '账号未通过审核'];
        }
        $num = max(1, min(3, $num));
        $this->model->where('id', $info['id'])->inc('weapp_subscribe_num', $num)->update(['update_time' => time()]);
        $left = (int)$this->model->where('id', $info['id'])->value('weapp_subscribe_num');
        return ['code' => 0, 'weapp_subscribe_num' => $left];
    }

    public function useWeappSubscribe(int $runner_id): bool
    {
        if ($runner_id <= 0) {
            return false;
        }
        $res = $this->model->where([
            ['id', '=', $runner_id],
            ['weapp_subscribe_num', '>', 0],
        ])->dec('weapp_subscribe_num')->update(['update_time' => time()]);
        return $res > 0;
    }
}
