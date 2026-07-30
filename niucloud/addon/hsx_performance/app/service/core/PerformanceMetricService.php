<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

use addon\hsx_performance\app\model\PerformanceMetric;

final class PerformanceMetricService
{
    public function ensure(array $fact): void
    {
        $where = [
            ['site_id', '=', (int)$fact['site_id']],
            ['source_plugin', '=', (string)$fact['source_plugin']],
            ['metric_key', '=', (string)$fact['action_key']],
        ];
        $metric = PerformanceMetric::where($where)->findOrEmpty();
        $data = [
            'business_chain' => (string)$fact['business_chain'],
            'metric_name' => (string)$fact['metric_name'],
            'fact_scope' => (string)$fact['fact_scope'],
            'unit' => (string)$fact['unit'],
            'update_at' => time(),
        ];
        if (!$metric->isEmpty()) {
            if ((string)$metric->metric_name === '' || (string)$metric->metric_name === (string)$metric->metric_key) {
                $metric->save($data);
            }
            return;
        }
        try {
            PerformanceMetric::create(array_merge($data, [
                'site_id' => (int)$fact['site_id'],
                'source_plugin' => (string)$fact['source_plugin'],
                'metric_key' => (string)$fact['action_key'],
                'description' => '',
                'status' => 1,
                'sort' => 0,
                'create_at' => time(),
            ]));
        } catch (\Throwable $e) {
            if (PerformanceMetric::where($where)->findOrEmpty()->isEmpty()) throw $e;
        }
    }
}
