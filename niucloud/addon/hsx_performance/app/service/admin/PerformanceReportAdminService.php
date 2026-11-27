<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\admin;

use addon\hsx_performance\app\model\PerformanceReport;
use addon\hsx_performance\app\service\core\PerformanceReportService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class PerformanceReportAdminService extends BaseAdminService
{
    public function page(array $where): array
    {
        $query = PerformanceReport::where('site_id', '=', $this->site_id);
        if (!empty($where['report_type'])) $query->where('report_type', '=', (string)$where['report_type']);
        return $query->order('start_at desc,id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $row = PerformanceReport::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('经营报告不存在');
        return $row->toArray();
    }

    public function generate(array $data): array
    {
        $type = (string)($data['report_type'] ?? 'daily');
        if (!in_array($type, ['daily', 'weekly', 'monthly'], true)) throw new CommonException('不支持的报告类型');
        return (new PerformanceReportService())->generate(
            $this->site_id,
            $type,
            max(0, (int)($data['start_at'] ?? 0)),
            max(0, (int)($data['end_at'] ?? 0)),
            true
        );
    }

    public function retry(int $id): array
    {
        $this->info($id);
        (new PerformanceReportService())->dispatch($id);
        return $this->info($id);
    }
}
