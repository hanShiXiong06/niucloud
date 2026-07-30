<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

use addon\hsx_performance\app\model\PerformanceEmployeeDaily;
use addon\hsx_performance\app\model\PerformanceFact;
use addon\hsx_performance\app\model\PerformanceMetric;
use think\facade\Db;

final class PerformanceProjectionService
{
    public function rebuildForFact(array $fact): void
    {
        // 指标行是同一指标所有日投影的串行锁，避免并发消费时后写覆盖先写。
        PerformanceMetric::where([
            ['site_id', '=', (int)$fact['site_id']],
            ['source_plugin', '=', (string)$fact['source_plugin']],
            ['metric_key', '=', (string)$fact['action_key']],
        ])->lock(true)->findOrEmpty();
        $this->rebuildSlice([
            'site_id' => (int)$fact['site_id'],
            'business_date' => (string)$fact['business_date'],
            'employee_uid' => (int)$fact['employee_uid'],
            'source_plugin' => (string)$fact['source_plugin'],
            'metric_key' => (string)$fact['action_key'],
            'fact_scope' => (string)$fact['fact_scope'],
            'role_key' => (string)$fact['role_key'],
        ]);
    }

    public function rebuildSlice(array $key): void
    {
        $where = [
            ['site_id', '=', (int)$key['site_id']],
            ['business_date', '=', (string)$key['business_date']],
            ['employee_uid', '=', (int)$key['employee_uid']],
            ['source_plugin', '=', (string)$key['source_plugin']],
            ['action_key', '=', (string)$key['metric_key']],
            ['fact_scope', '=', (string)$key['fact_scope']],
            ['role_key', '=', (string)$key['role_key']],
        ];
        $aggregate = PerformanceFact::where($where)->field(
            "COALESCE(SUM(quantity),0) AS quantity,
             COALESCE(SUM(amount),0) AS amount,
             COALESCE(SUM(profit),0) AS profit,
             COALESCE(SUM(duration_seconds),0) AS duration_seconds,
             COALESCE(SUM(quality_score),0) AS quality_score,
             COUNT(id) AS fact_count,
             COALESCE(SUM(direction),0) AS effective_fact_count,
             SUM(CASE WHEN fact_type = 'original' THEN 1 ELSE 0 END) AS original_count,
             SUM(CASE WHEN fact_type = 'reversal' THEN 1 ELSE 0 END) AS reversal_count,
             MAX(occurred_at) AS last_occurred_at"
        )->findOrEmpty();

        $summaryWhere = [
            ['site_id', '=', (int)$key['site_id']],
            ['business_date', '=', (string)$key['business_date']],
            ['employee_uid', '=', (int)$key['employee_uid']],
            ['source_plugin', '=', (string)$key['source_plugin']],
            ['metric_key', '=', (string)$key['metric_key']],
            ['fact_scope', '=', (string)$key['fact_scope']],
            ['role_key', '=', (string)$key['role_key']],
        ];
        $summary = PerformanceEmployeeDaily::where($summaryWhere)->findOrEmpty();
        if ($aggregate->isEmpty() || (int)$aggregate->fact_count === 0) {
            if (!$summary->isEmpty()) $summary->delete();
            return;
        }

        $latest = PerformanceFact::where($where)->order('occurred_at desc,id desc')->findOrEmpty();
        $data = [
            'employee_name' => (string)$latest->employee_name,
            'business_chain' => (string)$latest->business_chain,
            'metric_name' => (string)$latest->metric_name,
            'unit' => (string)$latest->unit,
            'quantity' => (string)$aggregate->quantity,
            'amount' => (string)$aggregate->amount,
            'profit' => (string)$aggregate->profit,
            'duration_seconds' => (int)$aggregate->duration_seconds,
            'quality_score' => (string)$aggregate->quality_score,
            'fact_count' => (int)$aggregate->fact_count,
            'effective_fact_count' => (int)$aggregate->effective_fact_count,
            'original_count' => (int)$aggregate->original_count,
            'reversal_count' => (int)$aggregate->reversal_count,
            'last_occurred_at' => (int)$aggregate->last_occurred_at,
            'update_at' => time(),
        ];
        if (!$summary->isEmpty()) {
            $summary->save($data);
            return;
        }
        PerformanceEmployeeDaily::create(array_merge($data, [
            'site_id' => (int)$key['site_id'],
            'business_date' => (string)$key['business_date'],
            'employee_uid' => (int)$key['employee_uid'],
            'source_plugin' => (string)$key['source_plugin'],
            'metric_key' => (string)$key['metric_key'],
            'fact_scope' => (string)$key['fact_scope'],
            'role_key' => (string)$key['role_key'],
            'create_at' => time(),
        ]));
    }

    public function rebuildSite(int $siteId, string $startDate = '', string $endDate = ''): array
    {
        $groups = [];
        Db::transaction(function () use ($siteId, $startDate, $endDate, &$groups): void {
            $query = PerformanceFact::where([['site_id', '=', $siteId]]);
            $summaryQuery = PerformanceEmployeeDaily::where([['site_id', '=', $siteId]]);
            if ($startDate !== '') {
                $query->where('business_date', '>=', $startDate);
                $summaryQuery->where('business_date', '>=', $startDate);
            }
            if ($endDate !== '') {
                $query->where('business_date', '<=', $endDate);
                $summaryQuery->where('business_date', '<=', $endDate);
            }
            $groups = $query->field('site_id,business_date,employee_uid,source_plugin,action_key AS metric_key,fact_scope,role_key')
                ->group('site_id,business_date,employee_uid,source_plugin,action_key,fact_scope,role_key')
                ->select()->toArray();
            $summaryQuery->delete();
            foreach ($groups as $group) $this->rebuildSlice($group);
        });
        return ['group_count' => count($groups), 'start_date' => $startDate, 'end_date' => $endDate];
    }
}
