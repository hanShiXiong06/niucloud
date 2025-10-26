<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\home_service\app\service\core\order;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\dict\order\OrderRefundLogDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderItem;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\service\core\card\CoreMemberCardService;
use addon\home_service\app\service\core\CoreGoodsSaleNumService;
use app\model\pay\Refund;
use app\service\admin\pay\RefundService;
use app\service\core\notice\NoticeService;
use app\service\core\pay\CoreRefundService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 订单售后
 * Class CoreOrderRefundService
 */
class CoreOrderRefundService extends BaseCoreService
{

    /**
     * 订单转账
     * @param int $refund_id
     * @param int $money
     * @param int $site_id
     * @param string $action_way
     * @param int $uid
     * Class CoreOrderRefundService
     */
    public function refundTransfer($refund_id, $money, $site_id, string $action_way, int $uid = 0)
    {
        $order_refund = (new OrderRefund())->where([['refund_id', '=', $refund_id], ['site_id', '=', $site_id]])->findOrEmpty();
        if ($order_refund->isEmpty()) throw new CommonException('REFUND_NOT_EXIST');
        // if ($refund->status != RefundDict::WAIT_REFUND) throw new CommonException('REFUND_STATUS_ERROR');

        if (bccomp($money, $order_refund->orderMain->order_money, 2) === 1) throw new CommonException('REFUND_MONEY_CANNOT_GT_PAYMONEY');
        if (bccomp($money, $order_refund->apply_money, 2) === 1) throw new CommonException('REFUND_MONEY_NOT_GT_APPLY_MONEY');

        Db::startTrans();
        try {
            // 添加维权日志
            CoreOrderRefundLogService::addLog($site_id, $refund_id, OrderRefundLogDict::REFUND, $action_way, $uid);
            $order_refund->refund_no = create_no();
            $order_refund->status = RefundDict::WAIT_REFUND;
            $order_refund->money = $money;
            $order_refund->transfer_time = time();
            $order_refund->save();
            (new OrderItem())->update(['refund_no' => $order_refund->refund_no], [['order_id', '=', $order_refund->order_id]]);

            //金额为0，直接完成
            if ($money > 0) {
                // 创建退款单
                $order_refund_no = (new CoreRefundService())->create($site_id, $order_refund->orderMain->out_trade_no,$order_refund->orderMain->pay_money, '');

                (new OrderRefund())->where([['refund_id','=', $refund_id]])->update(['refund_no' => $order_refund_no]);
                (new OrderItem())->where([['order_id', '=', $order_refund->order_id]])->update(['refund_no' => $order_refund_no]);
                (new Refund())->where([['refund_no', '=', $order_refund->refund_no]])->update(['money' => $money]);
                (new CoreRefundService())->refund($site_id, $order_refund_no);
            } else {
                $this->refundSuccess($order_refund->refund_no);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 退款成功
     * @param string $refund_no
     * @return void
     */
    public function refundSuccess(string $refund_no) {
        $refund = (new OrderRefund())->where([ [ 'refund_no', '=', $refund_no ] ])->findOrEmpty();

        if($refund->isEmpty()){
            (new OrderItem())->where([['refund_no', '=', $refund_no ]])->update(['refund_status' => RefundDict::REFUND_COMPLETED ]);
            return true;
        }

        $order = $refund->orderMain;
        if (!$refund->isEmpty()) {
            Db::startTrans();
            try {
                $pay_refund = (new RefundService())->getDetail($refund_no);
                $refund->status = RefundDict::REFUND_COMPLETED;
//                $refund->money = $pay_refund['money'] ?? 0;
                $refund->audit_time = time();
                $refund->transfer_time = time();
                $refund->save();

                if(!$order->is_card_order && in_array($order->order_status,[OrderDict::IN_SERVICE,OrderDict::WAIT_CHECK])){
                    event('ComputeOrderRefundCommission', [
                        'order_id' => $order->order_id,
                        'order_type' => $order->order_type,
                        'refund_id' => $refund->refund_id,
                    ]);
                    event('SettlementOrderRefundCommission', [
                        'order_id' => $order->order_id,
                        'order_type' => $order->order_type,
                        'refund_id' => $refund->refund_id,
                    ]);
                }

                (new OrderItem())->update(['refund_status' => RefundDict::REFUND_COMPLETED ], [ ['order_id', '=', $refund->order_id ] ]);

                (new Order())->update(['refund_status' => RefundDict::REFUND_COMPLETED, 'order_status' => OrderDict::CLOSE, 'close_time' => time(), 'is_abnormal' => 0], [ ['order_id', '=', $refund->order_id ] ]);

                // 添加订单日志
                CoreOrderLogService::addLog($refund['site_id'], $refund->order_id, OrderLogDict::ORDER_REFUND, 'system', 0, OrderDict::getStatus(OrderDict::CLOSE));

                // 添加售后日志
                CoreOrderRefundLogService::addLog($refund['site_id'], $refund['refund_id'], OrderRefundLogDict::COMPLETE, 'system');

                //次卡订单退还次数
                if ($order->is_card_order){
                    (new CoreMemberCardService())->recoverMemberCard($refund['site_id'],$order->order_id);
                }

                //返还优惠项
                (new CoreOrderDiscountService())->recoverDiscount($order->order_id);

//                CoreStatService::addStat($refund['site_id'], ['refund_num' => 1, 'refund_money' => $pay_refund['money'] ?? 0 ]);
//
//                // 发送退款成功提醒通知
                (new NoticeService())->send($order->site_id, 'home_service_refund', ['refund_no' => $refund_no]);

                //商品销量减少
                $item_info = (new OrderItem())->where([ ['order_item_id', '=', $refund['order_item_id']] ])->field('item_id, num, goods_id')->findOrEmpty()->toArray();
                if($item_info) {
                    $core_goods_sale_num_service = new CoreGoodsSaleNumService();
                    $core_goods_sale_num_service->dec([
                        'num' => $item_info['num'],
                        'goods_id' => $item_info['goods_id'],
                        'sku_id' => $item_info['item_id']
                    ]);
                }

                if (!empty($order->technician_id)){
                    event('NotificationEvent', [
                        'identity' => [NoticeDict::TECHNICIAN],
                        'type' => NoticeDict::REFUND_SUCCESS,
                        'notice_source' => NoticeDict::ORDER,
                        'order_id' => $order->order_id,
                        'technician_id' => $order->technician_id,
                        'member_id' => $order->member_id,
                        'site_id' => $order->site_id,
                    ]);
                }
                Db::commit();
                return true;
            } catch (\Exception $e) {
                Db::rollback();
                Log::write("订单退款成功事件处理错误，退款单号：{$refund_no} 错误原因：" . $e->getMessage().$e->getFile().$e->getLine());
                Throw new CommonException($e->getMessage().$e->getFile().$e->getLine());
                return false;
            }
        }
    }

    public function autoRefund($data)
    {
        $order = (new Order())->where([ ['order_id', '=', $data['order_id']]])->append(['order_status_info'])->findOrEmpty();
        if ($order->isEmpty()) return true;
        if (!$order->order_status_info['is_refund']) return true;
        $order_item = (new OrderItem())->where([ ['order_id', '=', $data['order_id']] ])->findOrEmpty();
        if (!in_array($order_item->refund_status, ['', RefundDict::CANCEL])) return true;

        $data['apply_money'] = $order->pay_money;

        Db::startTrans();
        try {
            //次卡订单
            if ($order->is_card_order){
                $data['apply_money'] = 0;
            }

            //退款金额不能大于可退款总额
            if ($data['apply_money'] > ($order_item['item_money'])) return true;

            $order_refund_no = create_no();
            if($data['apply_money'] > 0){
                $order_refund_no = (new CoreRefundService())->create($order->site_id, $order->out_trade_no, $data['apply_money'], $data['remark'] ?? '');
            }

            $insert_data = array(
                'order_id' => $data['order_id'],
                'order_item_id' => $data['order_item_id'],
                'site_id' => $order->site_id,
                'refund_no' => $order_refund_no,
                'reason' => '超时未抢自动退款',
                'member_id' => $order->member_id,
                'apply_money' => $data['apply_money'],
                'status' => RefundDict::WAIT_REFUND,
                'remark' => $data['remark'] ?? '',
                'voucher' => $data['voucher'] ?? '',
                'create_time' => time(),
                'source' => 'system'
            );
            $res = $this->model->create($insert_data);

            // 添加售后日志
            CoreOrderRefundLogService::addLog($order->site_id, $res->refund_id, OrderRefundLogDict::APPLY, 'system');
            //将订单项的退款单号覆盖
            $order_item->refund_no = $order_refund_no;
            $order_item->refund_status = RefundDict::WAIT_REFUND;
            $order_item->save();

            //退款金额为0或状态为待派遣，直接完成
            if($data['apply_money'] <= 0 || $order->order_status == OrderDict::WAIT_DISPATCH){
                (new CoreOrderRefundService())->refundTransfer($res->refund_id, $insert_data['apply_money'], $order->site_id, 'system');
            }

            Db::commit();
            return $res->refund_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

}
