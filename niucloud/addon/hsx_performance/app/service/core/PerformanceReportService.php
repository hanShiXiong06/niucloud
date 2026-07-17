<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

use addon\hsx_performance\app\model\PerformanceReport;
use app\model\sys\SysConfig;
use core\exception\CommonException;
use think\facade\Log;

final class PerformanceReportService
{
    public const METRICS_EVENT = 'HsxBusinessReportMetricsRequested';
    public const GENERATED_EVENT = 'HsxBusinessReportGenerated';

    public function generate(int $siteId, string $type, int $startAt = 0, int $endAt = 0, bool $dispatch = true): array
    {
        if ($siteId <= 0) throw new CommonException('经营报告缺少有效站点');
        [$startAt, $endAt, $periodKey, $title] = $this->period($type, $startAt, $endAt);
        $existing = PerformanceReport::where([
            ['site_id', '=', $siteId], ['report_type', '=', $type], ['period_key', '=', $periodKey],
        ])->findOrEmpty();
        $refreshExisting = !$existing->isEmpty() && $this->hasUnavailableProvider((array)$existing->providers_json);
        if (!$existing->isEmpty() && !$refreshExisting) {
            if ($dispatch && in_array((string)$existing->notify_status, ['pending', 'failed', 'skipped'], true)) {
                $this->dispatch((int)$existing->id);
                $existing->refresh();
            }
            return $existing->toArray();
        }

        $request = [
            'contract' => 'business.report.metrics.requested.v1',
            'site_id' => $siteId,
            'report_type' => $type,
            'period_key' => $periodKey,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'snapshot_at' => time(),
        ];
        try {
            $responses = (array)event(self::METRICS_EVENT, $request);
        } catch (\Throwable $e) {
            $responses = [['provider' => 'event_bus', 'available' => false, 'error' => $e->getMessage()]];
        }
        $providers = $this->normalizeProviders($responses);
        $snapshot = $this->aggregate($providers);
        $config = (new PerformanceConfigService())->get($siteId);
        if (empty($config['show_finance'])) {
            foreach (['sale_amount', 'stock_cost'] as $key) unset($snapshot['summary'][$key]);
            foreach ($snapshot['staff'] as &$staffRow) $staffRow['amount'] = 0;
            unset($staffRow);
        }
        if (empty($config['show_profit'])) {
            unset($snapshot['summary']['sale_profit']);
            foreach ($snapshot['staff'] as &$staffRow) $staffRow['profit'] = 0;
            unset($staffRow);
        }
        $reportData = [
            'site_id' => $siteId,
            'report_type' => $type,
            'period_key' => $periodKey,
            'title' => $title,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'summary_json' => $snapshot['summary'],
            'providers_json' => $providers,
            'snapshot_json' => $snapshot,
            'generated_at' => time(),
            'update_at' => time(),
        ];
        if ($refreshExisting) {
            $existing->save($reportData);
            $report = $existing;
        } else {
            $report = PerformanceReport::create(array_merge($reportData, [
                'report_no' => 'BR' . date('YmdHis') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'notify_status' => 'pending',
                'notify_attempts' => 0,
                'notify_error' => '',
                'create_at' => time(),
            ]));
        }
        if ($dispatch) $this->dispatch((int)$report->id);
        return PerformanceReport::where('id', '=', (int)$report->id)->findOrEmpty()->toArray();
    }

    public function dispatch(int $reportId): bool
    {
        $report = PerformanceReport::where('id', '=', $reportId)->findOrEmpty();
        if ($report->isEmpty() || (string)$report->notify_status === 'success') return false;
        $attempts = (int)$report->notify_attempts + 1;
        try {
            $snapshot = is_array($report->snapshot_json) ? $report->snapshot_json : [];
            $config = (new PerformanceConfigService())->get((int)$report->site_id);
            $results = (array)event(self::GENERATED_EVENT, [
                'event_id' => 'business-report:' . (int)$report->site_id . ':' . (string)$report->report_type . ':' . (string)$report->period_key,
                'event_name' => 'business.report.generated.v1',
                'site_id' => (int)$report->site_id,
                'report_id' => (int)$report->id,
                'report_type' => (string)$report->report_type,
                'period_key' => (string)$report->period_key,
                'title' => (string)$report->title,
                'summary' => (array)($snapshot['summary'] ?? []),
                'top_categories' => array_slice((array)($snapshot['categories'] ?? []), 0, 5),
                'top_staff' => array_slice((array)($snapshot['staff'] ?? []), 0, 8),
                'todos' => (array)($snapshot['todos'] ?? []),
                'receiver_uids' => (array)($config['receiver_uids'] ?? []),
                'target' => [
                    'plugin' => 'hsx_performance',
                    'route_key' => 'hsx_performance.report.detail',
                    'web_path' => 'site/hsx_performance/report/detail?id=' . (int)$report->id,
                    'miniapp_path' => 'addon/hsx_performance/pages/report/detail?id=' . (int)$report->id,
                ],
                'occurred_at' => (int)$report->generated_at,
            ]);
            $accepted = false;
            foreach ($results as $result) {
                if (is_array($result) && !empty($result['accepted'])) $accepted = true;
            }
            $report->save([
                'notify_status' => $accepted ? 'success' : 'skipped',
                'notify_attempts' => $attempts,
                'notify_error' => $accepted ? '' : '没有已启用的报告通知消费者',
                'notified_at' => $accepted ? time() : 0,
                'update_at' => time(),
            ]);
            return $accepted;
        } catch (\Throwable $e) {
            $report->save([
                'notify_status' => 'failed',
                'notify_attempts' => $attempts,
                'notify_error' => mb_substr($e->getMessage(), 0, 500),
                'update_at' => time(),
            ]);
            Log::warning('经营报告通知派发失败', ['report_id' => $reportId, 'message' => $e->getMessage()]);
            return false;
        }
    }

    public function tick(): array
    {
        $siteIds = SysConfig::where('config_key', '=', PerformanceConfigService::CONFIG_KEY)->column('site_id');
        $result = ['sites' => 0, 'generated' => 0, 'retried' => 0];
        foreach (array_values(array_unique(array_map('intval', $siteIds))) as $siteId) {
            $config = (new PerformanceConfigService())->get($siteId);
            if (empty($config['enabled'])) continue;
            $result['sites']++;
            // 队列即使晚于配置时间执行，也要依靠报告周期唯一键完成当天补生成。
            if (date('H:i') >= (string)$config['send_time']) {
                if (!empty($config['daily_enabled'])) { $this->generate($siteId, 'daily'); $result['generated']++; }
                if (!empty($config['weekly_enabled']) && (int)date('N') === 1) { $this->generate($siteId, 'weekly'); $result['generated']++; }
                if (!empty($config['monthly_enabled']) && (int)date('j') === 1) { $this->generate($siteId, 'monthly'); $result['generated']++; }
            }
        }
        $retryIds = PerformanceReport::where('notify_status', '=', 'failed')->where('notify_attempts', '<', 10)
            ->where('update_at', '<=', time() - 60)->order('id asc')->limit(100)->column('id');
        foreach ($retryIds as $id) if ($this->dispatch((int)$id)) $result['retried']++;
        return $result;
    }

    private function period(string $type, int $startAt, int $endAt): array
    {
        if ($startAt > 0 && $endAt >= $startAt) {
            return [$startAt, $endAt, date('Ymd', $startAt) . '-' . date('Ymd', $endAt), '经营报告 ' . date('Y-m-d', $startAt) . ' 至 ' . date('Y-m-d', $endAt)];
        }
        if ($type === 'weekly') {
            $start = strtotime('monday last week 00:00:00');
            $end = strtotime('sunday last week 23:59:59');
            return [$start, $end, date('o-\WW', $start), date('Y年m月d日', $start) . '至' . date('m月d日', $end) . '经营周报'];
        }
        if ($type === 'monthly') {
            $start = strtotime('first day of last month 00:00:00');
            $end = strtotime('last day of last month 23:59:59');
            return [$start, $end, date('Y-m', $start), date('Y年m月', $start) . '经营月报'];
        }
        $start = strtotime('yesterday 00:00:00');
        $end = strtotime('yesterday 23:59:59');
        return [$start, $end, date('Y-m-d', $start), date('Y年m月d日', $start) . '经营日报'];
    }

    private function normalizeProviders(array $responses): array
    {
        $providers = [];
        foreach ($responses as $response) {
            if (!is_array($response)) continue;
            $provider = trim((string)($response['provider'] ?? ''));
            if ($provider === '') continue;
            $providers[$provider] = array_merge([
                'provider' => $provider, 'provider_name' => $provider,
                'available' => true, 'summary' => [], 'categories' => [], 'staff' => [], 'todos' => [], 'details' => [],
            ], $response);
        }
        return array_values($providers);
    }

    private function hasUnavailableProvider(array $providers): bool
    {
        if ($providers === []) return true;
        foreach ($providers as $provider) {
            if (!is_array($provider) || empty($provider['available'])) return true;
        }
        return false;
    }

    private function aggregate(array $providers): array
    {
        $summary = [];
        $categories = [];
        $staff = [];
        $todos = [];
        foreach ($providers as $provider) {
            if (empty($provider['available'])) continue;
            foreach ((array)$provider['summary'] as $key => $value) {
                if (is_numeric($value)) $summary[$key] = round((float)($summary[$key] ?? 0) + (float)$value, 2);
            }
            foreach ((array)$provider['categories'] as $row) {
                if (!is_array($row)) continue;
                $key = trim((string)($row['key'] ?? $row['name'] ?? ''));
                if ($key === '') continue;
                if (!isset($categories[$key])) $categories[$key] = ['key' => $key, 'name' => (string)($row['name'] ?? $key), 'in_count' => 0, 'sale_count' => 0, 'stock_count' => 0];
                foreach (['in_count', 'sale_count', 'stock_count'] as $field) $categories[$key][$field] += (int)($row[$field] ?? 0);
            }
            foreach ((array)$provider['staff'] as $row) {
                if (!is_array($row)) continue;
                $uid = (int)($row['uid'] ?? 0);
                $role = trim((string)($row['role_key'] ?? 'worker'));
                if ($uid <= 0) continue;
                $key = $uid . ':' . $role;
                if (!isset($staff[$key])) $staff[$key] = ['uid' => $uid, 'name' => (string)($row['name'] ?? ('员工#' . $uid)), 'role_key' => $role, 'role_name' => (string)($row['role_name'] ?? $role), 'count' => 0, 'amount' => 0, 'profit' => 0];
                $staff[$key]['count'] += (int)($row['count'] ?? 0);
                $staff[$key]['amount'] = round((float)$staff[$key]['amount'] + (float)($row['amount'] ?? 0), 2);
                $staff[$key]['profit'] = round((float)$staff[$key]['profit'] + (float)($row['profit'] ?? 0), 2);
            }
            foreach ((array)$provider['todos'] as $key => $value) if (is_numeric($value)) $todos[$key] = (int)($todos[$key] ?? 0) + (int)$value;
        }
        $categoryRows = array_values($categories);
        usort($categoryRows, static fn(array $a, array $b): int => (($b['in_count'] + $b['sale_count']) <=> ($a['in_count'] + $a['sale_count'])) ?: strcmp($a['name'], $b['name']));
        $staffRows = array_values($staff);
        usort($staffRows, static fn(array $a, array $b): int => ($b['count'] <=> $a['count']) ?: strcmp($a['name'], $b['name']));
        return ['summary' => $summary, 'categories' => $categoryRows, 'staff' => $staffRows, 'todos' => $todos, 'providers' => $providers];
    }
}
