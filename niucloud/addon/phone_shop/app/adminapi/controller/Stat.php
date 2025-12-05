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

namespace addon\phone_shop\app\adminapi\controller;

use addon\phone_shop\app\service\admin\goods\GoodsService;
use addon\phone_shop\app\service\admin\StatService;
use addon\phone_shop\app\service\admin\order\OrderService;
use Carbon\Carbon;
use core\base\BaseAdminController;

class Stat extends BaseAdminController
{
    /**
     * 总计
     * @return \think\Response
     */
    public function total() {
        return success(data: (new StatService())->getStat());
    }

    /**
     * 今日
     * @return void
     */
    public function today() {
        return success(data: (new StatService())->getDayOverview(date('Y-m-d')));
    }

    /**
     * 昨日
     * @return void
     */
    public function yesterday() {
        $yesterday =  Carbon::yesterday();
        return success(data: (new StatService())->getDayOverview($yesterday->toDateString()));
    }

    /**
     * 获取阶段统计数据
     * @return \think\Response
     */
    public function stat() {
        $data = $this->request->params([
            ['time_range', 'today'],  // 时间范围：today, yesterday, week, month
        ]);
        return success(data: (new StatService())->getGoodsCategoryTrend($data['time_range']));
    }

    /**
     * 获取统计数据按时段
     * @return \think\Response
     */
    public function hourStat() {
        $data = $this->request->params([
            ['date', date('Y-m-d', time()) ],
        ]);
        return success(data: (new StatService())->getHourStatData($data['date']));
    }

    /**
     * 订单统计
     * @return \think\Response
     */
    public function order() {
        return success(data: (new OrderService())->getOrderCount());
    }

    public function goods()
    {
        return success(data: (new GoodsService())->getGoodsCount());
    }
}
