<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\tkfenxiao;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 分销关系变更触发
 */
class UpdateOrderJob extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob($data)
    {
        try {
            $res=event('TkFenxiaoOrderUpdate', $data)[0];
            if($res['code']==0){
                Log::write('=========聚合快递分销代理执行结果==========' . date('Y-m-d H:i:s'));
                Log::write($res['msg']);
            }
            return true;
        } catch (\Exception $e) {
            Log::write('=========分销代理数据入库队列失败==========' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage(), 'error');
            return false;
        }
    }

}
