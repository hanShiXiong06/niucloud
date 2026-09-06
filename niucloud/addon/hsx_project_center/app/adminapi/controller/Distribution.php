<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\adminapi\controller;

use addon\hsx_project_center\app\service\admin\ProjectCenterDistributionAdminService;
use core\base\BaseAdminController;

final class Distribution extends BaseAdminController
{
    public function overview() { return success((new ProjectCenterDistributionAdminService())->overview()); }
    public function lists() { return success((new ProjectCenterDistributionAdminService())->page($this->request->params([
        ['project_id', 0], ['status', ''], ['keyword', ''], ['page', 1], ['limit', 15],
    ]))); }
    public function info(int $id) { return success((new ProjectCenterDistributionAdminService())->info($id)); }
    public function projects() { return success((new ProjectCenterDistributionAdminService())->projects()); }
    public function settle(int $id) { (new ProjectCenterDistributionAdminService())->settle($id); return success('佣金结算已执行'); }
}
