<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderGoodsDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\dict\order\OrderRefundDict;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\model\order\OrderRefund;
use app\service\core\pay\CorePayService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 订单关闭服务层
 */
class CoreOrderCloseService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 订单关闭
     * @param array $data
     * @return void
     */
    public function close(array $data)
    {
        Db::startTrans();
        try {
            $order_data = $this->model->where([
                ['order_id', '=', $data['order_id']],
                ['site_id', '=', $data['site_id']]
            ])->lock(true)->findOrEmpty()->toArray();
            if (empty($order_data)) throw new CommonException('SHOP_ORDER_NOT_FOUND');//订单不存在
            // 架构:ERP 驱动的订单(代下单/出库卖出,有 relate_source)以 ERP 为唯一事实源,商城侧只读留痕。
            // 禁止从商城手动关闭(SHOP_CLOSE),请到 ERP 操作;关闭/退回由 ERP→商城 单向同步(直接 update,不经本方法)完成。
            if (!empty($order_data['relate_source']) && ($data['close_type'] ?? '') == OrderDict::SHOP_CLOSE) {
                throw new CommonException('此订单由 ERP 统一管理,请到 ERP 进行关闭 / 退回 / 收款操作');
            }
            if ($order_data['status'] == OrderDict::CLOSE) throw new CommonException('SHOP_ORDER_IS_CLOSED');
            if (($data['close_type'] ?? '') !== OrderDict::REFUND_CLOSE && (
                (int)$order_data['status'] !== OrderDict::WAIT_PAY
                || (int)($order_data['is_credit'] ?? 0) === 1
                || (float)($order_data['pay_money'] ?? 0) > 0
                || in_array((string)($order_data['payment_mode'] ?? ''), ['offline_cash', 'offline_credit'], true)
            )) {
                throw new CommonException('已收款或已挂账的订单不能直接关闭，请办理原单退货：未收款冲减应收，已收款办理退款');
            }
            if ($data['close_type'] == OrderDict::SHOP_CLOSE && !in_array($order_data['status'], [ OrderDict::WAIT_PAY, OrderDict::CLOSE ])) {
                throw new CommonException('SHOP_ORDER_IS_PAY_FINISH');//订单已支付
            }
            if ($data['close_type'] != OrderDict::REFUND_CLOSE) {
                //关闭相关的支付  todo  封装订单专用的关闭支付相关
                try {
                    (new CorePayService())->closeByTrade($data['site_id'], OrderDict::TYPE, $order_data['order_id']);
                } catch ( \Exception $e ) {
                    // 只有支付单已撤销可视为幂等；支付中/已支付等错误必须停止关单。
                    $pay = (new CorePayService())->findPayInfoByTrade((int)$data['site_id'], OrderDict::TYPE, (int)$order_data['order_id']);
                    if (!$pay->isEmpty() && $pay['status'] !== \app\dict\pay\PayDict::STATUS_CANCEL) {
                        throw new CommonException('支付状态尚未确认，未关闭订单或释放库存。请核对支付结果后重试：' . $e->getMessage());
                    }
                }
            }

            //关闭订单
            $this->model->where([
                [
                    ['order_id', '=', $order_data['order_id']]
                ]
            ])->update(
                [
                    'status' => OrderDict::CLOSE,
                    'close_type' => $data['close_type'],
                    'close_remark' => $data['close_remark'] ?? '',
                    'close_time' => time(),
                    'is_enable_refund' => 0,
                    'timeout' => 0
                ]
            );
            (new CoreOrderInventoryService())->releaseCancelled((int)$data['site_id'], (int)$order_data['order_id'], $data['close_type'] === OrderDict::REFUND_CLOSE);
            $data['order_data'] = $order_data;


            //订单关闭操作
            CoreOrderEventService::orderClose($data);
            Db::commit();
        } catch ( \Throwable $e ) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
        // 已提交的关单/库存不能因后置通知异常再回滚到外层事务。
        try {
            CoreOrderEventService::orderCloseAfter($data);
        } catch (\Throwable $e) {
            \think\facade\Log::error('[phone_shop] 订单已关闭，后置通知失败：' . (int)$data['order_id'] . '；' . $e->getMessage());
        }
        return true;
    }

    /**
     * 校验一下是否全部退款
     * @param $data
     * @return void
     * @throws \think\db\exception\DbException
     */
    public function checkAllClose($data)
    {
        $order_id = $data['order_id'];
        $site_id = $data['site_id'];
        //检测一下订单下的订单项是否全部退款完毕
        $where = array(
            ['order_id', '=', $order_id],
            ['status', '<>', OrderGoodsDict::REFUND_FINISH]
        );
        if ((new OrderGoods())->where($where)->count() == 0) {
            $data = [];
//            $data['main_type'] = OrderLogDict::SYSTEM;
//            $data['main_id'] = 0;
//            $data['close_type'] = OrderDict::REFUND_CLOSE;
//            $data['order_id'] = $order_id;
//            $data['site_id'] = $site_id;
//            $this->close($data);

            //判断订单总额是否全部扣除
            $order = (new Order())->where([['order_id', '=', $order_id]])->findOrEmpty();
            $total_refund_money = (new OrderRefund())->where([['order_id', '=', $order_id], ['status', '=', OrderRefundDict::FINISH]])->sum('money');
            if ($total_refund_money >= $order['order_money']) {
                $data['main_type'] = OrderLogDict::SYSTEM;
                $data['main_id'] = 0;
                $data['close_type'] = OrderDict::REFUND_CLOSE;
                $data['order_id'] = $order_id;
                $data['site_id'] = $site_id;
                $this->close($data);
            }else{//订单项全部关闭,但是订单总额为完全退款,订单直接完成
                //调用订单直接完成
                $data['main_type'] = OrderLogDict::SYSTEM;
                $data['main_id'] = 0;
                $data['order_id'] = $order_id;
                $data['site_id'] = $site_id;
                (new CoreOrderFinishService())->finish($data);
            }
            event('AfterPhoneShopOrderAllRefundFinish', $data);
        }
        return true;
    }

}
