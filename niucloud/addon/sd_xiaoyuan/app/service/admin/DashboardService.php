<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\runner\Runner;
use addon\sd_xiaoyuan\app\model\CampusAuth;
use core\base\BaseService;

class DashboardService extends BaseService
{
    protected function orderSchoolWhere(int $siteId, int $schoolId): array
    {
        $w = [['site_id', '=', $siteId]];
        if ($schoolId > 0) {
            $w[] = ['school_id', '=', $schoolId];
        }
        return $w;
    }

    public function getOverview($siteId, int $schoolId = 0)
    {
        $orderModel = new Order();
        $runnerModel = new Runner();
        $authModel = new CampusAuth();
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd = $todayStart + 86400;
        $ow = $this->orderSchoolWhere($siteId, $schoolId);
        $rw = [['site_id', '=', $siteId]];
        $aw = [['site_id', '=', $siteId]];
        if ($schoolId > 0) {
            $rw[] = ['school_id', '=', $schoolId];
            $aw[] = ['school_id', '=', $schoolId];
        }

        return [
            'order' => [
                'total' => $orderModel->where($ow)->count(),
                'today' => $orderModel->where(array_merge($ow, [
                    ['create_time', '>=', $todayStart],
                    ['create_time', '<', $todayEnd],
                ]))->count(),
                'pending' => $orderModel->where(array_merge($ow, [
                    ['status', '=', 10],
                ]))->count(),
                'processing' => $orderModel->where(array_merge($ow, [
                    ['status', 'in', [20, 30, 40]],
                ]))->count(),
                'completed' => $orderModel->where(array_merge($ow, [
                    ['status', '=', 50],
                ]))->count()
            ],
            'runner' => [
                'total' => $runnerModel->where($rw)->count(),
                'online' => $runnerModel->where(array_merge($rw, [
                    ['is_online', '=', 1],
                    ['status', '=', 1],
                ]))->count(),
                'pending_audit' => $runnerModel->where(array_merge($rw, [
                    ['status', '=', 0],
                ]))->count()
            ],
            'campus_auth' => [
                'total' => $authModel->where($aw)->count(),
                'pending' => $authModel->where(array_merge($aw, [
                    ['status', '=', 0],
                ]))->count()
            ],
            'income' => [
                'total' => $orderModel->where(array_merge($ow, [
                    ['status', '=', 50],
                ]))->sum('platform_fee') ?: 0,
                'today' => $orderModel->where(array_merge($ow, [
                    ['status', '=', 50],
                    ['complete_time', '>=', $todayStart],
                    ['complete_time', '<', $todayEnd],
                ]))->sum('platform_fee') ?: 0
            ]
        ];
    }

    public function getStat($siteId, $params)
    {
        $type = $params['type'] ?? 'week';
        $schoolId = (int)($params['school_id'] ?? 0);
        $orderModel = new Order();
        
        if ($type == 'week') {
            $days = 7;
        } elseif ($type == 'month') {
            $days = 30;
        } else {
            $days = 7;
        }
        
        $data = [];
        $owBase = $this->orderSchoolWhere($siteId, $schoolId);
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $start = strtotime($date);
            $end = $start + 86400;
            
            $data[] = [
                'date' => $date,
                'order_count' => $orderModel->where(array_merge($owBase, [
                    ['create_time', '>=', $start],
                    ['create_time', '<', $end],
                ]))->count(),
                'complete_count' => $orderModel->where(array_merge($owBase, [
                    ['status', '=', 50],
                    ['complete_time', '>=', $start],
                    ['complete_time', '<', $end],
                ]))->count(),
                'income' => $orderModel->where(array_merge($owBase, [
                    ['status', '=', 50],
                    ['complete_time', '>=', $start],
                    ['complete_time', '<', $end],
                ]))->sum('platform_fee') ?: 0
            ];
        }
        
        return $data;
    }
}
