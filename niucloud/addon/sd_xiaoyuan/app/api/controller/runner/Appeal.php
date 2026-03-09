<?php

namespace addon\sd_xiaoyuan\app\api\controller\runner;

use addon\sd_xiaoyuan\app\model\Appeal as AppealModel;
use addon\sd_xiaoyuan\app\service\core\RunnerService;
use core\base\BaseApiController;

class Appeal extends BaseApiController
{
    public function lists()
    {
        $params = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10]
        ]);
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        $model = new AppealModel();
        $where = [
            ['runner_id', '=', $runnerInfo['id']]
        ];
        
        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        
        $list = $model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $model->where($where)->count();
        
        return success([
            'list' => $list,
            'count' => $count
        ]);
    }

    public function add()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['appeal_type', ''],
            ['content', ''],
            ['images', []]
        ]);
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        $model = new AppealModel();
        
        $exists = $model->where([
            ['order_id', '=', $data['order_id']],
            ['runner_id', '=', $runnerInfo['id']]
        ])->findOrEmpty();
        
        if (!$exists->isEmpty()) {
            return fail('该订单已申诉过');
        }
        
        $model->create([
            'order_id' => $data['order_id'],
            'runner_id' => $runnerInfo['id'],
            'appeal_type' => $data['appeal_type'],
            'content' => $data['content'],
            'images' => json_encode($data['images']),
            'status' => 0,
            'create_time' => time()
        ]);
        
        return success('申诉提交成功');
    }

    public function detail()
    {
        $id = $this->request->param('id', 0);
        
        $runnerService = new RunnerService();
        $runnerInfo = $runnerService->getInfo();
        
        if (empty($runnerInfo)) {
            return fail('跑腿员信息不存在');
        }
        
        $model = new AppealModel();
        $detail = $model->where([
            ['id', '=', $id],
            ['runner_id', '=', $runnerInfo['id']]
        ])->findOrEmpty()->toArray();
        
        return success($detail);
    }
}
