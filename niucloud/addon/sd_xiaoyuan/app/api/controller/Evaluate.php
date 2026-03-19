<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\EvaluateService;
use core\base\BaseApiController;

class Evaluate extends BaseApiController
{
    public function add()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['score', 5],
            ['service_score', 5],
            ['speed_score', 5],
            ['content', ''],
            ['images', []],
            ['is_anonymous', 0]
        ]);
        
        $service = new EvaluateService();
        $result = $service->add($data);
        
        return success($result);
    }

    public function lists()
    {
        $runnerId = $this->request->param('runner_id', 0);
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new EvaluateService();
        $result = $service->getRunnerEvaluates($runnerId, $params);
        
        return success($result);
    }

    public function myEvaluates()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);
        
        $service = new EvaluateService();
        $result = $service->getMyEvaluates($params);
        
        return success($result);
    }
}
