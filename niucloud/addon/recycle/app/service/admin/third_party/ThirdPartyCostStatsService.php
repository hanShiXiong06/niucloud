<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\third_party;

use addon\recycle\app\model\third_party\ThirdPartyCostStats;
use addon\recycle\app\model\third_party\ThirdPartyApiLog;
use core\base\BaseAdminService;

/**
 * 第三方服务费用统计服务类
 * Class ThirdPartyCostStatsService
 * @package addon\recycle\app\service\admin\third_party
 */
class ThirdPartyCostStatsService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ThirdPartyCostStats();
    }

    /**
     * 获取费用统计分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,service_type,provider_name,date,total_calls,success_calls,failed_calls,total_cost,avg_duration';
        $order = 'date desc';

        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['service_type', 'provider_name'], $where)
            ->field($field)
            ->order($order);

        // 手动处理日期范围过滤
        if (!empty($where['start_date'])) {
            $search_model->where('date', '>=', $where['start_date']);
        }
        if (!empty($where['end_date'])) {
            $search_model->where('date', '<=', $where['end_date']);
        }

        $result = $this->pageQuery($search_model);

        // 添加汇总统计
        $summary = $this->getSummary($where);
        $result['summary'] = $summary;

        return $result;
    }

    /**
     * 获取汇总统计（从API日志表实时计算）
     * @param array $where
     * @return array
     */
    private function getSummary(array $where = [])
    {
        // 从API日志表实时统计
        $query = ThirdPartyApiLog::where([['site_id', '=', $this->site_id]]);

        if (!empty($where['service_type'])) {
            $query->where('service_type', '=', $where['service_type']);
        }
        if (!empty($where['provider_name'])) {
            $query->where('provider_name', '=', $where['provider_name']);
        }
        if (!empty($where['start_date'])) {
            $query->where('create_at', '>=', strtotime($where['start_date']));
        }
        if (!empty($where['end_date'])) {
            $query->where('create_at', '<=', strtotime($where['end_date'] . ' 23:59:59'));
        }

        $total_calls = $query->count();
        $success_calls = (clone $query)->where('status', '=', 1)->count();
        $failed_calls = $total_calls - $success_calls;
        $total_cost = (clone $query)->sum('cost');
        $avg_duration = (clone $query)->avg('duration') ?: 0;

        return [
            'total_calls' => $total_calls,
            'success_calls' => $success_calls,
            'failed_calls' => $failed_calls,
            'total_cost' => round($total_cost, 2),
            'avg_duration' => round($avg_duration)
        ];
    }

    /**
     * 从API日志回填统计数据
     * @return array
     */
    public function rebuildStats()
    {
        try {
            // 获取所有API日志，按日期、服务类型、服务商分组统计
            $logs = ThirdPartyApiLog::where('site_id', $this->site_id)
                ->field('
                    DATE(FROM_UNIXTIME(create_at)) as date,
                    service_type,
                    provider_name,
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as success_calls,
                    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as failed_calls,
                    SUM(cost) as total_cost,
                    AVG(duration) as avg_duration
                ')
                ->group('date, service_type, provider_name')
                ->select()
                ->toArray();

            $count = 0;
            foreach ($logs as $log) {
                // 检查是否已存在
                $exists = ThirdPartyCostStats::where([
                    ['site_id', '=', $this->site_id],
                    ['service_type', '=', $log['service_type']],
                    ['provider_name', '=', $log['provider_name']],
                    ['date', '=', $log['date']],
                ])->find();

                if ($exists) {
                    // 更新
                    $exists->save([
                        'total_calls' => $log['total_calls'],
                        'success_calls' => $log['success_calls'],
                        'failed_calls' => $log['failed_calls'],
                        'total_cost' => round((float)$log['total_cost'], 2),
                        'avg_duration' => round((float)$log['avg_duration']),
                    ]);
                } else {
                    // 创建
                    ThirdPartyCostStats::create([
                        'site_id' => $this->site_id,
                        'service_type' => $log['service_type'],
                        'provider_name' => $log['provider_name'],
                        'date' => $log['date'],
                        'total_calls' => $log['total_calls'],
                        'success_calls' => $log['success_calls'],
                        'failed_calls' => $log['failed_calls'],
                        'total_cost' => round((float)$log['total_cost'], 2),
                        'avg_duration' => round((float)$log['avg_duration']),
                    ]);
                }
                $count++;
            }

            return [
                'success' => true,
                'count' => $count,
                'message' => "成功回填 {$count} 条统计数据"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '回填失败：' . $e->getMessage()
            ];
        }
    }
}
