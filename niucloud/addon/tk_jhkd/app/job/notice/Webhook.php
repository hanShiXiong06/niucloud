<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\notice;
use addon\tk_jhkd\app\service\core\common\WebhookService;
use core\base\BaseJob;
use think\Exception;
use think\facade\Log;

/**
 * webHook
 */
class Webhook extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob($config,$text)
    {
        try {
            Log::write('webhook---聚合快递CPS业务通知--'.date('Y-m-d H:i:s', time()));
            if($config['is_webhook']==1){
                (new WebhookService())->sendMsg($config, $text);
            }
            return true;
        } catch (Exception $e) {
            return true;
        }
    }

}
