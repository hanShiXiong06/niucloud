<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\Community;
use addon\sd_xiaoyuan\app\model\Confession;
use addon\sd_xiaoyuan\app\model\Sign;
use addon\sd_xiaoyuan\app\model\MemberRelation;
use addon\sd_xiaoyuan\app\model\House;
use addon\sd_xiaoyuan\app\service\admin\DashboardService;
use core\base\BaseAdminController;

class Dashboard extends BaseAdminController
{
    public function index()
    {
        $service = new DashboardService();
        $schoolId = (int)$this->request->param('school_id', 0);
        $data = $service->getOverview($this->request->siteId(), $schoolId);
        
        // 添加快捷入口
        $quickLinks = [
            [
                'title' => '订单管理',
                'icon' => 'iconfont iconshangpinguanli1',
                'items' => [
                    [
                        'title' => '订单列表',
                        'url' => '/admin/sd_xiaoyuan/order/list',
                        'icon' => 'iconfont iconliebiao',
                        'count' => $this->getOrderCount()
                    ],
                    [
                        'title' => '任务悬赏',
                        'url' => '/admin/sd_xiaoyuan/task/list',
                        'icon' => 'iconfont iconjihuarenwu',
                        'count' => $this->getTaskCount()
                    ],
                    [
                        'title' => '拼单好饭',
                        'url' => '/admin/sd_xiaoyuan/group_order/list',
                        'icon' => 'iconfont iconhuiyuanliebiao',
                        'count' => $this->getGroupOrderCount()
                    ],
                    [
                        'title' => '二手交易',
                        'url' => '/admin/sd_xiaoyuan/secondhand/list',
                        'icon' => 'iconfont iconshangpinliebiao',
                        'count' => $this->getSecondhandCount()
                    ],
                    [
                        'title' => '失物招领',
                        'url' => '/admin/sd_xiaoyuan/lost_found/list',
                        'icon' => 'iconfont iconsousuo',
                        'count' => $this->getLostFoundCount()
                    ],
                    [
                        'title' => '接单员管理',
                        'url' => '/admin/sd_xiaoyuan/runner/list',
                        'icon' => 'iconfont iconyonghu',
                        'count' => $this->getRunnerCount()
                    ]
                ]
            ],
            [
                'title' => '内容管理',
                'icon' => 'iconfont iconneirong2',
                'items' => [
                    [
                        'title' => '树洞管理',
                        'url' => '/admin/sd_xiaoyuan/community/list',
                        'icon' => 'iconfont iconzhongcaoshequ',
                        'count' => $this->getCommunityCount()
                    ],
                    [
                        'title' => '表白墙管理',
                        'url' => '/admin/sd_xiaoyuan/confession/list',
                        'icon' => 'iconfont iconai',
                        'count' => $this->getConfessionCount()
                    ],
                    [
                        'title' => '房屋租赁',
                        'url' => '/admin/sd_xiaoyuan/house/list',
                        'icon' => 'iconfont iconhome',
                        'count' => $this->getHouseCount()
                    ]
                ]
            ]
        ];
        
        $data['quick_links'] = $quickLinks;
        return success($data);
    }

    public function stat()
    {
        $params = $this->request->params([
            ['type', 'week'],
            ['start_time', ''],
            ['end_time', ''],
            ['school_id', 0],
        ]);
        
        $service = new DashboardService();
        $data = $service->getStat($this->request->siteId(), $params);
        return success($data);
    }

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

    /**
     * 获取订单数量
     */
    private function getOrderCount()
    {
        return (new \addon\sd_xiaoyuan\app\model\order\Order())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取任务数量
     */
    private function getTaskCount()
    {
        return (new \addon\sd_xiaoyuan\app\model\Task())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取拼单数量
     */
    private function getGroupOrderCount()
    {
        return (new \addon\sd_xiaoyuan\app\model\GroupOrder())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取二手商品数量
     */
    private function getSecondhandCount()
    {
        return (new \addon\sd_xiaoyuan\app\model\Secondhand())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取失物招领数量
     */
    private function getLostFoundCount()
    {
        return (new \addon\sd_xiaoyuan\app\model\LostFound())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取接单员数量
     */
    private function getRunnerCount()
    {
        return (new \addon\sd_xiaoyuan\app\model\runner\Runner())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取树洞数量
     */
    private function getCommunityCount()
    {
        return (new Community())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取表白墙数量
     */
    private function getConfessionCount()
    {
        return (new Confession())->where('site_id', $this->request->siteId())->count();
    }

    /**
     * 获取房屋数量
     */
    private function getHouseCount()
    {
        return (new House())->where('site_id', $this->request->siteId())->count();
    }
}
