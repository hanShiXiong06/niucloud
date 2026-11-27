<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\model\ProjectCenterIncomeBoard;
use addon\hsx_project_center\app\service\core\ProjectCenterIncomeParserService;
use core\base\BaseAdminService;

final class ProjectCenterIncomeAdminService extends BaseAdminService
{
    public function lists(int $projectId, int $date = 0): array
    {
        $query = ProjectCenterIncomeBoard::where([['site_id', '=', $this->site_id], ['project_id', '=', $projectId], ['status', '=', 1]]);
        if ($date > 0) $query->where('stat_date', '=', $date);
        else {
            $latest = (int)(clone $query)->max('stat_date');
            if ($latest > 0) $query->where('stat_date', '=', $latest);
        }
        return $query->order('stat_date desc,rank_no asc')->select()->toArray();
    }

    public function replace(int $projectId, string $text, int $date = 0): array
    {
        return (new ProjectCenterIncomeParserService())->replace((int)$this->site_id, $projectId, $text, $date);
    }
}
