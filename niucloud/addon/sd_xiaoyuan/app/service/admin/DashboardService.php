<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\model\CampusAuth;
use core\base\BaseService;

class DashboardService extends BaseService
{
    public function getOverview($siteId)
    {
        $orderModel = new Order();
        $runnerModel = new Runner();
        $authModel = new CampusAuth();
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd = $todayStart + 86400;
        
        return [
            'order' => [
                'total' => $orderModel->where('site_id', $siteId)->count(),
                'today' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['create_time', '>=', $todayStart],
                    ['create_time', '<', $todayEnd]
                ])->count(),
                'pending' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 10]
                ])->count(),
                'processing' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['status', 'in', [20, 30, 40]]
                ])->count(),
                'completed' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 50]
                ])->count()
            ],
            'runner' => [
                'total' => $runnerModel->where('site_id', $siteId)->count(),
                'online' => $runnerModel->where([
                    ['site_id', '=', $siteId],
                    ['is_online', '=', 1],
                    ['status', '=', 1]
                ])->count(),
                'pending_audit' => $runnerModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 0]
                ])->count()
            ],
            'campus_auth' => [
                'total' => $authModel->where('site_id', $siteId)->count(),
                'pending' => $authModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 0]
                ])->count()
            ],
            'income' => [
                'total' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 50]
                ])->sum('platform_fee') ?: 0,
                'today' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 50],
                    ['complete_time', '>=', $todayStart],
                    ['complete_time', '<', $todayEnd]
                ])->sum('platform_fee') ?: 0
            ]
        ];
    }

    public function getStat($siteId, $params)
    {
        $type = $params['type'] ?? 'week';
        $orderModel = new Order();
        
        if ($type == 'week') {
            $days = 7;
        } elseif ($type == 'month') {
            $days = 30;
        } else {
            $days = 7;
        }
        
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $start = strtotime($date);
            $end = $start + 86400;
            
            $data[] = [
                'date' => $date,
                'order_count' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['create_time', '>=', $start],
                    ['create_time', '<', $end]
                ])->count(),
                'complete_count' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 50],
                    ['complete_time', '>=', $start],
                    ['complete_time', '<', $end]
                ])->count(),
                'income' => $orderModel->where([
                    ['site_id', '=', $siteId],
                    ['status', '=', 50],
                    ['complete_time', '>=', $start],
                    ['complete_time', '<', $end]
                ])->sum('platform_fee') ?: 0
            ];
        }
        
        return $data;
    }
}
