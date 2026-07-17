<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\adminapi\controller;

use addon\hsx_performance\app\service\admin\PerformanceConfigAdminService;
use core\base\BaseAdminController;

final class Config extends BaseAdminController
{
    public function info() { return success((new PerformanceConfigAdminService())->info()); }

    public function save()
    {
        $data = $this->request->params([
            ['enabled', 0], ['daily_enabled', 1], ['weekly_enabled', 1], ['monthly_enabled', 1],
            ['send_time', '09:10'], ['show_finance', 1], ['show_profit', 1], ['receiver_uids', []],
        ]);
        return success((new PerformanceConfigAdminService())->save($data));
    }
}
