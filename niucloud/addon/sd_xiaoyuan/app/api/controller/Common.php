<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\service\core\ConfigService;
use addon\sd_xiaoyuan\app\service\core\RunnerService;
use core\base\BaseApiController;

class Common extends BaseApiController
{
    public function config()
    {
        $siteId = (int)$this->request->siteId();
        if ($siteId <= 0) {
            return fail('站点ID无效，请重新进入小程序');
        }
        $config = (new ConfigService())->getConfig($siteId);
        if (!is_array($config)) {
            return fail('站点配置数据异常，请到后台【校园帮-基础配置】点保存一次');
        }
        unset($config['withdraw']);
        return success($config);
    }

    public function nearbyRunners()
    {
        $lng = $this->request->param('lng', '');
        $lat = $this->request->param('lat', '');
        $limit = $this->request->param('limit', 10);
        
        $service = new RunnerService();
        $runners = $service->getNearbyRunners($lng, $lat, $limit);
        
        return success($runners);
    }

    public function taskTypes()
    {
        $types = [
            ['key' => 'EXPRESS', 'name' => '代取快递', 'icon' => 'express', 'desc' => '帮您取快递送到手'],
            ['key' => 'BUY', 'name' => '代买服务', 'icon' => 'buy', 'desc' => '帮您代买餐饮零食'],
            ['key' => 'ERRAND', 'name' => '跑腿服务', 'icon' => 'errand', 'desc' => '万能跑腿帮您办事'],
            ['key' => 'QUEUE', 'name' => '代排队', 'icon' => 'queue', 'desc' => '帮您排队省时间'],
            ['key' => 'CLASS', 'name' => '代上课', 'icon' => 'class', 'desc' => '帮您代上课签到笔记'],
            ['key' => 'PRINT', 'name' => '代打印', 'icon' => 'print', 'desc' => '帮您打印文件资料'],
            ['key' => 'SEAT', 'name' => '代占座', 'icon' => 'seat', 'desc' => '帮您占座位'],
            ['key' => 'PARTTIME', 'name' => '兼职招聘', 'icon' => 'parttime', 'desc' => '发布校园兼职与短工信息'],
            ['key' => 'COMPANION', 'name' => '约伴组局', 'icon' => 'companion', 'desc' => '发布约伴和活动信息']
        ];
        
        return success($types);
    }

    public function homeStats()
    {
        try {
            $site_id = $this->request->siteId();
            $school_id = $this->request->param('school_id', 0);
            
            // 商品数
            $goodsQuery = (new \addon\sd_xiaoyuan\app\model\Secondhand())->where([['site_id', '=', $site_id], ['status', '=', 1]]);
            if ($school_id > 0) {
                $goodsQuery->where('school_id', $school_id);
            }
            $goodsCount = $goodsQuery->count();
            
            // 浏览数 (虚拟 + 实际)
            $viewQuery = (new \addon\sd_xiaoyuan\app\model\Secondhand())->where([['site_id', '=', $site_id]]);
            if ($school_id > 0) {
                $viewQuery->where('school_id', $school_id);
            }
            $viewCount = $viewQuery->sum('view_count');
            
            // 今日任务数：订单表 create_time 为 Unix 时间戳，不能用日期字符串区间
            $todayStart = strtotime(date('Y-m-d'));
            $todayEnd = $todayStart + 86400;
            $orderQuery = (new Order())->where([
                ['site_id', '=', $site_id],
                ['create_time', '>=', $todayStart],
                ['create_time', '<', $todayEnd],
            ]);
            if ($school_id > 0) {
                $orderQuery->where('school_id', $school_id);
            }
            $orderCount = (int)$orderQuery->count();

            // 累计接单员收益：已完成订单 runner_income 汇总（与结算逻辑一致；RunnerBalanceLog 未必有记录）
            $incomeQuery = (new Order())->where([
                ['site_id', '=', $site_id],
                ['status', '=', 50],
            ]);
            if ($school_id > 0) {
                $incomeQuery->where('school_id', $school_id);
            }
            $totalRunnerIncome = round((float)$incomeQuery->sum('runner_income'), 2);

            return success([
                'goods_count' => $goodsCount ?: 0,
                'view_count' => $viewCount ?: 0,
                'order_count' => $orderCount,
                'total_runner_income' => $totalRunnerIncome,
                'user_count' => $totalRunnerIncome,
            ]);
        } catch (\Exception $e) {
            return success([
                'goods_count' => 0,
                'view_count' => 0,
                'order_count' => 0,
                'total_runner_income' => 0,
                'user_count' => 0,
            ]);
        }
    }
}
