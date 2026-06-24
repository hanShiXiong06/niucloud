<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\model\MemberRelation;
use app\model\member\MemberAccountLog;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\sys\CoreConfigService;
use app\dict\member\MemberAccountTypeDict;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 接单员邀请奖励服务
 */
class RunnerInviteService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 获取邀请配置
     */
    public function getInviteConfig()
    {
        // 默认与后台「接单员等级」里邀请配置（SD_XIAOYUAN_RUNNER_INVITE）一致；未保存前用下列默认值
        $config = [
            'reward_amount' => 10.00,
            'reward_type' => 'balance',
            'require_orders' => 1,
            'is_enabled' => 1
        ];
        $saved = (new CoreConfigService())->getConfig($this->site_id, 'SD_XIAOYUAN_RUNNER_INVITE');
        if (isset($saved['value']) && is_array($saved['value'])) {
            $config = array_merge($config, $saved['value']);
        }
        return $config;
    }

    /**
     * 绑定邀请关系
     */
    public function bindInviter(int $inviteeId, int $inviterId)
    {
        if ($inviteeId == $inviterId) {
            throw new CommonException('不能邀请自己');
        }

        // 检查邀请人是否是接单员
        $inviter = (new Runner())->where([
            ['id', '=', $inviterId],
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ])->find();

        if (empty($inviter)) {
            throw new CommonException('邀请人不是有效接单员');
        }

        // 检查被邀请人是否已有邀请人
        $invitee = (new Runner())->where([
            ['id', '=', $inviteeId],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($invitee)) {
            throw new CommonException('被邀请人不存在');
        }

        if ($invitee->inviter_id > 0) {
            throw new CommonException('已有邀请人');
        }

        // 绑定邀请关系
        $invitee->save(['inviter_id' => $inviterId]);

        return true;
    }

    /**
     * 接单员审核通过后发放邀请奖励
     */
    public function onRunnerApproved(int $runnerId)
    {
        $runner = (new Runner())->where('id', $runnerId)->find();
        if (empty($runner) || $runner->inviter_id <= 0) {
            return;
        }

        $config = $this->getInviteConfig();
        if (!$config['is_enabled']) {
            return;
        }

        // 如果不需要完成订单就发放奖励
        if ($config['require_orders'] <= 0) {
            if (empty($runner->invite_reward_granted)) {
                $this->grantReward($runner->inviter_id, $runnerId, $config['reward_amount']);
                $runner->save(['invite_reward_granted' => 1]);
            }
        }
        // 需要完成订单才发放的情况，在onOrderCompleted中处理
    }

    /**
     * 订单完成后检查是否发放邀请奖励
     */
    public function onOrderCompleted(int $runnerId)
    {
        $runner = (new Runner())->where('id', $runnerId)->find();
        if (empty($runner) || $runner->inviter_id <= 0) {
            return;
        }

        $config = $this->getInviteConfig();
        if (!$config['is_enabled'] || $config['require_orders'] <= 0) {
            return;
        }

        // 检查是否达到要求的订单数且未发放过奖励
        if ($runner->complete_orders >= $config['require_orders'] && !$runner->invite_reward_granted) {
            $this->grantReward($runner->inviter_id, $runnerId, $config['reward_amount']);
            // 标记已发放
            $runner->save(['invite_reward_granted' => 1]);
        }
    }

    /**
     * 发放奖励
     */
    protected function grantReward(int $inviterId, int $inviteeId, float $amount)
    {
        $inviter = (new Runner())->where('id', $inviterId)->find();
        if (empty($inviter)) {
            return;
        }

        // 使用框架账户系统发放邀请奖励到可提现金额
        (new CoreMemberAccountService())->addLog(
            $this->site_id,
            $inviter['member_id'],
            MemberAccountTypeDict::MONEY,
            $amount,
            'sd_xiaoyuan_invite_reward',
            '邀请接单员奖励',
            $inviteeId
        );

        // 更新接单员统计
        $inviter->inc('total_income', $amount)->update();
    }

    /**
     * 获取邀请的接单员列表
     */
    public function getInvitedRunners(int $inviterId, int $page = 1, int $limit = 10)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['inviter_id', '=', $inviterId]
        ];

        $model = new Runner();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('id desc')
            ->select()
            ->toArray();

        return ['count' => $count, 'list' => $list];
    }

    /**
     * 获取邀请统计
     */
    public function getInviteStats(int $inviterId)
    {
        $inviter = (new Runner())->where('id', $inviterId)->find();
        if (empty($inviter)) {
            return [
                'total_invited' => 0,
                'total_reward' => 0
            ];
        }

        $totalInvited = (new Runner())->where([
            ['site_id', '=', $this->site_id],
            ['inviter_id', '=', $inviterId]
        ])->count();

        // 计算已发放的邀请奖励（通过已标记发放的被邀请人数量 * 奖励金额）
        $config = $this->getInviteConfig();
        $grantedCount = (new Runner())->where([
            ['site_id', '=', $this->site_id],
            ['inviter_id', '=', $inviterId],
            ['invite_reward_granted', '=', 1]
        ])->count();

        return [
            'total_invited' => $totalInvited,
            'total_reward' => $grantedCount * $config['reward_amount']
        ];
    }

    /**
     * 邀请奖励流水（会员账单里 sd_xiaoyuan_invite_reward）
     */
    public function getRewardRecords(int $inviterId, int $page = 1, int $limit = 10)
    {
        $inviter = (new Runner())->where([
            ['id', '=', $inviterId],
            ['site_id', '=', $this->site_id],
        ])->find();
        if (empty($inviter)) {
            return ['count' => 0, 'list' => []];
        }
        $memberId = (int)($inviter['member_id'] ?? 0);
        if ($memberId <= 0) {
            return ['count' => 0, 'list' => []];
        }
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $memberId],
            ['from_type', '=', 'sd_xiaoyuan_invite_reward'],
        ];
        $model = new MemberAccountLog();
        $count = (int) $model->where($where)->count();
        $rows = $model->where($where)->order('id', 'desc')->page($page, $limit)->select()->toArray();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)($row['id'] ?? 0),
                'create_time' => (int)($row['create_time'] ?? 0),
                'reward_amount' => (float)($row['account_data'] ?? 0),
                'status' => 1,
            ];
        }
        return ['count' => $count, 'list' => $list];
    }
}
