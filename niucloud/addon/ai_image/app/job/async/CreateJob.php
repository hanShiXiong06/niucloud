<?php

namespace addon\ai_image\app\job\async;

use addon\ai_image\app\adminapi\controller\aiimagemodel\AiimageModel;
use addon\ai_image\app\model\aiimagecreate\AiimageCreate;
use addon\ai_image\app\service\core\ConfigService;
use addon\ai_image\app\service\core\DuomiService;
use addon\ai_image\app\service\core\FetchService;
use core\base\BaseJob;
use think\Exception;
use think\facade\Log;

/**
 * 调用订单催付通知
 */
class CreateJob extends BaseJob
{
    /**
     * 消费
     * @param $data
     * @return true
     */
    public function doJob($site_id, $id)
    {
        try {
            $this->model = new AiimageCreate();
            $this->site_id = $site_id;
            $info = $this->model->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
                ['status', '=', 0]
            ])->findOrEmpty();
            if ($info->isEmpty()) return true;
            $info = $this->model->where([['id', "=", $id]])->with(['member', 'aiimageModel'])->findOrEmpty()->toArray();
            //判断状态进行生成查询
            $config = (new ConfigService())->getConfigSite($this->site_id);
            $result = (new DuomiService($config))->query($info['task_id']);
            if ($result['code'] == 200 && $info['status'] == 0) {
                if ($result['data']['state'] == 'succeeded') {
                    $url = (new FetchService())
                        ->save(
                            $result['data']['data']['images'][0]['url'],
                            $this->site_id,
                            'ai_image/' . $this->site_id . '/' . date('Y') . date('m') . date('d'),
                            true
                        );
                    $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
                        ->update([
                            'status' => 1,
                            'images' => $url
                        ]);
                }
                if ($result['data']['state'] == 'error') {
                    $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
                        ->update([
                            'state' => 'error',
                            'status' => 2,
                            'msg' => $result['data']['msg']
                        ]);
                }
            }
            return true;
        } catch (Exception $e) {
            Log::write('===AI设计状态队列错误===' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            return true;
        }
    }
}
