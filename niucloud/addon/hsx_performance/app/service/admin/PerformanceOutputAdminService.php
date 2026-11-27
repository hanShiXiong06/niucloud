<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\admin;

use addon\hsx_performance\app\model\PerformanceAnomaly;
use addon\hsx_performance\app\model\PerformanceEmployeeDaily;
use addon\hsx_performance\app\model\PerformanceFact;
use addon\hsx_performance\app\model\PerformanceMetric;
use addon\hsx_performance\app\service\core\PerformanceAnomalyService;
use addon\hsx_performance\app\service\core\PerformanceMetricCatalog;
use addon\hsx_performance\app\service\core\PerformanceProjectionService;
use addon\hsx_performance\app\support\PerformanceDecimal;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class PerformanceOutputAdminService extends BaseAdminService
{
    public function overview(array $where): array
    {
        $range = $this->dateRange($where);
        $query = $this->dailyQuery($where, $range);
        $summary = (clone $query)->field(
            'COUNT(DISTINCT employee_uid) AS employee_count,
             COUNT(DISTINCT CONCAT(source_plugin, ":", metric_key)) AS metric_count,
             COALESCE(SUM(effective_fact_count),0) AS completed_count,
             COALESCE(SUM(quantity),0) AS quantity,
             COALESCE(SUM(amount),0) AS amount,
             COALESCE(SUM(profit),0) AS profit,
             COALESCE(SUM(duration_seconds),0) AS duration_seconds,
             COALESCE(SUM(original_count),0) AS original_count,
             COALESCE(SUM(reversal_count),0) AS reversal_count'
        )->findOrEmpty()->toArray();

        $employees = (clone $query)->field(
            'employee_uid,MAX(employee_name) AS employee_name,
             COALESCE(SUM(effective_fact_count),0) AS completed_count,
             COALESCE(SUM(quantity),0) AS quantity,
             COALESCE(SUM(amount),0) AS amount,
             COALESCE(SUM(profit),0) AS profit,
             COUNT(DISTINCT CONCAT(source_plugin, ":", metric_key)) AS metric_count,
             MAX(last_occurred_at) AS last_occurred_at'
        )->group('employee_uid')->order('completed_count desc,quantity desc,employee_uid asc')->limit(20)->select()->toArray();

        $metrics = (clone $query)->field(
            'source_plugin,business_chain,metric_key,MAX(metric_name) AS metric_name,
             MAX(fact_scope) AS fact_scope,MAX(unit) AS unit,
             COALESCE(SUM(effective_fact_count),0) AS completed_count,
             COALESCE(SUM(quantity),0) AS quantity,
             COALESCE(SUM(amount),0) AS amount,
             COALESCE(SUM(profit),0) AS profit'
        )->group('source_plugin,business_chain,metric_key')->order('completed_count desc,quantity desc')->select()->toArray();

        $trend = (clone $query)->field(
            'business_date,COALESCE(SUM(effective_fact_count),0) AS completed_count,
             COALESCE(SUM(quantity),0) AS quantity,
             COALESCE(SUM(amount),0) AS amount'
        )->group('business_date')->order('business_date asc')->select()->toArray();

        return [
            'range' => $range,
            'summary' => $this->castSummary($summary),
            'employees' => array_map([$this, 'castEmployee'], $employees),
            'metrics' => array_map([$this, 'castMetric'], $metrics),
            'trend' => array_map(static fn(array $row): array => [
                'date' => (string)$row['business_date'],
                'completed_count' => (int)$row['completed_count'],
                'quantity' => (string)$row['quantity'],
                'amount' => (string)$row['amount'],
            ], $trend),
            'filters' => $this->filterOptions(),
        ];
    }

    public function employeePage(array $where): array
    {
        $range = $this->dateRange($where);
        $query = $this->dailyQuery($where, $range);
        $page = $query->field(
            'employee_uid,MAX(employee_name) AS employee_name,
             COALESCE(SUM(effective_fact_count),0) AS completed_count,
             COALESCE(SUM(quantity),0) AS quantity,
             COALESCE(SUM(amount),0) AS amount,
             COALESCE(SUM(profit),0) AS profit,
             COUNT(DISTINCT CONCAT(source_plugin, ":", metric_key)) AS metric_count,
             MAX(last_occurred_at) AS last_occurred_at'
        )->group('employee_uid')->order('completed_count desc,quantity desc,employee_uid asc')->paginate([
            'page' => max(1, (int)($where['page'] ?? 1)),
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 20))),
        ])->toArray();
        $page['data'] = array_map([$this, 'castEmployee'], $page['data'] ?? []);
        $page['range'] = $range;
        return $page;
    }

    public function employeeInfo(int $employeeUid, array $where): array
    {
        if ($employeeUid <= 0) throw new CommonException('员工参数无效');
        $where['employee_uid'] = $employeeUid;
        $overview = $this->overview($where);
        $employee = $overview['employees'][0] ?? null;
        if (!$employee) throw new CommonException('当前范围没有该员工的产出数据');
        $facts = $this->factPage(array_merge($where, ['page' => 1, 'limit' => 30]));
        return [
            'range' => $overview['range'],
            'employee' => $employee,
            'metrics' => $overview['metrics'],
            'trend' => $overview['trend'],
            'facts' => $facts['data'] ?? [],
        ];
    }

    public function factPage(array $where): array
    {
        $range = $this->dateRange($where);
        $query = PerformanceFact::where([['site_id', '=', $this->site_id]])
            ->where('business_date', '>=', $range['start_date'])
            ->where('business_date', '<=', $range['end_date']);
        foreach (['employee_uid' => 'employee_uid', 'source_plugin' => 'source_plugin', 'business_chain' => 'business_chain',
                     'metric_key' => 'action_key', 'fact_scope' => 'fact_scope', 'fact_type' => 'fact_type'] as $input => $field) {
            if (($where[$input] ?? '') !== '') $query->where($field, '=', $where[$input]);
        }
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('event_id|employee_name|business_no|imei', '%' . $keyword . '%');
        }
        $page = $query->order('occurred_at desc,id desc')->paginate([
            'page' => max(1, (int)($where['page'] ?? 1)),
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 20))),
        ])->toArray();
        $page['data'] = array_map([$this, 'decorateMetricName'], $page['data'] ?? []);
        $page['range'] = $range;
        return $page;
    }

    public function anomalyPage(array $where): array
    {
        $query = PerformanceAnomaly::where([['site_id', '=', $this->site_id]]);
        if (($where['status'] ?? '') !== '') $query->where('status', '=', (string)$where['status']);
        if (($where['anomaly_type'] ?? '') !== '') $query->where('anomaly_type', '=', (string)$where['anomaly_type']);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('event_id|message', '%' . $keyword . '%');
        }
        return $query->order('status asc,update_at desc,id desc')->paginate([
            'page' => max(1, (int)($where['page'] ?? 1)),
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 20))),
        ])->toArray();
    }

    public function resolveAnomaly(int $id, string $status): array
    {
        if (!in_array($status, ['resolved', 'ignored', 'open'], true)) throw new CommonException('异常状态无效');
        $row = PerformanceAnomaly::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('数据异常不存在');
        $row->save([
            'status' => $status,
            'resolved_at' => $status === 'open' ? 0 : time(),
            'update_at' => time(),
        ]);
        return $row->toArray();
    }

    public function rebuild(array $where): array
    {
        $range = $this->dateRange($where);
        return array_merge(
            ['site_id' => (int)$this->site_id],
            (new PerformanceProjectionService())->rebuildSite((int)$this->site_id, $range['start_date'], $range['end_date'])
        );
    }

    public function reconcile(array $where): array
    {
        $range = $this->dateRange($where);
        $facts = PerformanceFact::where([['site_id', '=', $this->site_id]])
            ->where('business_date', '>=', $range['start_date'])->where('business_date', '<=', $range['end_date']);
        $groups = (clone $facts)->field(
            "business_date,employee_uid,source_plugin,action_key AS metric_key,fact_scope,role_key,
             COALESCE(SUM(quantity),0) AS quantity,
             COALESCE(SUM(amount),0) AS amount,
             COALESCE(SUM(profit),0) AS profit,
             COALESCE(SUM(duration_seconds),0) AS duration_seconds,
             COALESCE(SUM(quality_score),0) AS quality_score,
             COUNT(id) AS fact_count,
             COALESCE(SUM(direction),0) AS effective_fact_count,
             SUM(CASE WHEN fact_type = 'original' THEN 1 ELSE 0 END) AS original_count,
             SUM(CASE WHEN fact_type = 'reversal' THEN 1 ELSE 0 END) AS reversal_count,
             MAX(occurred_at) AS last_occurred_at"
        )->group('business_date,employee_uid,source_plugin,action_key,fact_scope,role_key')->select()->toArray();
        $summaryRows = PerformanceEmployeeDaily::where([['site_id', '=', $this->site_id]])
            ->where('business_date', '>=', $range['start_date'])->where('business_date', '<=', $range['end_date'])
            ->field('business_date,employee_uid,source_plugin,metric_key,fact_scope,role_key,quantity,amount,profit,duration_seconds,quality_score,fact_count,effective_fact_count,original_count,reversal_count,last_occurred_at')
            ->select()->toArray();
        $legacyHashCount = (clone $facts)->where('payload_hash', '=', '')->count();
        $orphanCount = 0;
        $reversals = (clone $facts)->where('fact_type', '=', 'reversal')->field('event_id,reversal_of_event_id')->select()->toArray();
        foreach ($reversals as $reversal) {
            $exists = PerformanceFact::where([
                ['site_id', '=', $this->site_id],
                ['event_id', '=', (string)$reversal['reversal_of_event_id']],
                ['fact_type', '=', 'original'],
            ])->count();
            if ($exists > 0) continue;
            $orphanCount++;
            (new PerformanceAnomalyService())->record(
                (int)$this->site_id,
                (string)$reversal['event_id'],
                'orphan_reversal',
                '对账发现冲红对应的原事实不存在',
                $reversal
            );
        }

        $summaryMap = [];
        foreach ($summaryRows as $row) $summaryMap[$this->projectionKey($row)] = $row;
        $projectionMismatches = [];
        $projectionMismatchCount = 0;
        foreach ($groups as $row) {
            $key = $this->projectionKey($row);
            $summary = $summaryMap[$key] ?? null;
            unset($summaryMap[$key]);
            $differentFields = $summary === null ? ['missing_summary'] : $this->projectionDifferentFields($row, $summary);
            if ($differentFields !== []) {
                $projectionMismatchCount++;
                if (count($projectionMismatches) < 20) {
                    $projectionMismatches[] = ['key' => $key, 'fields' => $differentFields];
                }
            }
        }
        foreach (array_keys($summaryMap) as $key) {
            $projectionMismatchCount++;
            if (count($projectionMismatches) < 20) {
                $projectionMismatches[] = ['key' => $key, 'fields' => ['orphan_summary']];
            }
        }
        if ($projectionMismatchCount > 0) {
            (new PerformanceAnomalyService())->record(
                (int)$this->site_id,
                'projection:' . $range['start_date'] . ':' . $range['end_date'],
                'projection_value_mismatch',
                '员工产出事实与日汇总数值不一致，建议执行重建',
                [
                    'fact_groups' => count($groups),
                    'summary_groups' => count($summaryRows),
                    'mismatch_count' => $projectionMismatchCount,
                    'samples' => $projectionMismatches,
                ]
            );
        }
        return [
            'range' => $range,
            'fact_count' => (int)(clone $facts)->count(),
            'fact_group_count' => count($groups),
            'summary_group_count' => count($summaryRows),
            'legacy_unhashed_count' => (int)$legacyHashCount,
            'orphan_reversal_count' => $orphanCount,
            'projection_mismatch_count' => $projectionMismatchCount,
            'projection_mismatch_samples' => $projectionMismatches,
            'projection_match' => $projectionMismatchCount === 0,
            'passed' => $projectionMismatchCount === 0 && $orphanCount === 0,
        ];
    }

    private function projectionKey(array $row): string
    {
        return implode('|', [
            (string)($row['business_date'] ?? ''),
            (int)($row['employee_uid'] ?? 0),
            (string)($row['source_plugin'] ?? ''),
            (string)($row['metric_key'] ?? $row['action_key'] ?? ''),
            (string)($row['fact_scope'] ?? ''),
            (string)($row['role_key'] ?? ''),
        ]);
    }

    private function projectionDifferentFields(array $fact, array $summary): array
    {
        $different = [];
        foreach (['quantity' => 4, 'amount' => 2, 'profit' => 2, 'quality_score' => 4] as $field => $scale) {
            if (PerformanceDecimal::normalize($fact[$field] ?? 0, $scale)
                !== PerformanceDecimal::normalize($summary[$field] ?? 0, $scale)) {
                $different[] = $field;
            }
        }
        foreach (['duration_seconds', 'fact_count', 'effective_fact_count', 'original_count', 'reversal_count', 'last_occurred_at'] as $field) {
            if ((int)($fact[$field] ?? 0) !== (int)($summary[$field] ?? 0)) $different[] = $field;
        }
        return $different;
    }

    private function dailyQuery(array $where, array $range)
    {
        $query = PerformanceEmployeeDaily::where([['site_id', '=', $this->site_id]])
            ->where('business_date', '>=', $range['start_date'])
            ->where('business_date', '<=', $range['end_date']);
        foreach (['employee_uid', 'source_plugin', 'business_chain', 'metric_key', 'fact_scope'] as $field) {
            if (($where[$field] ?? '') !== '') $query->where($field, '=', $where[$field]);
        }
        if (!empty($where['keyword'])) $query->whereLike('employee_name|metric_name', '%' . trim((string)$where['keyword']) . '%');
        return $query;
    }

    private function filterOptions(): array
    {
        $metrics = PerformanceMetric::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->field('source_plugin,business_chain,metric_key,metric_name,fact_scope,unit')
            ->order('sort desc,id asc')->select()->toArray();
        $plugins = [];
        foreach ($metrics as &$metric) {
            $metric = $this->decorateMetricName($metric);
            $key = (string)$metric['source_plugin'];
            $plugins[$key] = ['value' => $key, 'label' => $this->pluginName($key)];
        }
        unset($metric);
        return ['plugins' => array_values($plugins), 'metrics' => $metrics];
    }

    private function dateRange(array $where): array
    {
        $period = trim((string)($where['period'] ?? 'month'));
        $today = date('Y-m-d');
        $start = trim((string)($where['start_date'] ?? ''));
        $end = trim((string)($where['end_date'] ?? ''));
        if ($start !== '' || $end !== '') {
            $start = $this->validDate($start !== '' ? $start : $end);
            $end = $this->validDate($end !== '' ? $end : $start);
            if ($start > $end) [$start, $end] = [$end, $start];
            return ['period' => 'custom', 'start_date' => $start, 'end_date' => $end];
        }
        if ($period === 'today') return ['period' => 'today', 'start_date' => $today, 'end_date' => $today];
        if ($period === 'week') {
            return ['period' => 'week', 'start_date' => date('Y-m-d', strtotime('monday this week')), 'end_date' => $today];
        }
        if ($period === 'last7') {
            return ['period' => 'last7', 'start_date' => date('Y-m-d', strtotime('-6 days')), 'end_date' => $today];
        }
        return ['period' => 'month', 'start_date' => date('Y-m-01'), 'end_date' => $today];
    }

    private function validDate(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new CommonException('日期格式必须为YYYY-MM-DD');
        return $date;
    }

    private function castSummary(array $row): array
    {
        return [
            'employee_count' => (int)($row['employee_count'] ?? 0),
            'metric_count' => (int)($row['metric_count'] ?? 0),
            'completed_count' => (int)($row['completed_count'] ?? 0),
            'quantity' => (string)($row['quantity'] ?? '0'),
            'amount' => (string)($row['amount'] ?? '0'),
            'profit' => (string)($row['profit'] ?? '0'),
            'duration_seconds' => (int)($row['duration_seconds'] ?? 0),
            'original_count' => (int)($row['original_count'] ?? 0),
            'reversal_count' => (int)($row['reversal_count'] ?? 0),
        ];
    }

    private function castEmployee(array $row): array
    {
        foreach (['employee_uid', 'completed_count', 'metric_count', 'last_occurred_at'] as $field) $row[$field] = (int)($row[$field] ?? 0);
        foreach (['quantity', 'amount', 'profit'] as $field) $row[$field] = (string)($row[$field] ?? '0');
        return $row;
    }

    private function castMetric(array $row): array
    {
        $row['completed_count'] = (int)($row['completed_count'] ?? 0);
        foreach (['quantity', 'amount', 'profit'] as $field) $row[$field] = (string)($row[$field] ?? '0');
        return $this->decorateMetricName($row);
    }

    private function decorateMetricName(array $row): array
    {
        $metricKey = (string)($row['metric_key'] ?? $row['action_key'] ?? '');
        $metricName = trim((string)($row['metric_name'] ?? ''));
        if ($metricKey !== '' && PerformanceMetricCatalog::isKnown($metricKey)) {
            $definition = PerformanceMetricCatalog::definition($metricKey);
            $row['metric_name'] = (string)$definition['name'];
            $row['fact_scope'] = (string)$definition['scope'];
            $row['unit'] = (string)$definition['unit'];
        } elseif ($metricKey !== '' && ($metricName === '' || $metricName === $metricKey)) {
            $row['metric_name'] = $metricKey;
        }
        return $row;
    }

    private function pluginName(string $plugin): string
    {
        return [
            'hsx_recycle' => '回收业务',
            'hsx_erp' => 'ERP',
            'hsx_member_card' => '会员卡',
        ][$plugin] ?? $plugin;
    }
}
