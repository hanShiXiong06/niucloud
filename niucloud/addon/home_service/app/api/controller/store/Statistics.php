<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\api\controller\store;

use addon\home_service\app\service\api\store\StatisticsService;
use core\base\BaseApiController;


/**
 * 统计
 * Class Reserve
 * @package app\adminapi\controller
 */
class Statistics extends BaseApiController
{

    /**
     * 获取今天的 统计数据
     * @return \think\Response
     */
    public function getTodayData()
    {
        return success((new statisticsService())->getTodayData());
    }

    /**
     * 订单看板
     * @return \think\Response
     */
    public function getOrderDashboard()
    {
        return success((new statisticsService())->getOrderDashboard());
    }

    /**
     * 师傅动态
     * @return \think\Response
     */
    public function getTechnicianDynamic()
    {
        return success((new statisticsService())->getTechnicianDynamic());
    }


    /**
     * 收支统计图
     * @return \think\Response
     */
    public function getIncomeAndExpenseStatChart()
    {
        $data = $this->request->params([
            ['date_type', 'day'],
        ]);
        return success('SUCCESS', (new statisticsService())->getIncomeAndExpenseStatChart($data));
    }

    /**
     * 收支统计
     * @return \think\Response
     */
    public function getIncomeAndExpenseStat()
    {
        $data = $this->request->params([
            ['date_type', 'day'],
        ]);
        return success('SUCCESS', (new statisticsService())->getIncomeAndExpenseStat($data));
    }




    /**
     * 日账单统计
     * @return \think\Response
     */
    public function getDayBillStat()
    {
        return success('SUCCESS', (new statisticsService())->getDayBillStat());
    }

    /**
     * 日订单统计
     * @return \think\Response
     */
    public function getDayOrderStat()
    {
        return success('SUCCESS', (new statisticsService())->getDayOrderStat());
    }

    /**
     * 月订单统计
     * @return \think\Response
     */
    public function getMonthOrderStat()
    {
        $data = $this->request->params([
            ['date', ''],
        ]);
        return success('SUCCESS', (new statisticsService())->getMonthOrderStat($data));
    }

    /**
     * 获取订单明细列表
     * @return \think\Response
     */
    public function getOrderPage()
    {
        $data = $this->request->params([
            ['date_type', 'day'],
            ['date', ''],
            ['status', 'finish'],
        ]);
        return success('SUCCESS', (new statisticsService())->getOrderPage($data));
    }
}
