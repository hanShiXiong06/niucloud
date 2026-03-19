<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\Community;
use addon\sd_xiaoyuan\app\model\Confession;
use addon\sd_xiaoyuan\app\model\Sign;
use addon\sd_xiaoyuan\app\model\MemberRelation;
use addon\sd_xiaoyuan\app\model\House;
use core\base\BaseAdminController;

/**
 * 数据统计控制器
 */
class Stat extends BaseAdminController
{
    /**
     * 综合统计
     */
    public function overview()
    {
        $today = date('Y-m-d');
        $todayStart = strtotime($today);

        // 树洞统计
        $communityTotal = (new Community())->where('site_id', $this->request->siteId())->count();
        $communityToday = (new Community())->where([
            ['site_id', '=', $this->request->siteId()],
            ['create_time', '>=', $todayStart]
        ])->count();

        // 表白墙统计
        $confessionTotal = (new Confession())->where('site_id', $this->request->siteId())->count();
        $confessionToday = (new Confession())->where([
            ['site_id', '=', $this->request->siteId()],
            ['create_time', '>=', $todayStart]
        ])->count();

        // 签到统计
        $signTotal = (new Sign())->where('site_id', $this->request->siteId())->count();
        $signToday = (new Sign())->where([
            ['site_id', '=', $this->request->siteId()],
            ['sign_date', '=', $today]
        ])->count();

        // 邀请统计
        $inviteTotal = (new MemberRelation())->where([
            ['site_id', '=', $this->request->siteId()],
            ['pid', '>', 0]
        ])->count();

        // 房源统计
        $houseTotal = (new House())->where('site_id', $this->request->siteId())->count();
        $housePublished = (new House())->where([
            ['site_id', '=', $this->request->siteId()],
            ['status', '=', 1]
        ])->count();

        return success([
            'community' => [
                'total' => $communityTotal,
                'today' => $communityToday
            ],
            'confession' => [
                'total' => $confessionTotal,
                'today' => $confessionToday
            ],
            'sign' => [
                'total' => $signTotal,
                'today' => $signToday
            ],
            'invite' => [
                'total' => $inviteTotal
            ],
            'house' => [
                'total' => $houseTotal,
                'published' => $housePublished
            ]
        ]);
    }

    /**
     * 趋势统计
     */
    public function trend()
    {
        $days = $this->request->param('days', 7);
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dayStart = strtotime($date);
            $dayEnd = $dayStart + 86400;

            $data[] = [
                'date' => $date,
                'community' => (new Community())->where([
                    ['site_id', '=', $this->request->siteId()],
                    ['create_time', '>=', $dayStart],
                    ['create_time', '<', $dayEnd]
                ])->count(),
                'confession' => (new Confession())->where([
                    ['site_id', '=', $this->request->siteId()],
                    ['create_time', '>=', $dayStart],
                    ['create_time', '<', $dayEnd]
                ])->count(),
                'sign' => (new Sign())->where([
                    ['site_id', '=', $this->request->siteId()],
                    ['sign_date', '=', $date]
                ])->count()
            ];
        }

        return success($data);
    }
}
