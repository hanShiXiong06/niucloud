<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\adminapi\controller;

use addon\hsx_ai\app\service\admin\AiConfigAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Log extends BaseAdminController
{
    public function lists(): Response
    {
        $where = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['scene_key', ''],
            ['provider_id', ''],
            ['page', 1],
            ['limit', 15],
        ]);
        return success((new AiConfigAdminService())->logs($where));
    }
}
