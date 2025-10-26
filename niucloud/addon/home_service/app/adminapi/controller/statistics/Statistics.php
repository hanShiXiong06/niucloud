<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\adminapi\controller\statistics;


use addon\home_service\app\service\admin\statistics\EvaluateService;
use addon\home_service\app\service\admin\statistics\StatisticsService;
use core\base\BaseAdminController;


/**
 * 统计控制器
 * Class Evaluate
 * @package addon\home_service\app\adminapi\controller\statistics
 */
class Statistics extends BaseAdminController
{
    /**
     * 首页-基础数据
     * Class getBasicData
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getBasicData()
    {
        return success((new StatisticsService())->getBasicData());
    }

    /**
     * 首页-工单数据
     * Class getBasicData
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getWorkOrderData()
    {
        return success((new StatisticsService())->getWorkOrderData());
    }

    /**
     * 首页-待办总览
     * Class getTodoData
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getTodoData()
    {
        return success((new StatisticsService())->getTodoData());
    }

    /**
     * 首页-交易趋势
     * Class getTradingTrend
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getTradingTrend()
    {
        $data = $this->request->params([
            ["date_type", "week"],
        ]);
        return success((new StatisticsService())->getTradingTrend($data));
    }

    /**
     * 首页-服务类型占比
     * Class getTradingTrend
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getCategoryRate()
    {
        return success((new StatisticsService())->getCategoryRate());
    }

    /**
     * 首页-热门服务排行
     * Class getPopularServiceRank
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getPopularServiceRank()
    {
        $data = $this->request->params([
            ["stat_type", "order_count"],  // order_count   positive_rate
        ]);
        return success((new StatisticsService())->getPopularServiceRank($data));
    }

    /**
     * 首页-师傅排行榜
     * Class getTechnicianRank
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getTechnicianRank()
    {
        $data = $this->request->params([
            ["stat_type", "order_count"],  // order_count   evaluate_avg_scores
        ]);
        return success((new StatisticsService())->getTechnicianRank($data));
    }







    /**
     * 师傅报表
     * Class getTechnicianStatistics
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getTechnicianStatistics()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
            ["stat_type", "service_order_count"],  // service_order_count   total_order_money
        ]);
        return success((new StatisticsService())->getTechnicianStatistics($data));
    }

    /**
     * 机构报表
     * Class getStoreStatistics
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getStoreStatistics()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
            ["stat_type", "service_order_count"],  // service_order_count   total_order_money
        ]);
        return success((new StatisticsService())->getStoreStatistics($data));
    }


    /**
     * 门店评价
     * Class Evaluate
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getStoreEvalStats()
    {
        $data = $this->request->params([
            ["store_id", ""],
        ]);
        return success((new EvaluateService())->getStoreEvalStats($data));
    }

    /**
     * 财务报表-收入趋势
     * Class getFinanceStats
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getFinanceIncomeTrendStats()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
        ]);
        return success((new StatisticsService())->getFinanceIncomeTrendStats($data));
    }

    /**
     * 财务报表-门店收入趋势
     * Class getFinanceStats
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getFinanceStoreIncomeTrendStats()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
        ]);
        return success((new StatisticsService())->getFinanceStoreIncomeTrendStats($data));
    }

    /**
     * 财务报表-师傅收入趋势
     * Class getFinanceStats
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getFinanceTechnicianIncomeTrendStats()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
        ]);
        return success((new StatisticsService())->getFinanceTechnicianIncomeTrendStats($data));
    }

    /**
     * 财务报表-售后支出趋势
     * Class getFinanceStats
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getRefundExpenditureStats()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
        ]);
        return success((new StatisticsService())->getRefundExpenditureStats($data));
    }

    /**
     * 财务报表-收支盈利分析
     * Class getFinanceStats
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getReceiptExpenditureAnalyze()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
        ]);
        return success((new StatisticsService())->getReceiptExpenditureAnalyze($data));
    }

    /**
     * 客户报表
     * Class getFinanceStats
     * @package addon\home_service\app\adminapi\controller\statistics
     */
    public function getMemberStats()
    {
        $data = $this->request->params([
            ["date_type", "week"],
            ["date", []],
        ]);
        return success((new StatisticsService())->getMemberStats($data));
    }

}
