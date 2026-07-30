<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\adminapi\controller;

use addon\hsx_performance\app\service\admin\PerformanceOutputAdminService;
use core\base\BaseAdminController;

final class Output extends BaseAdminController
{
    public function overview()
    {
        return success((new PerformanceOutputAdminService())->overview($this->filters()));
    }

    public function employees()
    {
        return success((new PerformanceOutputAdminService())->employeePage($this->filters()));
    }

    public function employee(int $uid)
    {
        return success((new PerformanceOutputAdminService())->employeeInfo($uid, $this->filters()));
    }

    public function facts()
    {
        return success((new PerformanceOutputAdminService())->factPage($this->filters()));
    }

    public function anomalies()
    {
        return success((new PerformanceOutputAdminService())->anomalyPage($this->request->params([
            ['status', 'open'], ['anomaly_type', ''], ['keyword', ''], ['page', 1], ['limit', 20],
        ])));
    }

    public function resolveAnomaly(int $id)
    {
        return success((new PerformanceOutputAdminService())->resolveAnomaly(
            $id,
            (string)$this->request->param('status', 'resolved')
        ));
    }

    public function rebuild()
    {
        return success((new PerformanceOutputAdminService())->rebuild($this->filters()));
    }

    public function reconcile()
    {
        return success((new PerformanceOutputAdminService())->reconcile($this->filters()));
    }

    private function filters(): array
    {
        return $this->request->params([
            ['period', 'month'], ['start_date', ''], ['end_date', ''], ['employee_uid', ''],
            ['source_plugin', ''], ['business_chain', ''], ['metric_key', ''], ['fact_scope', ''],
            ['fact_type', ''], ['keyword', ''], ['page', 1], ['limit', 20],
        ]);
    }
}
