<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\PointsMallService;
use core\base\BaseApiController;

class Member extends BaseApiController
{
    public function points()
    {
        $service = new PointsMallService();
        return success($service->getMyPoints());
    }

    public function pointsRecord()
    {
        $type = $this->request->param('type', '');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 20);
        $service = new PointsMallService();
        return success($service->getPointsRecord($type, $page, $limit));
    }
}
