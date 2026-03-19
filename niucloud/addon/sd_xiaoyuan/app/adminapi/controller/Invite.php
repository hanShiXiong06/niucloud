<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\MemberRelation;
use core\base\BaseAdminController;
use think\facade\Db;

/**
 * 邀请分销管理控制器
 */
class Invite extends BaseAdminController
{
    /**
     * 分销统计
     */
    public function stat()
    {
        // 总邀请人数
        $totalInvite = (new MemberRelation())->where([
            ['site_id', '=', $this->request->siteId()],
            ['pid', '>', 0]
        ])->count();

        // 今日新增邀请
        $todayStart = strtotime(date('Y-m-d'));
        $todayInvite = (new MemberRelation())->where([
            ['site_id', '=', $this->request->siteId()],
            ['create_time', '>=', $todayStart]
        ])->count();

        return success([
            'total_invite' => $totalInvite,
            'total_commission' => 0,
            'today_invite' => $todayInvite
        ]);
    }

    /**
     * 分销关系列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['member_id', 0],
            ['pid', 0]
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['member_id'] > 0) {
            $where[] = ['member_id', '=', $params['member_id']];
        }

        if ($params['pid'] > 0) {
            $where[] = ['pid', '=', $params['pid']];
        }

        $model = new MemberRelation();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'], $params['limit'])
            ->order('id desc')
            ->select()
            ->toArray();

        // 获取所有相关会员ID
        $memberIds = [];
        foreach ($list as $item) {
            $memberIds[] = $item['member_id'];
            if ($item['pid'] > 0) $memberIds[] = $item['pid'];
            if ($item['pid2'] > 0) $memberIds[] = $item['pid2'];
        }
        $memberIds = array_unique(array_filter($memberIds));

        // 查询会员信息
        $members = [];
        if (!empty($memberIds)) {
            $memberList = \app\model\member\Member::where([['member_id', 'in', $memberIds]])
                ->field('member_id,nickname,headimg')
                ->select()
                ->toArray();
            foreach ($memberList as $m) {
                $members[$m['member_id']] = $m;
            }
        }

        // 统计每个推荐人的邀请人数
        $inviteCounts = [];
        $pidList = array_unique(array_column($list, 'pid'));
        if (!empty($pidList)) {
            $countData = $model->where([
                ['site_id', '=', $this->request->siteId()],
                ['pid', 'in', $pidList]
            ])->group('pid')->column('count(*)', 'pid');
            $inviteCounts = $countData;
        }

        // 附加昵称和邀请人数
        foreach ($list as &$item) {
            $item['member_nickname'] = $members[$item['member_id']]['nickname'] ?? '';
            $item['member_headimg'] = $members[$item['member_id']]['headimg'] ?? '';
            $item['pid_nickname'] = $members[$item['pid']]['nickname'] ?? '';
            $item['pid2_nickname'] = $members[$item['pid2']]['nickname'] ?? '';
            $item['invite_count'] = $inviteCounts[$item['member_id']] ?? 0;
        }
        unset($item);

        return success(['count' => $count, 'list' => $list]);
    }

    /**
     * 分销配置
     */
    public function config()
    {
        if ($this->request->isPost()) {
            $config = $this->request->params([
                ['open_fenxiao', 0],
                ['fenxiao_rate1', 10],
                ['fenxiao_rate2', 5],
                ['invite_poster_bg', '']
            ]);

            $configService = new \addon\sd_xiaoyuan\app\service\admin\ConfigService();
            $allConfig = $configService->getConfig($this->request->siteId());
            $allConfig['open_fenxiao'] = intval($config['open_fenxiao']);
            $allConfig['fenxiao_rate1'] = intval($config['fenxiao_rate1']);
            $allConfig['fenxiao_rate2'] = intval($config['fenxiao_rate2']);
            $allConfig['invite_poster_bg'] = $config['invite_poster_bg'];
            $configService->setConfig($allConfig);

            return success('保存成功');
        }

        $configService = new \addon\sd_xiaoyuan\app\service\admin\ConfigService();
        $allConfig = $configService->getConfig($this->request->siteId());

        return success([
            'open_fenxiao' => $allConfig['open_fenxiao'] ?? 0,
            'fenxiao_rate1' => $allConfig['fenxiao_rate1'] ?? 10,
            'fenxiao_rate2' => $allConfig['fenxiao_rate2'] ?? 5,
            'invite_poster_bg' => $allConfig['invite_poster_bg'] ?? ''
        ]);
    }
}
