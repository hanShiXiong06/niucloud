<?php

namespace addon\ai_image\app\job\async;

use addon\ai_image\app\model\aiimagecreate\AiimageCreate;
use core\base\BaseJob;
use think\Exception;
use think\facade\Log;

class AsyncCreateJob extends BaseJob
{
    /**
     * 消费
     * @param $data
     * @return true
     */
    public function doJob()
    {
        try {
            $order = (new AiimageCreate())->where(['status' => 0])->limit(50)->select();
            foreach ($order as $item) {
                CreateJob::dispatch(['site_id' => $item['site_id'], 'id' => $item['id']]);
            }
            return true;
        } catch (Exception $e) {
            Log::write('===AI设计生成状态同步错误===' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            return true;
        }
    }
}
