<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\admin\ConfigService;
use addon\sd_xiaoyuan\app\service\core\RunnerService;
use core\base\BaseApiController;

class Common extends BaseApiController
{
    public function config()
    {
        $service = new ConfigService();
        $config = $service->getConfig($this->request->siteId());
        
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
            ['key' => 'PRINT', 'name' => '代打印', 'icon' => 'print', 'desc' => '帮您打印文件资料'],
            ['key' => 'SEAT', 'name' => '代占座', 'icon' => 'seat', 'desc' => '帮您占座位']
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
            
            // 今日任务数 (今天创建的订单)
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            $orderQuery = (new \addon\sd_xiaoyuan\app\model\order\Order())
                ->where([['site_id', '=', $site_id]])
                ->whereBetween('create_time', [$todayStart, $todayEnd]);
            if ($school_id > 0) {
                $orderQuery->where('school_id', $school_id);
            }
            $orderCount = $orderQuery->count();
            
            // 累计佣金 (所有接单员的总收入)
            $commissionQuery = (new \addon\sd_xiaoyuan\app\model\RunnerBalanceLog())
                ->where([['site_id', '=', $site_id], ['type', '=', 'INCOME']]);
            if ($school_id > 0) {
                // 需要关联订单表来过滤学校
                $commissionQuery->alias('rbl')
                    ->join('xiaoyuan_order o', 'o.id = rbl.order_id')
                    ->where('o.school_id', $school_id);
            }
            $totalCommission = $commissionQuery->sum('amount');
            
            return success([
                'goods_count' => $goodsCount ?: 0,
                'view_count' => $viewCount ?: 0,
                'order_count' => $orderCount ?: 0,
                'user_count' => $totalCommission ?: 0
            ]);
        } catch (\Exception $e) {
            return success([
                'goods_count' => 0,
                'view_count' => 0,
                'order_count' => 0,
                'user_count' => 0
            ]);
        }
    }
}
