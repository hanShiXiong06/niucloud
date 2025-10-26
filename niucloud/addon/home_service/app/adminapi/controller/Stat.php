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

namespace addon\home_service\app\adminapi\controller;

use addon\home_service\app\service\admin\StatService;
use Carbon\Carbon;
use core\base\BaseAdminController;

/**
 * 统计
 * Class Stat
 * @description 统计
 * @package app\adminapi\controller\o2o_goods_category
 */
class Stat extends BaseAdminController
{
    /**
     * 总计
     * @description 总计
     * @return \think\Response
     */
    public function total()
    {
        return success(data: (new StatService())->getStat());
    }


    /**
     * 今日
     * @description 今日
     * @return void
     */
    public function today()
    {
        return success(data: (new StatService())->getStat(date('Y-m-d', time())));
    }

    /**
     * 昨日
     * @description 昨日
     * @return void
     */
    public function yesterday()
    {
        $yesterday = Carbon::yesterday();
        return success(data: (new StatService())->getStat(date('Y-m-d', $yesterday->getTimestamp())));
    }

    /**
     * 总计
     * @description 总计
     * @return \think\Response
     */
    public function month()
    {
        return success(data: (new StatService())->getMonthStat(date('Y-m', time()), date('Y-m-d', time())));
    }

}
