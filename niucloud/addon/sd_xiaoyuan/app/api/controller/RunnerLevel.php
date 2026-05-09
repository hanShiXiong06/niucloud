<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\RunnerLevelService;
use addon\sd_xiaoyuan\app\service\core\RunnerLocationService;
use addon\sd_xiaoyuan\app\service\core\RunnerInviteService;
use core\base\BaseApiController;

/**
 * 接单员等级接口控制器
 */
class RunnerLevel extends BaseApiController
{
    /**
     * 获取等级配置列表
     */
    public function levels()
    {
        $service = new RunnerLevelService();
        $list = $service->getLevelList();
        return success($list);
    }

    /**
     * 获取接单员等级信息
     */
    public function info()
    {
        $runnerId = $this->request->param('runner_id', 0);
        if ($runnerId <= 0) {
            return $this->error('参数错误');
        }

        $service = new RunnerLevelService();
        $data = $service->getRunnerLevelInfo($runnerId);
        return success($data);
    }

    /**
     * 更新接单员位置
     */
    public function updateLocation()
    {
        $data = $this->request->params([
            ['runner_id', 0],
            ['latitude', 0],
            ['longitude', 0],
            ['address', '']
        ]);

        if ($data['runner_id'] <= 0 || $data['latitude'] == 0 || $data['longitude'] == 0) {
            return $this->error('参数错误');
        }

        $service = new RunnerLocationService();
        $service->updateLocation($data['runner_id'], $data['latitude'], $data['longitude'], $data['address']);
        return success();
    }

    /**
     * 获取接单员位置
     */
    public function getLocation()
    {
        $runnerId = $this->request->param('runner_id', 0);
        if ($runnerId <= 0) {
            return $this->error('参数错误');
        }

        $service = new RunnerLocationService();
        $data = $service->getLocation($runnerId);
        return success($data);
    }

    /**
     * 获取附近接单员
     */
    public function nearby()
    {
        $latitude = $this->request->param('latitude', 0);
        $longitude = $this->request->param('longitude', 0);
        $radius = $this->request->param('radius', 5);

        if ($latitude == 0 || $longitude == 0) {
            return $this->error('请提供位置信息');
        }

        $service = new RunnerLocationService();
        $data = $service->getNearbyRunners($latitude, $longitude, $radius);
        return success($data);
    }

    /**
     * 绑定邀请人
     */
    public function bindInviter()
    {
        $inviteeId = $this->request->param('invitee_id', 0);
        $inviterId = $this->request->param('inviter_id', 0);

        if ($inviteeId <= 0 || $inviterId <= 0) {
            return $this->error('参数错误');
        }

        $service = new RunnerInviteService();
        $service->bindInviter($inviteeId, $inviterId);
        return success();
    }

    /**
     * 获取邀请的接单员列表
     */
    public function invitedRunners()
    {
        $inviterId = $this->request->param('inviter_id', 0);
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);

        if ($inviterId <= 0) {
            return $this->error('参数错误');
        }

        $service = new RunnerInviteService();
        $data = $service->getInvitedRunners($inviterId, $page, $limit);
        return success($data);
    }

    /**
     * 获取邀请奖励记录
     */
    public function inviteRewards()
    {
        $inviterId = $this->request->param('inviter_id', 0);
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);

        if ($inviterId <= 0) {
            return $this->error('参数错误');
        }

        $service = new RunnerInviteService();
        $data = $service->getRewardRecords($inviterId, $page, $limit);
        return success($data);
    }

    /**
     * 获取邀请统计
     */
    public function inviteStats()
    {
        $inviterId = $this->request->param('inviter_id', 0);
        if ($inviterId <= 0) {
            return $this->error('参数错误');
        }

        $service = new RunnerInviteService();
        $data = $service->getInviteStats($inviterId);
        return success($data);
    }
}
