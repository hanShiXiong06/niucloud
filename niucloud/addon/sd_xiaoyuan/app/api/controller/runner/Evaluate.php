<?php

namespace addon\sd_xiaoyuan\app\api\controller\runner;

use addon\sd_xiaoyuan\app\model\Evaluate as EvaluateModel;
use addon\sd_xiaoyuan\app\service\core\RunnerService;
use core\base\BaseApiController;

class Evaluate extends BaseApiController
{
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10]
        ]);
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        $model = new EvaluateModel();
        $where = [
            ['runner_id', '=', $runnerInfo['id']]
        ];
        
        $list = $model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $model->where($where)->count();
        
        $avgScore = $model->where($where)->avg('score') ?: 5;
        
        return success([
            'list' => $list,
            'count' => $count,
            'avg_score' => round($avgScore, 1)
        ]);
    }
}
