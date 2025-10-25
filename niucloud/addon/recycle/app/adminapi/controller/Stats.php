<?php
// addon/recycle/app/adminapi/controller/Stats.php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller;

use core\base\BaseAdminController;
use addon\recycle\app\service\admin\stats\StatsService;
use addon\recycle\app\service\admin\stats\RecycleStatsService;
use think\Response;
use think\App;

class Stats extends BaseAdminController
{
    /**
     * @var RecycleStatsService
     */
    protected $recycleStatsService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->recycleStatsService = new RecycleStatsService();
    }

    /**
     * 获取质检员绩效统计（兼容旧接口）
     * @return Response
     */
    public function inspectorPerformance(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
            ['check_uid', 0],
        ]);
        
        // 确保时间格式
        if (!empty($params['start_time']) && strlen($params['start_time']) == 10) {
            $params['start_time'] .= ' 00:00:00';
        }
        if (!empty($params['end_time']) && strlen($params['end_time']) == 10) {
            $params['end_time'] .= ' 23:59:59';
        }

        // 使用旧的统计服务保持兼容性
        $stats_service = new StatsService();
        $data = $stats_service->getInspectorPerformanceStats($params);
        return success($data);
    }

    /**
     * 获取价格确认人绩效统计（兼容旧接口）
     * @return Response
     */
    public function priceConfirmerPerformance(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
            ['price_uid', 0],
        ]);
        
        // 确保时间格式
        if (!empty($params['start_time']) && strlen($params['start_time']) == 10) {
            $params['start_time'] .= ' 00:00:00';
        }
        if (!empty($params['end_time']) && strlen($params['end_time']) == 10) {
            $params['end_time'] .= ' 23:59:59';
        }

        // 使用旧的统计服务保持兼容性
        $stats_service = new StatsService();
        $data = $stats_service->getPriceConfirmerPerformanceStats($params);
        return success($data);
    }

    /**
     * 获取今日统计数据
     * @return Response
     */
    public function getTodayStats(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
            ['category_id', 0],
        ]);

        $data = $this->recycleStatsService->getTodayStats($params['user_id'], $params['category_id']);
        return success($data);
    }

    /**
     * 获取分类统计汇总
     * @return Response
     */
    public function getCategoryStats(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getCategoryStats($params);
        return success($data);
    }

    /**
     * 获取用户统计数据
     * @return Response
     */
    public function getUserStats(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getUserStats($params);
        return success($data);
    }

    /**
     * 获取排行榜数据（仅管理员）
     * @return Response
     */
    public function getRankingStats(): Response
    {
        $params = $this->request->params([
            ['rank_type', 'check'],
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
            ['limit', 10],
        ]);

        $data = $this->recycleStatsService->getRankingStats($params);
        return success($data);
    }

    /**
     * 获取质检员分类统计（兼容接口）
     * @return Response
     */
    public function getCheckerCategoryStats(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getCategoryStats($params);
        return success($data);
    }

    /**
     * 获取质检员今日工作量（兼容接口）
     * @return Response
     */
    public function getCheckerTodayWork(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
        ]);

        $userId = $params['user_id'] ?: $this->uid;
        
        // 获取今日各分类统计
        $categoryStats = [];
        for ($i = 1; $i <= 5; $i++) {
            $stats = $this->recycleStatsService->getTodayStats($userId, $i);
            $stats['category_id'] = $i;
            $stats['category_name'] = match($i) {
                1 => '手机',
                2 => '平板', 
                3 => '笔记本',
                4 => '手表',
                5 => '其他',
                default => '未知'
            };
            $categoryStats[] = $stats;
        }

        // 获取今日总计
        $totalStats = $this->recycleStatsService->getTodayStats($userId, 0);

        return success([
            'category_stats' => $categoryStats,
            'total_stats' => $totalStats
        ]);
    }

    /**
     * 获取统计概览（仪表板数据）
     * @return Response
     */
    public function getDashboardStats(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        // 获取当前用户的今日统计
        $todayStats = $this->recycleStatsService->getTodayStats();
        
        // 获取分类统计
        $categoryStats = $this->recycleStatsService->getCategoryStats($params);
        
        // 获取用户统计列表
        $userStats = $this->recycleStatsService->getUserStats($params);

        return success([
            'today_stats' => $todayStats,
            'category_stats' => $categoryStats,
            'user_stats' => $userStats
        ]);
    }

    /**
     * 获取签收统计数据
     * @return Response
     */
    public function getSignStats(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
            ['category_id', 0],
        ]);

        $data = $this->recycleStatsService->getSignStats($params);
        return success($data);
    }

    /**
     * 获取签收分类统计
     * @return Response
     */
    public function getSignCategoryStats(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getSignCategoryStats($params);
        return success($data);
    }

    /**
     * 获取普通用户签收统计
     * @return Response
     */
    public function getUserSignStats(): Response
    {
        $params = $this->request->params([
            ['user_id', 0],
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getUserSignStats($params);
        return success($data);
    }

    /**
     * 获取管理员概况统计
     * @return Response
     */
    public function getOverviewStats(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getOverviewStats($params);
        return success($data);
    }

    /**
     * 获取用户列表
     * @return Response
     */
    public function getUserList(): Response
    {
        $data = $this->recycleStatsService->getUserList();
        return success($data);
    }

    /**
     * 获取用户详细统计
     * @return Response
     */
    public function getUserDetailStats(): Response
    {
        $params = $this->request->params([
            ['user_id', ''],
            ['start_time',date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getUserDetailStats($params);
        return success($data);
    }

    /**
     * 获取会员统计概览
     * @return Response
     */
    public function getMemberStatsOverview(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getMemberStatsOverview($params);
        return success($data);
    }

    /**
     * 获取会员注册趋势
     * @return Response
     */
    public function getMemberRegisterTrend(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getMemberRegisterTrend($params);
        return success($data);
    }

    /**
     * 获取会员注册渠道分布
     * @return Response
     */
    public function getMemberChannelStats(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getMemberChannelStats($params);
        return success($data);
    }

    /**
     * 获取拉新排行榜
     * @return Response
     */
    public function getMemberInviteRank(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
            ['limit', 10],
        ]);

        $data = $this->recycleStatsService->getMemberInviteRank($params);
        return success($data);
    }

    /**
     * 获取会员活跃度统计
     * @return Response
     */
    public function getMemberActivityStats(): Response
    {
        $params = $this->request->params([
            ['start_time', date('Y-m-d')],
            ['end_time', date('Y-m-d')],
        ]);

        $data = $this->recycleStatsService->getMemberActivityStats($params);
        return success($data);
    }
} 