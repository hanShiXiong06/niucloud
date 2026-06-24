<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Sign;
use addon\sd_xiaoyuan\app\service\core\SignConfigCoreService;
use app\dict\member\MemberAccountChangeTypeDict;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 签到服务
 */
class SignService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 签到
     */
    public function sign()
    {
        $today = date('Y-m-d');
        
        // 检查今天是否已签到
        $todaySign = (new Sign())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['sign_date', '=', $today]
        ])->find();

        if (!empty($todaySign)) {
            throw new CommonException('今天已经签到过了');
        }

        // 获取昨天的签到记录，计算连续签到天数
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterdaySign = (new Sign())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['sign_date', '=', $yesterday]
        ])->find();

        $continuousDays = 1;
        if (!empty($yesterdaySign)) {
            $yd = is_array($yesterdaySign) ? ($yesterdaySign['continuous_days'] ?? 0) : (int)($yesterdaySign->continuous_days ?? 0);
            $continuousDays = $yd + 1;
            // 最多7天循环
            if ($continuousDays > 7) {
                $continuousDays = 1;
            }
        }

        // 获取签到奖励配置
        $reward = $this->getSignReward($continuousDays);

        // 创建签到记录
        $signData = [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'sign_date' => $today,
            'continuous_days' => $continuousDays,
            'reward_points' => $reward['points'] ?? 0,
            'sign_time' => time(),
            'create_time' => time()
        ];

        (new Sign())->save($signData);

        // 发放奖励
        if (!empty($reward['points'])) {
            $this->sendPointsReward($reward['points']);
        }

        return [
            'continuous_days' => $continuousDays,
            'reward_points' => $reward['points'] ?? 0,
            'reward_coupon_id' => $reward['coupon_id'] ?? 0
        ];
    }

    /**
     * 获取签到状态
     */
    public function getSignStatus()
    {
        $today = date('Y-m-d');
        
        // 今天是否签到
        $todaySign = (new Sign())->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id],
            ['sign_date', '=', $today]
        ])->find();

        $isSigned = !empty($todaySign);
        $continuousDays = is_array($todaySign) ? ($todaySign['continuous_days'] ?? 0) : (int)($todaySign->continuous_days ?? 0);

        if (!$isSigned) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $yesterdaySign = (new Sign())->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id],
                ['sign_date', '=', $yesterday]
            ])->find();

            if (!empty($yesterdaySign)) {
                $continuousDays = is_array($yesterdaySign) ? ($yesterdaySign['continuous_days'] ?? 0) : (int)($yesterdaySign->continuous_days ?? 0);
            } else {
                $continuousDays = 0;
            }
        }

        $nextSignDay = $isSigned ? $continuousDays : ($continuousDays >= 7 ? 1 : $continuousDays + 1);
        if ($nextSignDay < 1) {
            $nextSignDay = 1;
        }

        // 获取7天奖励配置
        $rewards = $this->getWeekRewards();

        // 获取用户总积分
        $member = \app\model\member\Member::where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->field('point')->find();
        $totalPoints = 0;
        if ($member) {
            $totalPoints = (int)(is_array($member) ? ($member['point'] ?? 0) : ($member->point ?? 0));
        }

        return [
            'is_signed' => $isSigned,
            'continuous_days' => $continuousDays,
            'next_sign_day' => $nextSignDay,
            'rewards' => $rewards,
            'total_points' => $totalPoints
        ];
    }

    /**
     * 获取签到奖励
     */
    private function getSignReward($day)
    {
        $core = new SignConfigCoreService();
        return $core->getDayReward((int)$this->site_id, (int)$day);
    }

    /**
     * 获取一周奖励配置
     */
    private function getWeekRewards()
    {
        $core = new SignConfigCoreService();
        return $core->getRewardsList((int)$this->site_id);
    }

    /**
     * 发放积分奖励
     */
    private function sendPointsReward($points)
    {
        $pointTypes = MemberAccountChangeTypeDict::getType('point');
        if (!is_array($pointTypes) || empty($pointTypes['sd_xiaoyuan_sign'])) {
            throw new CommonException('签到积分类型未注册，请更新校园帮插件后重试');
        }
        $accountService = new \app\service\core\member\CoreMemberAccountService();
        $accountService->addLog(
                $this->site_id,
                $this->member_id,
                'point',
                $points,
                'sd_xiaoyuan_sign',
                '签到奖励',
                0
            );
    }

    /**
     * 获取签到历史
     */
    public function getSignHistory($page = 1, $limit = 20)
    {
        $model = new Sign();
        
        $count = $model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->count();

        $list = $model->where([
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->page($page, $limit)->order('sign_date desc')->select()->toArray();

        return [
            'count' => $count,
            'list' => $list
        ];
    }
}
