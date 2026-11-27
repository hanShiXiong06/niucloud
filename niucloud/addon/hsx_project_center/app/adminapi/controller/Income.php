<?php
declare(strict_types=1);
namespace addon\hsx_project_center\app\adminapi\controller;
use addon\hsx_project_center\app\service\admin\ProjectCenterIncomeAdminService;
use core\base\BaseAdminController;
final class Income extends BaseAdminController
{
    public function lists() { return success((new ProjectCenterIncomeAdminService())->lists((int)$this->request->param('project_id', 0), (int)$this->request->param('stat_date', 0))); }
    public function replace() { $params = $this->request->params([['project_id', 0], ['stat_date', 0], ['text', '']]); return success('收益榜已更新', (new ProjectCenterIncomeAdminService())->replace((int)$params['project_id'], (string)$params['text'], (int)$params['stat_date'])); }
}
