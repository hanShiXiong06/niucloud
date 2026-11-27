<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\adminapi\controller;

use addon\hsx_performance\app\service\admin\PerformanceReportAdminService;
use core\base\BaseAdminController;

final class Report extends BaseAdminController
{
    public function lists() { return success((new PerformanceReportAdminService())->page($this->request->params([['report_type', ''], ['page', 1], ['limit', 15]]))); }
    public function info(int $id) { return success((new PerformanceReportAdminService())->info($id)); }
    public function generate() { return success((new PerformanceReportAdminService())->generate($this->request->params([['report_type', 'daily'], ['start_at', 0], ['end_at', 0]]))); }
    public function retry(int $id) { return success((new PerformanceReportAdminService())->retry($id)); }
}
