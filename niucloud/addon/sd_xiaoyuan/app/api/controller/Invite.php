<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\FenxiaoService;
use core\base\BaseApiController;

/**
 * 邀请有礼控制器
 */
class Invite extends BaseApiController
{
    /**
     * 绑定邀请关系
     */
    public function bindRelation()
    {
        $pid = $this->request->param('pid', 0);
        
        if (empty($pid)) {
            return success('无需绑定');
        }

        $member_id = $this->request->memberId();
        if (empty($member_id)) {
            return success('未登录');
        }

        $service = new FenxiaoService();
        $result = $service->setRelation($member_id, $pid);
        
        if ($result) {
            return success('绑定成功');
        }
        return success('绑定失败');
    }

    /**
     * 获取我的邀请统计
     */
    public function stat()
    {
        $service = new FenxiaoService();
        $data = $service->getInviteStat();
        return success($data);
    }

    
    /**
     * 获取我的推荐关系
     */
    public function relation()
    {
        $service = new FenxiaoService();
        $data = $service->getRelation();
        return success($data);
    }

    /**
     * 生成邀请海报
     */
    public function poster()
    {
        $service = new FenxiaoService();
        $data = $service->getPosterData();
        return success($data);
    }

    /**
     * 获取我的团队列表
     */
    public function team()
    {
        $level = $this->request->param('level', 1);
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 20);
        
        $service = new FenxiaoService();
        $data = $service->getTeamList((int)$level, (int)$page, (int)$limit);
        return success($data);
    }

    /**
     * 获取团队统计
     */
    public function teamStat()
    {
        $service = new FenxiaoService();
        $data = $service->getTeamStat();
        return success($data);
    }
}
