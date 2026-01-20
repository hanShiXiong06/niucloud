<?php
declare (strict_types=1);

namespace addon\kd_api\app\listener\refund;

use addon\kd_api\app\dict\order\OrderDict;
use addon\kd_api\app\dict\status\StatusDict;
use addon\kd_api\app\model\kdapi_order\KdapiOrder;
use think\facade\Db;
use think\facade\Log;

/**
 * 退款完成后操作,重新计算佣金
 */
class AfterKdOrderRefundFinish
{
    public function handle($data)
    {
        Log::write('====AfterKdOrderCancel 快递API 订单取消后事件====');
        try {
            $order_data = $data['order_data'];
            $site_id = $order_data['site_id'];
            $member_id = $order_data['member_id'];
            $order_info = Db::name('tkjhkd_order')->where(['site_id' => $site_id, 'order_id' => $order_data['order_id']])->findOrEmpty();
            if (!$order_info) return [
                'msg' => '订单不存在',
                'code' => 0,
            ];
            if ($order_info['pub_id'] == '') return [
                'msg' => '订单未绑定快递API',
                'code' => 0,
            ];
            $api_info = Db::name('kdapi_api')->where('id', $order_info['pub_id'])->findOrEmpty();
            if (!$api_info) return [
                'msg' => '快递API不存在',
                'code' => 0,
            ];
            if ($api_info['status'] != StatusDict::SUCCESS) return [
                'msg' => '快递API未启用',
                'code' => 0,
            ];
            $deliver_info = Db::name('tkjhkd_order_delivery')->where('order_id', $order_info['order_id'])->findOrEmpty();
            if (!$deliver_info) return [
                'msg' => '订单配送信息不存在',
                'code' => 0,
            ];
            $start_address = json_decode($deliver_info['start_address'], true);
            $end_address = json_decode($deliver_info['end_address'], true);
            $send = explode("-", $start_address['address']);
            $end = explode("-", $end_address['address']);
            $send_province = $send[0];
            $end_province = $end[0];
            $end_city = $end[1];
            $title = $send_province . '---' . $end_province . $end_city;
            $commission = $order_info['pay_money'] * $api_info['rate'] / 100;
            $data = [
                'site_id' => $site_id,
                'member_id' => $member_id,
                'order_id' => $order_info['order_id'],
                'title' => $title,
                'order_money' => $order_info['order_money'],
                'pay_money' => $order_info['pay_money'],
                'commission' => $commission,
                'pub_id' => $order_info['pub_id'],
                'status' => OrderDict::REFUND,
                'sid' => $order_info['sid'],
                'create_time' => time(),
            ];
            $apiOrderModel = new KdapiOrder();
            $api_order = $apiOrderModel->where([
                'order_id' => $order_info['order_id'],
                'site_id' => $site_id,
            ])->findOrEmpty();
            if ($api_order->isEmpty()) {
                $apiOrderModel->create($data);
            } else {
                if($api_order['is_js'] == 1) return true;
                $apiOrderModel->where(['order_id' => $order_info['order_id']])->update($data);
            }
            return true;
        } catch (\Exception $e) {
            Log::write('====AfterKdOrderFinish 快递API 订单完成取消事件 异常====');
            Log::write($e->getMessage());
            Log::write($data);
        }
    }
}
