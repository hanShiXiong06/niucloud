<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\CreditService;
use addon\sd_xiaoyuan\app\model\CreditLog;
use core\base\BaseAdminController;
use think\Response;

/**
 * 信誉分管理控制器
 */
class Credit extends BaseAdminController
{
    /**
     * 获取信誉分列表
     */
    public function lists(): Response
    {
        $data = $this->request->params([
            ['member_id', ''],
            ['is_restricted', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new CreditService())->getPage($data);
        return success($list);
    }

    /**
     * 获取用户信誉分详情
     */
    public function info(): Response
    {
        $member_id = $this->request->param('member_id', 0);
        if (empty($member_id)) {
            return fail('参数错误');
        }
        
        $info = (new CreditService())->getInfo((int)$member_id);
        return success($info);
    }

    /**
     * 获取信誉分变动记录
     */
    public function logList(): Response
    {
        $data = $this->request->params([
            ['member_id', 0],
            ['type', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $member_id = (int)$data['member_id'];
        if (empty($member_id)) {
            return fail('参数错误');
        }
        
        $list = (new CreditService())->getLogPage($member_id, $data);
        return success($list);
    }

    /**
     * 管理员调整信誉分
     */
    public function adjust(): Response
    {
        $member_id = $this->request->param('member_id', 0);
        $score = $this->request->param('score', 0);
        $remark = $this->request->param('remark', '');
        
        if (empty($member_id)) {
            return fail('参数错误');
        }
        
        if ($score == 0) {
            return fail('调整分数不能为0');
        }
        
        $new_score = (new CreditService())->adminAdjust((int)$member_id, (int)$score, $remark);
        return success(['new_score' => $new_score]);
    }

    /**
     * 获取信誉分统计
     */
    public function stat(): Response
    {
        $stat = (new CreditService())->getStat();
        return success($stat);
    }

    /**
     * 获取变动类型列表
     */
    public function typeList(): Response
    {
        $list = CreditLog::getTypeList();
        return success($list);
    }
}
