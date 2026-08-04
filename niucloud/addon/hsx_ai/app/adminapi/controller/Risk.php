<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\adminapi\controller;

use addon\hsx_ai\app\service\admin\AiRiskAdminService;
use core\base\BaseAdminController;
use think\Response;

final class Risk extends BaseAdminController
{
    public function lists(): Response
    {
        $where = $this->request->params([
            ['keyword', ''], ['risk_level', 0], ['risk_type', ''], ['status', ''], ['page', 1], ['limit', 15],
        ]);
        return success((new AiRiskAdminService())->getPage($where));
    }
}
