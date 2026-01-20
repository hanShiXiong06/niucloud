<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\kd_api\app\job\js;


use addon\kd_api\app\dict\order\OrderDict;
use addon\kd_api\app\model\kdapi_order\KdapiOrder;
use app\dict\member\MemberAccountTypeDict;
use app\service\core\member\CoreMemberAccountService;
use core\base\BaseJob;
use think\facade\Db;
use think\facade\Log;

class JsOrderJob extends BaseJob
{

    public function doJob($id)
    {
        try {
            $apiOrderModel = new KdapiOrder();
            $order = $apiOrderModel->where(['is_js' => 0, 'status' => OrderDict::OVER,'id'=>$id])->findOrEmpty();
            Db::startTrans();
            if(!$order->isEmpty()){
                (new CoreMemberAccountService())->addLog($order['site_id'], $order['pub_id'], MemberAccountTypeDict::COMMISSION, $order['commission'], 'kd_api_award', '快递API推广激励');
                $apiOrderModel->where(['id' => $id])->update(['is_js' => 1]);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Log::write('====快递API分佣结算异常====' . date('Y-m-d H:i:s') . '=====', 'error');
            Log::write($e->getMessage());
            return false;
        }
    }

}
