<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpKpiService;
use core\base\BaseAdminController;

class ErpKpi extends BaseAdminController
{
    public function dashboard() { return success((new ErpKpiService())->dashboard($this->request->params([['period', 'month'], ['start_at', 0], ['end_at', 0]]))); }
    public function rules() { return success((new ErpKpiService())->rules()); }
    public function saveRules() { return success((new ErpKpiService())->saveRules((array)$this->request->param('rules', []))); }
}
