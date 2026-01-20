<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\kd_api\app\job\js;


use addon\kd_api\app\dict\order\OrderDict;
use addon\kd_api\app\model\kdapi_order\KdapiOrder;
use core\base\BaseJob;
use think\facade\Log;

class JsOrder extends BaseJob
{

    public function doJob()
    {
        try {
            $apiOrderModel = new KdapiOrder();
            $res = $apiOrderModel->where(['is_js' => 0, 'status' => OrderDict::OVER])->limit(50)->select()->toArray();
            foreach ($res as $k => $v) {
                JsOrderJob::dispatch([
                    'id' => $v['id'],
                ]);
            }
            return true;
        } catch (\Exception $e) {
            Log::write('====快递API分佣结算异常====' . date('Y-m-d H:i:s') . '=====', 'error');
            Log::write($e->getMessage());
            return false;
        }
    }

}
